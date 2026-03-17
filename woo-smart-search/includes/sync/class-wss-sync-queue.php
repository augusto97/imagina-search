<?php
/**
 * Sync Queue.
 *
 * Manages the product sync queue using Action Scheduler.
 * Uses WSS_Meilisearch via wss_get_engine() for engine access.
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

		// Schedule queue processing.
		if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( 'wss_process_sync_queue' ) ) {
			as_schedule_single_action( time() + self::QUEUE_DELAY, 'wss_process_sync_queue', array(), 'woo-smart-search' );
		}
	}

	/**
	 * Process the sync queue.
	 *
	 * Uses WSS_Meilisearch via wss_get_engine() for engine access.
	 */
	public function process_queue() {
		global $wpdb;

		// Verify the engine is available before processing.
		$engine = wss_get_engine();

		if ( ! $engine ) {
			wss_log( __( 'Sync queue: Meilisearch engine not available. Rescheduling.', 'woo-smart-search' ), 'warning' );

			// Reschedule so items are not lost.
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time() + self::QUEUE_DELAY, 'wss_process_sync_queue', array(), 'woo-smart-search' );
			}

			return;
		}

		$table = $wpdb->prefix . 'wss_sync_queue';
		$sync  = new WSS_Product_Sync();

		// Get pending items (max 50 at a time).
		$items = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE status = 'pending' ORDER BY scheduled_at ASC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( empty( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			$product_id = (int) $item['product_id'];
			$action     = $item['action'];

			$success = false;

			if ( 'delete' === $action ) {
				$success = $sync->delete_single_product( $product_id );
			} else {
				$success = $sync->sync_single_product( $product_id );
			}

			$wpdb->update(
				$table,
				array(
					'status'       => $success ? 'completed' : 'failed',
					'processed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $item['id'] ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		// Clean old completed entries (older than 24 hours).
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status IN ('completed', 'failed') AND processed_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
			)
		);

		// If more pending items remain, schedule another run.
		$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $remaining > 0 && function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + 5, 'wss_process_sync_queue', array(), 'woo-smart-search' );
		}
	}
}
