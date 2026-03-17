<?php
/**
 * Plugin deactivator.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Deactivator
 */
class WSS_Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		// Unschedule all Action Scheduler tasks.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'wss_process_sync_queue' );
			as_unschedule_all_actions( 'wss_bulk_sync_batch' );
			as_unschedule_all_actions( 'wss_health_check' );
		}

		// Clear transients.
		delete_transient( 'wss_sync_progress' );
		delete_transient( 'wss_activation_redirect' );

		flush_rewrite_rules();
	}
}
