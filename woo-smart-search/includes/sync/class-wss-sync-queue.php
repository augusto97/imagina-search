<?php
/**
 * Sync Queue.
 *
 * Manages the product/post sync queue. Prefers Action Scheduler when
 * available (bundled with WooCommerce), falls back to WP-Cron, and
 * can process immediately as a last resort.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Sync_Queue
 */
class WSS_Sync_Queue {

	/**
	 * Delay in seconds before processing queued items (to batch multiple changes).
	 */
	const QUEUE_DELAY = 30;

	/**
	 * Maximum number of retries for failed items.
	 */
	const MAX_RETRIES = 3;

	/**
	 * Whether request-end processing has already been registered this request.
	 *
	 * @var bool
	 */
	private static $shutdown_registered = false;

	/**
	 * Products queued in this request (product_id => action).
	 *
	 * @var array
	 */
	private static $seen = array();

	/**
	 * Whether processing was already scheduled in this request.
	 *
	 * @var bool
	 */
	private static $scheduled = false;

	/**
	 * Initialize queue processing hooks.
	 */
	public function init() {
		// Action Scheduler hook (preferred — WooCommerce bundles it).
		add_action( 'wss_process_sync_queue', array( $this, 'process_queue' ) );

		// WP-Cron fallback for sites without Action Scheduler.
		add_action( 'wss_cron_process_queue', array( $this, 'process_queue' ) );
	}

