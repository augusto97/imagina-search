<?php
/**
 * Search analytics tracking and reporting.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Search_Analytics
 *
 * Logs search queries and clicks, and provides reporting methods
 * for top queries, zero-result queries, search volume, and CTR.
 */
class WSS_Search_Analytics {

	/**
	 * Database table name (without prefix).
	 *
	 * @var string
	 */
	const TABLE_NAME = 'wss_search_log';

	/**
	 * Initialize hooks.
	 */
	public function init() {
		// Schedule daily cleanup of old logs.
		if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( 'wss_cleanup_search_logs' ) ) {
			as_schedule_recurring_action( time() + DAY_IN_SECONDS, DAY_IN_SECONDS, 'wss_cleanup_search_logs', array(), 'woo-smart-search' );
		}
		add_action( 'wss_cleanup_search_logs', array( $this, 'cleanup_old_logs' ) );
	}

	/**
	 * Get the full table name with prefix.
	 *
	 * @return string
	 */
	private static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create the search log table.
	 *
	 * Should be called on plugin activation.
	 */
	public static function create_table() {
		global $wpdb;

		$table           = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			query varchar(255) NOT NULL DEFAULT '',
			results_count int(11) NOT NULL DEFAULT 0,
			clicked_product_id bigint(20) unsigned DEFAULT NULL,
			ip_address varchar(45) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_query (query(191)),
			KEY idx_created_at (created_at),
			KEY idx_results_count (results_count)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Ensure the search log table exists, creating it if needed.
	 */
	private static function maybe_create_table() {
		static $checked = false;
		if ( $checked ) {
			return;
		}
		$checked = true;

		global $wpdb;
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			self::create_table();
		}
	}

	/**
	 * Log a search query.
	 *
	 * @param string $query         The search query string.
	 * @param int    $results_count Number of results returned.
	 * @param string $ip            IP address of the searcher.
	 * @param string $user_agent    Browser user agent string.
	 * @return int|false The row ID on success, false on failure.
	 */
	public function log_search( $query, $results_count, $ip = '', $user_agent = '' ) {
		global $wpdb;

		self::maybe_create_table();
		$table = self::get_table_name();

		$inserted = $wpdb->insert(
			$table,
			array(
				'query'         => sanitize_text_field( $query ),
				'results_count' => absint( $results_count ),
				'ip_address'    => sanitize_text_field( $ip ),
				'user_agent'    => sanitize_text_field( substr( $user_agent, 0, 255 ) ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);

		if ( $inserted ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Log a click on a search result.
	 *
	 * Updates the most recent search log entry matching the query
	 * to record which product was clicked.
	 *
	 * @param string $query      The search query that led to the click.
	 * @param int    $product_id The product ID that was clicked.
	 * @return bool True on success, false on failure.
	 */
	public function log_click( $query, $product_id ) {
		global $wpdb;

		$table = self::get_table_name();

		// Find the most recent log entry for this query without a click.
		$log_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE query = %s AND clicked_product_id IS NULL ORDER BY created_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				sanitize_text_field( $query )
			)
		);

		if ( ! $log_id ) {
			return false;
		}

		$updated = $wpdb->update(
			$table,
			array( 'clicked_product_id' => absint( $product_id ) ),
			array( 'id' => $log_id ),
			array( '%d' ),
			array( '%d' )
		);

		return (bool) $updated;
	}

	/**
	 * Get the top search queries ordered by frequency.
	 *
	 * @param int $limit Maximum number of results to return.
	 * @return array Array of objects with query, count, and last_searched properties.
	 */
	public function get_top_queries( $limit = 20 ) {
		global $wpdb;

		$table = self::get_table_name();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT query, COUNT(*) AS count, MAX(created_at) AS last_searched
				FROM {$table}
				WHERE query != ''
				GROUP BY query
				ORDER BY count DESC
				LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $limit )
			)
		);
	}

	/**
	 * Get search queries that returned zero results.
	 *
	 * @param int $limit Maximum number of results to return.
	 * @return array Array of objects with query, count, and last_searched properties.
	 */
	public function get_zero_result_queries( $limit = 20 ) {
		global $wpdb;

		$table = self::get_table_name();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT query, COUNT(*) AS count, MAX(created_at) AS last_searched
				FROM {$table}
				WHERE results_count = 0 AND query != ''
				GROUP BY query
				ORDER BY count DESC
				LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $limit )
			)
		);
	}

	/**
	 * Get search volume statistics grouped by day.
	 *
	 * @param string $period One of 'today', 'week', or 'month'.
	 * @return array Array of objects with date and count properties.
	 */
	public function get_search_volume( $period = 'week' ) {
		global $wpdb;

		$table = self::get_table_name();

		switch ( $period ) {
			case 'today':
				$date_from = gmdate( 'Y-m-d 00:00:00' );
				break;
			case 'month':
				$date_from = gmdate( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );
				break;
			case 'week':
			default:
				$date_from = gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );
				break;
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS date, COUNT(*) AS count
				FROM {$table}
				WHERE created_at >= %s
				GROUP BY DATE(created_at)
				ORDER BY date ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$date_from
			)
		);
	}

	/**
	 * Get the click-through rate as a percentage.
	 *
	 * Calculates the ratio of searches that resulted in a product click
	 * versus total searches over the last 30 days.
	 *
	 * @return float The CTR as a percentage (0-100).
	 */
	public function get_click_through_rate() {
		global $wpdb;

		$table     = self::get_table_name();
		$date_from = gmdate( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$date_from
			)
		);

		if ( 0 === $total ) {
			return 0.0;
		}

		$clicked = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE clicked_product_id IS NOT NULL AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$date_from
			)
		);

		return round( ( $clicked / $total ) * 100, 2 );
	}

	/**
	 * Delete search log entries older than the specified number of days.
	 *
	 * @param int $days Number of days to retain. Default 90.
	 * @return int Number of rows deleted.
	 */
	public function cleanup_old_logs( $days = 90 ) {
		global $wpdb;

		$table    = self::get_table_name();
		$cutoff   = gmdate( 'Y-m-d 00:00:00', strtotime( '-' . absint( $days ) . ' days' ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff
			)
		);

		if ( $deleted > 0 ) {
			wss_log(
				sprintf(
					/* translators: %d: number of deleted rows */
					__( 'Cleaned up %d old search log entries.', 'woo-smart-search' ),
					$deleted
				),
				'info'
			);
		}

		return (int) $deleted;
	}
}
