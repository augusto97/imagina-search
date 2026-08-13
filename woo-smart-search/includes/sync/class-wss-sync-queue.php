<?php
/**
 * Sync Queue.
 *
 * Manages the product sync queue. Items are drained at the end of the request
 * that enqueued them (after the response is flushed), so changes made through
 * any path — the editor, the WooCommerce REST API, integrations, bulk/quick
 * edit or CSV import — reflect in search almost immediately. Action Scheduler /
 * WP-Cron remain as a fallback for anything not drained inline and for sites
 * where request-end processing is not desired.
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
	 * Delay in seconds before the Action Scheduler fallback processes items.
	 */
	const QUEUE_DELAY = 30;

	/**
	 * Maximum number of retries for failed items.
	 */
	const MAX_RETRIES = 3;

	/**
	 * Items to process per batch.
	 */
	const BATCH_SIZE = 50;

	/**
	 * Max seconds to spend draining the queue at request end (the rest falls
	 * back to Action Scheduler). Guards against a huge bulk import exhausting
	 * PHP's execution time.
	 */
	const SHUTDOWN_BUDGET = 20;

	/**
	 * Whether something was queued during this request.
	 *
	 * @var bool
	 */
	private static $queued_this_request = false;

	/**
	 * Whether the shutdown drain has been hooked for this request.
	 *
	 * @var bool
	 */
	private static $shutdown_hooked = false;

	/**
	 * Initialize queue processing.
	 */
	public function init() {
		add_action( 'wss_process_sync_queue', array( $this, 'process_queue' ) );
	}

	/**
	 * Add a product to the sync queue.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $action     Action: 'update' or 'delete'.
	 */
	public static function add( int $product_id, string $action = 'update' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wss_sync_queue';

		// Check if already queued.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE product_id = %d AND status = 'pending'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$product_id
			)
		);

		if ( $existing ) {
			// Update the action (delete takes priority).
			$wpdb->update(
				$table,
				array(
					'action'       => $action,
					'scheduled_at' => current_time( 'mysql' ),
				),
				array( 'id' => $existing ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'product_id'   => $product_id,
					'action'       => $action,
					'scheduled_at' => current_time( 'mysql' ),
					'status'       => 'pending',
				),
				array( '%d', '%s', '%s', '%s' )
			);
		}

		// Drain at the end of THIS request so integrations / bulk uploads show
		// up immediately instead of waiting for Action Scheduler / WP-Cron
		// (unreliable on low-traffic sites and headless API calls).
		self::$queued_this_request = true;
		if ( ! self::$shutdown_hooked ) {
			self::$shutdown_hooked = true;
			add_action( 'shutdown', array( __CLASS__, 'process_on_shutdown' ), 100 );
		}

		// Action Scheduler fallback (also covers anything the shutdown drain
		// could not finish within its time budget).
		if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( 'wss_process_sync_queue' ) ) {
			as_schedule_single_action( time() + self::QUEUE_DELAY, 'wss_process_sync_queue', array(), 'woo-smart-search' );
		}
	}

	/**
	 * Drain the queue at the end of the request that enqueued the changes.
	 *
	 * Flushes the response first so the editor / API caller is not kept
	 * waiting, then processes items inline until the queue is empty or the time
	 * budget is reached; the remainder falls back to Action Scheduler.
	 */
	public static function process_on_shutdown() {
		if ( ! self::$queued_this_request ) {
			return;
		}
		self::$queued_this_request = false;

		// WP-CLI bulk imports intentionally defer to the scheduler (they can
		// queue thousands and do their own batching); Action Scheduler / WP-Cron
		// runs already process the queue directly and must not recurse here.
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) {
			return;
		}

		// Send the response before doing the indexing work.
		$flushed = false;
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
			$flushed = true;
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
			$flushed = true;
		}

		$queue = new self();

		if ( $flushed ) {
			// Response already sent — drain until empty or the time budget runs
			// out, so bulk imports in one request settle without waiting on cron.
			$deadline = time() + self::SHUTDOWN_BUDGET;
			do {
				$processed = $queue->process_batch( self::BATCH_SIZE );
			} while ( $processed > 0 && time() < $deadline );
		} else {
			// Cannot flush the response (e.g. mod_php) — process a single batch
			// so the caller is not blocked for long; the rest falls back to
			// Action Scheduler.
			$queue->process_batch( self::BATCH_SIZE );
		}

		// Anything left over is handled by the Action Scheduler fallback.
		$queue->maybe_schedule_next();
	}

	/**
	 * Action Scheduler entry point: process one batch and reschedule if needed.
	 */
	public function process_queue() {
		$this->process_batch( self::BATCH_SIZE );
		$this->maybe_schedule_next();
	}

	/**
	 * Process up to $limit pending queue items.
	 *
	 * @param int $limit Maximum items to process.
	 * @return int Number of items processed in this batch (0 when the queue is
	 *             empty or the engine is unavailable).
	 */
	private function process_batch( int $limit ): int {
		global $wpdb;

		// Verify the engine is available before processing.
		$engine = wss_get_engine();

		if ( ! $engine ) {
			wss_log( __( 'Sync queue: search engine not available. Rescheduling.', 'woo-smart-search' ), 'warning' );
			$this->maybe_schedule_next( true );
			return 0;
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

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'pending' ORDER BY scheduled_at ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);

		if ( empty( $items ) ) {
			return 0;
		}

		foreach ( $items as $item ) {
			$post_id   = (int) $item['product_id'];
			$action    = $item['action'];
			$post_type = get_post_type( $post_id );

			$success = false;

			// Route to correct sync handler based on post type.
			$is_product = ( 'product' === $post_type ) && $product_sync;

			if ( 'delete' === $action ) {
				$success = $is_product
					? $product_sync->delete_single_product( $post_id )
					: ( $post_sync ? $post_sync->delete_single_post( $post_id ) : false );
			} else {
				$success = $is_product
					? $product_sync->sync_single_product( $post_id )
					: ( $post_sync ? $post_sync->sync_single_post( $post_id ) : false );
			}

			if ( $success ) {
				$wpdb->update(
					$table,
					array(
						'status'       => 'completed',
						'processed_at' => current_time( 'mysql' ),
					),
					array( 'id' => $item['id'] ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			} else {
				$retries = isset( $item['priority'] ) ? (int) $item['priority'] : 0;
				++$retries;

				if ( $retries < self::MAX_RETRIES ) {
					// Retry with exponential backoff: reschedule as pending.
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
					// Max retries exceeded — mark as permanently failed.
					$wpdb->update(
						$table,
						array(
							'status'       => 'failed',
							'priority'     => $retries,
							'processed_at' => current_time( 'mysql' ),
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

		// Clean old completed entries (older than 24 hours).
		// Keep failed entries for 7 days for visibility.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status = 'completed' AND processed_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status = 'failed' AND processed_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) )
			)
		);

		return count( $items );
	}

	/**
	 * Schedule the Action Scheduler fallback if pending items remain and one is
	 * not already scheduled.
	 *
	 * @param bool $force Schedule even if the pending count cannot be read.
	 */
	private function maybe_schedule_next( bool $force = false ) {
		global $wpdb;

		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( 'wss_process_sync_queue' ) ) {
			return;
		}

		if ( ! $force ) {
			$table     = $wpdb->prefix . 'wss_sync_queue';
			$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $remaining <= 0 ) {
				return;
			}
		}

		as_schedule_single_action( time() + 5, 'wss_process_sync_queue', array(), 'woo-smart-search' );
	}
}
