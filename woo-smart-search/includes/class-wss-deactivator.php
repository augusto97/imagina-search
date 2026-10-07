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
		// Unschedule every Action Scheduler task of the plugin (by group, so
		// hooks added in later versions are covered too).
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), 'woo-smart-search' );
			foreach ( array( 'wss_process_sync_queue', 'wss_bulk_sync_batch', 'wss_bulk_post_sync_batch', 'wss_health_check', 'wss_cleanup_search_logs', 'wss_periodic_reindex' ) as $hook ) {
				as_unschedule_all_actions( $hook );
			}
		}

		// WP-Cron fallbacks.
		foreach ( array( 'wss_cron_health_check', 'wss_cron_periodic_reindex', 'wss_cron_process_queue' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		// A sync "running" at deactivation must not resume on reactivation.
		delete_option( 'wss_sync_progress' );
		delete_option( 'wss_jobs_checked_at' );
		delete_option( 'wss_lock_sync_batch' );
		delete_option( 'wss_lock_queue' );

		// Clear transients.
		delete_transient( 'wss_activation_redirect' );
		delete_transient( 'wss_connection_error' );
		delete_transient( 'wss_health_email_sent' );

		flush_rewrite_rules();
	}
}