	/**
	 * Add a product/post to the sync queue and ensure processing is scheduled.
	 *
	 * @param int    $product_id Product/post ID.
	 * @param string $action     Action: 'update' or 'delete'.
	 */
	public static function add( int $product_id, string $action = 'update' ) {
		global $wpdb;

		// One product save fires ~15 hooks (meta, terms, save_post, WC update…):
		// queue each product/action once per request.
		if ( isset( self::$seen[ $product_id ] ) && self::$seen[ $product_id ] === $action ) {
			return;
		}
		self::$seen[ $product_id ] = $action;

		$table = $wpdb->prefix . 'wss_sync_queue';
		$now   = current_time( 'mysql', true ); // UTC everywhere in this table.

		// Reuse the pending row if there is one. (Checking UPDATE's affected
		// rows is not enough: MySQL reports 0 when the values are unchanged,
		// which inserted a duplicate row per hook.)
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE product_id = %d AND status = 'pending' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$product_id
			)
		);

		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'action'       => $action,
					'scheduled_at' => $now,
					'priority'     => 0,
				),
				array( 'id' => (int) $existing ),
				array( '%s', '%s', '%d' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'product_id'   => $product_id,
					'action'       => $action,
					'priority'     => 0,
					'scheduled_at' => $now,
					'status'       => 'pending',
				),
				array( '%d', '%s', '%d', '%s', '%s' )
			);
		}

		// Schedule queue processing via the best available mechanism (cron /
		// Action Scheduler) as a reliable fallback...
		self::ensure_processing_scheduled();

		// ...and also process at the end of THIS request so the change is
		// reflected almost immediately, instead of waiting for the next cron
		// tick (which on low-traffic stores can be minutes away or, if WP-Cron
		// is disabled, never). The response is flushed first so the editor /
		// API caller is never kept waiting for the indexing HTTP round-trip.
		self::register_shutdown_processing();
	}

	/**
	 * Register a one-time request-end handler that drains the queue.
	 */
	private static function register_shutdown_processing() {
		if ( self::$shutdown_registered ) {
			return;
		}
		// On the CLI (e.g. WP-CLI imports) there is no request end to hook and
		// the caller is long-running, so leave processing to the scheduler.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		self::$shutdown_registered = true;
		add_action( 'shutdown', array( __CLASS__, 'process_on_shutdown' ), 100 );
	}

	/**
	 * Drain the queue at the end of the current request.
	 *
	 * Flushes the HTTP response first (when the SAPI supports it) so the user
	 * or API client gets their response immediately and indexing happens after.
	 */
	public static function process_on_shutdown() {
		// Send the response now; keep working in the background.
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			@fastcgi_finish_request(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			@litespeed_finish_request(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		// If the engine is down, leave the pending items for the scheduled run.
		if ( ! wss_get_engine() ) {
			return;
		}

		try {
			( new self() )->process_queue();
		} catch ( \Throwable $e ) {
			wss_log( 'Sync queue shutdown processing error: ' . $e->getMessage(), 'error' );
		}
	}

	/**
	 * Ensure a queue processing run is scheduled.
	 *
	 * Tries Action Scheduler first (reliable, used by WooCommerce), then
	 * WP-Cron, and finally processes immediately as a last resort.
	 */
	private static function ensure_processing_scheduled() {
		if ( self::$scheduled ) {
			return;
		}
		self::$scheduled = true;

		// 1. Action Scheduler (bundled with WooCommerce).
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( 'wss_process_sync_queue' ) ) {
				as_schedule_single_action( time() + self::QUEUE_DELAY, 'wss_process_sync_queue', array(), 'woo-smart-search' );
			}
			return;
		}

		// 2. WP-Cron fallback.
		if ( ! wp_next_scheduled( 'wss_cron_process_queue' ) ) {
			wp_schedule_single_event( time() + self::QUEUE_DELAY, 'wss_cron_process_queue' );
		}
	}

	/**
	 * Process the sync queue.
	 *
	 * Fetches pending items and syncs them one by one via the configured
	 * search engine (Meilisearch or Local). Items that fail are retried
	 * up to MAX_RETRIES times with exponential backoff.
	 */
	public function process_queue() {
		global $wpdb;

		$engine = wss_get_engine();

		if ( ! $engine ) {
			$engine_type = wss_get_option( 'search_engine', 'meilisearch' );
			wss_log(
				sprintf(
					/* translators: %s: engine type */
					__( 'Sync queue: search engine (%s) not available. Will retry on next scheduled run.', 'woo-smart-search' ),
					$engine_type
				),
				'warning'
			);

			// Reschedule so items are not lost — but use a longer delay to
			// avoid hammering a down engine every 30 seconds.
			self::schedule_retry( 120 );
			return;
		}

		$table          = $wpdb->prefix . 'wss_sync_queue';
		$content_source = wss_get_content_source();
		$is_ecommerce   = wss_is_ecommerce_mode();
		$is_mixed       = 'mixed' === $content_source;

		$product_sync = null;
		$post_sync    = null;

		if ( $is_ecommerce || $is_mixed ) {
			$product_sync = new WSS_Product_Sync();
		}
		if ( ! $is_ecommerce || $is_mixed ) {
			$post_sync = new WSS_Post_Sync();
		}

		// One drain at a time: shutdown handlers of concurrent requests and the
		// scheduled run would otherwise index the same items in parallel.
		if ( ! wss_acquire_lock( 'queue', 300 ) ) {
			return;
		}

		try {
			$this->drain( $table, $product_sync, $post_sync );
		} finally {
			wss_release_lock( 'queue' );
		}
	}

	/**
	 * Process one batch of due items. Caller holds the queue lock.
	 *
	 * @param string                $table        Queue table.
	 * @param WSS_Product_Sync|null $product_sync Product handler.
	 * @param WSS_Post_Sync|null    $post_sync    Post handler.
	 */
	private function drain( $table, $product_sync, $post_sync ) {
		global $wpdb;

		$now = current_time( 'mysql', true );

		// Due pending items only (max 50 at a time), so the retry backoff works.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'pending' AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now
			),
			ARRAY_A
		);

		if ( empty( $items ) ) {
			$this->schedule_next_due( $table );
			return;
		}

		$processed = 0;
		$failed    = 0;

		foreach ( $items as $item ) {
			$post_id   = (int) $item['product_id'];
			$action    = $item['action'];
			$post_type = get_post_type( $post_id );

			$success = false;

			// Route to correct sync handler based on post type.
			$is_product = ( 'product' === $post_type ) && $product_sync;

			if ( 'delete' === $action ) {
				// A hard-deleted post no longer has a post type: both handlers
				// remove the same document ID, so use whichever is available.
				if ( $is_product || ( ! $post_type && $product_sync ) ) {
					$success = $product_sync->delete_single_product( $post_id );
				} elseif ( $post_sync ) {
					$success = $post_sync->delete_single_post( $post_id );
				} elseif ( $product_sync ) {
					$success = $product_sync->delete_single_product( $post_id );
				}
			} else {
				$success = $is_product
					? $product_sync->sync_single_product( $post_id )
					: ( $post_sync ? $post_sync->sync_single_post( $post_id ) : false );
			}

			if ( $success ) {
				++$processed;
				$wpdb->update(
					$table,
					array(
						'status'       => 'completed',
						'processed_at' => current_time( 'mysql', true ),
					),
					array( 'id' => $item['id'] ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			} else {
				++$failed;
				$retries = isset( $item['priority'] ) ? (int) $item['priority'] : 0;
				++$retries;

				if ( $retries < self::MAX_RETRIES ) {
					$backoff_seconds = (int) pow( 5, $retries ); // 5s, 25s, 125s.
					$wpdb->update(
						$table,
						array(
							'status'       => 'pending',
							'priority'     => $retries,
							'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + $backoff_seconds ),
						),
						array( 'id' => $item['id'] ),
						array( '%s', '%d', '%s' ),
						array( '%d' )
					);
				} else {
					$wpdb->update(
						$table,
						array(
							'status'       => 'failed',
							'priority'     => $retries,
							'processed_at' => current_time( 'mysql', true ),
						),
						array( 'id' => $item['id'] ),
						array( '%s', '%d', '%s' ),
						array( '%d' )
					);
					wss_log(
						sprintf(
							/* translators: 1: post ID 2: action 3: retry count */
							__( 'Sync failed after %3$d retries: post %1$d (%2$s)', 'woo-smart-search' ),
							$post_id,
							$action,
							self::MAX_RETRIES
						),
						'error'
					);
				}
			}
		}

		if ( $processed > 0 || $failed > 0 ) {
			wss_log(
				sprintf(
					/* translators: 1: processed count 2: failed count */
					__( 'Sync queue batch: %1$d processed, %2$d failed.', 'woo-smart-search' ),
					$processed,
					$failed
				),
				'info'
			);
		}

		// Record real sync activity so the admin "Last Sync" reflects
		// incremental / periodic syncs, not only manual Full Syncs. Lets the
		// field work as a health signal (a stale value = nothing is syncing).
		if ( $processed > 0 ) {
			wss_touch_last_sync();
		}

		// Clean old completed entries (older than 24 hours).
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status = 'completed' AND processed_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
			)
		);
		// Keep failed entries for 7 days for visibility.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status = 'failed' AND processed_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) )
			)
		);

		// If more pending items remain, schedule the next run for when the
		// earliest one is due.
		$this->schedule_next_due( $table );
	}

	/**
	 * Schedule a run for the earliest pending item (≥ 5 s from now).
	 *
	 * @param string $table Queue table.
	 */
	private function schedule_next_due( $table ) {
		global $wpdb;

		$next = $wpdb->get_var( "SELECT MIN(scheduled_at) FROM {$table} WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		if ( ! $next ) {
			return;
		}
		$due = strtotime( $next . ' UTC' );
		self::schedule_retry( max( 5, (int) $due - time() ) );
	}

	/**
	 * Force an immediate queue processing run. Called when the search
	 * engine comes back online after an outage so pending items don't
	 * have to wait for the next scheduled interval.
	 */
	public static function add_wake_up() {
		global $wpdb;
		$table = $wpdb->prefix . 'wss_sync_queue';

		// Items that exhausted their retries during the outage get a new chance.
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'pending', priority = 0, scheduled_at = %s WHERE status = 'failed'", current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $remaining > 0 ) {
			self::schedule_retry( 5 );
			wss_log(
				sprintf(
					/* translators: %d: number of pending items */
					__( 'Sync queue: waking up to process %d pending items after engine recovery.', 'woo-smart-search' ),
					$remaining
				),
				'info'
			);
		}
	}

	/**
	 * Schedule a retry via the best available scheduler.
	 *
	 * @param int $delay Seconds to wait before retrying.
	 */
	private static function schedule_retry( int $delay ) {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			if ( ! as_has_scheduled_action( 'wss_process_sync_queue' ) ) {
				as_schedule_single_action( time() + $delay, 'wss_process_sync_queue', array(), 'woo-smart-search' );
			}
		} elseif ( ! wp_next_scheduled( 'wss_cron_process_queue' ) ) {
			wp_schedule_single_event( time() + $delay, 'wss_cron_process_queue' );
		}
	}
}
