<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete all plugin options (covers wss_settings, wss_db_version, sync state,
// local engine settings, periodic reindex timestamps, attribute names, etc.).
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wss\\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

// Drop custom tables (logs, queue, analytics + local engine index tables).
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wss_logs" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wss_sync_queue" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wss_search_log" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wss_index_documents" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wss_index_terms" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wss_index_postings" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

// Clear all transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wss_%' OR option_name LIKE '_transient_timeout_wss_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

// Unschedule ALL recurring/queued actions so Action Scheduler doesn't keep
// firing hooks for code that no longer exists.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'wss_process_sync_queue' );
	as_unschedule_all_actions( 'wss_bulk_sync_batch' );
	as_unschedule_all_actions( 'wss_bulk_post_sync_batch' );
	as_unschedule_all_actions( 'wss_periodic_reindex' );
	as_unschedule_all_actions( 'wss_health_check' );
	as_unschedule_all_actions( 'wss_cleanup_search_logs' );
}
