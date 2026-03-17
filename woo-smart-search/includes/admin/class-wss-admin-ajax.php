<?php
/**
 * Admin AJAX handlers.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Admin_Ajax
 */
class WSS_Admin_Ajax {

	/**
	 * Initialize AJAX hooks.
	 */
	public function init() {
		add_action( 'wp_ajax_wss_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_wss_test_connection', array( $this, 'test_connection' ) );
		add_action( 'wp_ajax_wss_full_sync', array( $this, 'full_sync' ) );
		add_action( 'wp_ajax_wss_sync_progress', array( $this, 'sync_progress' ) );
		add_action( 'wp_ajax_wss_clear_index', array( $this, 'clear_index' ) );
		add_action( 'wp_ajax_wss_get_logs', array( $this, 'get_logs' ) );
		add_action( 'wp_ajax_wss_clear_logs', array( $this, 'clear_logs' ) );
		add_action( 'wp_ajax_wss_export_logs', array( $this, 'export_logs' ) );
		add_action( 'wp_ajax_wss_get_index_stats', array( $this, 'get_index_stats' ) );
	}

	/**
	 * Verify the AJAX nonce and permissions.
	 *
	 * @return bool
	 */
	private function verify_request(): bool {
		if ( ! check_ajax_referer( 'wss_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'woo-smart-search' ) ) );
			return false;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'woo-smart-search' ) ) );
			return false;
		}

		return true;
	}

	/**
	 * Save plugin settings.
	 */
	public function save_settings() {
		$this->verify_request();

		$settings = get_option( 'wss_settings', array() );

		// Connection tab fields.
		$text_fields = array(
			'engine', 'host', 'port', 'protocol', 'index_name',
			'search_api_key', 'theme', 'primary_color', 'bg_color',
			'text_color', 'border_color', 'font_size', 'border_radius',
			'placeholder_text', 'custom_css', 'integration_mode',
			'synonyms', 'stop_words',
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$settings[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			}
		}

		// Handle API key encryption.
		if ( isset( $_POST['api_key'] ) ) {
			$raw_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );
			if ( ! empty( $raw_key ) ) {
				$settings['api_key'] = WSS_Engine_Factory::encrypt_key( $raw_key );
			}
		}

		// Integer fields.
		$int_fields = array( 'batch_size', 'max_autocomplete_results', 'results_per_page', 'cache_ttl', 'rate_limit' );
		foreach ( $int_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$settings[ $field ] = absint( $_POST[ $field ] );
			}
		}

		// Yes/no fields.
		$bool_fields = array(
			'index_out_of_stock', 'index_hidden', 'enable_facets',
			'search_by_sku', 'show_out_of_stock_results',
			'show_image', 'show_price', 'show_category',
			'show_sku', 'show_stock', 'show_rating',
		);
		foreach ( $bool_fields as $field ) {
			$settings[ $field ] = isset( $_POST[ $field ] ) ? 'yes' : 'no';
		}

		// Array fields.
		if ( isset( $_POST['exclude_categories'] ) && is_array( $_POST['exclude_categories'] ) ) {
			$settings['exclude_categories'] = array_map( 'absint', $_POST['exclude_categories'] );
		} else {
			$settings['exclude_categories'] = array();
		}

		if ( isset( $_POST['custom_fields'] ) && is_array( $_POST['custom_fields'] ) ) {
			$settings['custom_fields'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['custom_fields'] ) );
		} else {
			$settings['custom_fields'] = array();
		}

		if ( isset( $_POST['visible_facets'] ) && is_array( $_POST['visible_facets'] ) ) {
			$settings['visible_facets'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['visible_facets'] ) );
		} else {
			$settings['visible_facets'] = array();
		}

		update_option( 'wss_settings', $settings );

		// Reset engine instance.
		WSS_Engine_Factory::reset();

		wss_log( __( 'Settings updated', 'woo-smart-search' ), 'info' );

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'woo-smart-search' ) ) );
	}

	/**
	 * Test the search engine connection.
	 */
	public function test_connection() {
		$this->verify_request();

		$engine_type = isset( $_POST['engine'] ) ? sanitize_text_field( wp_unslash( $_POST['engine'] ) ) : 'meilisearch';
		$config      = array(
			'host'     => isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '',
			'port'     => isset( $_POST['port'] ) ? sanitize_text_field( wp_unslash( $_POST['port'] ) ) : '',
			'protocol' => isset( $_POST['protocol'] ) ? sanitize_text_field( wp_unslash( $_POST['protocol'] ) ) : 'http',
			'api_key'  => isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '',
		);

		$engine = WSS_Engine_Factory::create( $engine_type, $config );
		if ( ! $engine ) {
			wp_send_json_error( array( 'message' => __( 'Invalid engine type.', 'woo-smart-search' ) ) );
			return;
		}

		$result = $engine->test_connection();

		if ( $result['success'] ) {
			do_action( 'wss_connection_established', $engine_type );
			wss_log(
				sprintf(
					/* translators: 1: engine type 2: engine version */
					__( 'Connection test successful: %1$s v%2$s', 'woo-smart-search' ),
					$engine_type,
					$result['version']
				),
				'info'
			);
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Start full synchronization.
	 */
	public function full_sync() {
		$this->verify_request();

		$sync   = new WSS_Product_Sync();
		$result = $sync->start_full_sync();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Get sync progress.
	 */
	public function sync_progress() {
		$this->verify_request();

		$progress = get_transient( 'wss_sync_progress' );

		if ( ! $progress ) {
			wp_send_json_success(
				array(
					'status'    => 'idle',
					'total'     => 0,
					'processed' => 0,
				)
			);
			return;
		}

		wp_send_json_success( $progress );
	}

	/**
	 * Clear the search index.
	 */
	public function clear_index() {
		$this->verify_request();

		$engine = wss_get_engine();
		if ( ! $engine ) {
			wp_send_json_error( array( 'message' => __( 'Search engine not configured.', 'woo-smart-search' ) ) );
			return;
		}

		$index_name = wss_get_option( 'index_name', 'woo_products' );
		$result     = $engine->delete_all_documents( $index_name );

		if ( $result ) {
			wss_log( __( 'Index cleared', 'woo-smart-search' ), 'info' );
			wp_send_json_success( array( 'message' => __( 'Index cleared successfully.', 'woo-smart-search' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to clear index.', 'woo-smart-search' ) ) );
		}
	}

	/**
	 * Get log entries.
	 */
	public function get_logs() {
		$this->verify_request();

		global $wpdb;

		$table = $wpdb->prefix . 'wss_logs';
		$type  = isset( $_POST['log_type'] ) ? sanitize_text_field( wp_unslash( $_POST['log_type'] ) ) : '';
		$page  = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$limit = 50;
		$offset = ( $page - 1 ) * $limit;

		$where = '1=1';
		$params = array();

		if ( ! empty( $type ) ) {
			$where .= ' AND type = %s';
			$params[] = $type;
		}

		if ( ! empty( $params ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$logs  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", array_merge( $params, array( $limit, $offset ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$logs  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		wp_send_json_success(
			array(
				'logs'  => $logs,
				'total' => $total,
				'pages' => ceil( $total / $limit ),
				'page'  => $page,
			)
		);
	}

	/**
	 * Clear all logs.
	 */
	public function clear_logs() {
		$this->verify_request();

		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wss_logs" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		wp_send_json_success( array( 'message' => __( 'Logs cleared.', 'woo-smart-search' ) ) );
	}

	/**
	 * Export logs as CSV.
	 */
	public function export_logs() {
		$this->verify_request();

		global $wpdb;

		$table = $wpdb->prefix . 'wss_logs';
		$logs  = $wpdb->get_results( "SELECT type, message, context, created_at FROM {$table} ORDER BY created_at DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$csv_rows = array();
		$csv_rows[] = array( 'Type', 'Message', 'Context', 'Date' );
		foreach ( $logs as $log ) {
			$csv_rows[] = array( $log['type'], $log['message'], $log['context'], $log['created_at'] );
		}

		$csv = '';
		foreach ( $csv_rows as $row ) {
			$csv .= '"' . implode( '","', array_map( function( $v ) { return str_replace( '"', '""', $v ); }, $row ) ) . "\"\n";
		}

		wp_send_json_success( array( 'csv' => $csv ) );
	}

	/**
	 * Get index statistics.
	 */
	public function get_index_stats() {
		$this->verify_request();

		$engine = wss_get_engine();
		if ( ! $engine ) {
			wp_send_json_error( array( 'message' => __( 'Search engine not configured.', 'woo-smart-search' ) ) );
			return;
		}

		$index_name = wss_get_option( 'index_name', 'woo_products' );
		$stats      = $engine->get_index_stats( $index_name );

		$stats['last_sync'] = wss_get_option( 'last_sync', 0 );

		wp_send_json_success( $stats );
	}
}
