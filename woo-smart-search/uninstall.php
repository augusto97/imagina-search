<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'wss_settings' );
delete_option( 'wss_db_version' );

// Drop custom tables.
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wss_logs" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wss_sync_queue" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Clear all transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wss_%' OR option_name LIKE '_transient_timeout_wss_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Unschedule actions.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'wss_process_sync_queue' );
	as_unschedule_all_actions( 'wss_bulk_sync_batch' );
	as_unschedule_all_actions( 'wss_health_check' );
}
