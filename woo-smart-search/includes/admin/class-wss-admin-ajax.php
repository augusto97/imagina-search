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
		add_action( 'wp_ajax_wss_get_analytics', array( $this, 'get_analytics' ) );
		add_action( 'wp_ajax_wss_get_connection_status', array( $this, 'get_connection_status' ) );
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

		$required_cap = wss_is_woocommerce_active() ? 'manage_woocommerce' : 'manage_options';
		if ( ! current_user_can( $required_cap ) ) {
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
			'host', 'port', 'protocol', 'index_name',
			'search_api_key', 'theme', 'primary_color', 'bg_color',
			'text_color', 'border_color', 'font_size', 'border_radius',
			'placeholder_text', 'custom_css', 'integration_mode',
			'synonyms', 'stop_words', 'widget_layout', 'content_source',
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				if ( 'custom_css' === $field ) {
					// Preserve newlines but strip HTML tags and potential injections.
					$settings[ $field ] = wp_strip_all_tags( wp_unslash( $_POST[ $field ] ) );
				} elseif ( 'protocol' === $field ) {
					// Only allow http or https.
					$val = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
					$settings[ $field ] = in_array( $val, array( 'http', 'https' ), true ) ? $val : 'https';
				} elseif ( 'index_name' === $field ) {
					// Alphanumeric, dashes, and underscores only.
					$settings[ $field ] = preg_replace( '/[^a-zA-Z0-9_\-]/', '', sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
				} else {
					$settings[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				}
			}
		}

		// Handle API key encryption.
		if ( isset( $_POST['api_key'] ) ) {
			$raw_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );
			if ( ! empty( $raw_key ) ) {
				$settings['api_key'] = WSS_Meilisearch::encrypt_key( $raw_key );
			}
		}

		// Integer fields.
		$int_fields = array( 'batch_size', 'max_autocomplete_results', 'results_per_page', 'cache_ttl', 'rate_limit', 'results_page_id' );
		foreach ( $int_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$settings[ $field ] = absint( $_POST[ $field ] );
			}
		}

		// Yes/no fields — only process checkboxes when their form tab is submitted.
		// Each form has a hidden _wss_tab field to identify it.
		$submitted_tab = isset( $_POST['_wss_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['_wss_tab'] ) ) : '';

		$appearance_bools = array(
			'show_image', 'show_price', 'show_category',
			'show_sku', 'show_stock', 'show_rating',
			'show_sale_badge', 'show_excerpt', 'show_author',
			'show_date', 'show_post_type', 'enable_analytics',
		);

		$search_bools = array(
			'index_out_of_stock', 'index_hidden', 'enable_facets',
			'search_by_sku', 'show_out_of_stock_results',
		);

		$bool_fields = array();
		if ( 'appearance' === $submitted_tab ) {
			$bool_fields = $appearance_bools;
		} elseif ( 'search' === $submitted_tab ) {
			$bool_fields = $search_bools;
		}

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
		} elseif ( 'search' === $submitted_tab ) {
			// Only reset visible_facets when the Results Page tab is submitted with none checked.
			$settings['visible_facets'] = array();
		}

		// Content source settings.
		$content_source_changed = false;
		if ( isset( $_POST['content_source'] ) ) {
			$source = sanitize_text_field( wp_unslash( $_POST['content_source'] ) );
			if ( in_array( $source, array( 'auto', 'woocommerce', 'wordpress', 'mixed' ), true ) ) {
				$old_source = $settings['content_source'] ?? 'auto';
				if ( $old_source !== $source ) {
					$content_source_changed = true;
				}
				$settings['content_source'] = $source;
			}
		}

		if ( isset( $_POST['wp_post_types'] ) && is_array( $_POST['wp_post_types'] ) ) {
			$settings['wp_post_types'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['wp_post_types'] ) );
		} elseif ( 'content_sources' === $submitted_tab ) {
			$settings['wp_post_types'] = array( 'post' );
		}

		if ( isset( $_POST['wp_custom_fields'] ) && is_array( $_POST['wp_custom_fields'] ) ) {
			$settings['wp_custom_fields'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['wp_custom_fields'] ) );
		} elseif ( 'content_sources' === $submitted_tab ) {
			$settings['wp_custom_fields'] = array();
		}

		update_option( 'wss_settings', $settings );

		// Invalidate cached CSS variables.
		delete_transient( 'wss_css_vars_' . WSS_VERSION );

		// Reset Meilisearch singleton so it picks up new config.
		WSS_Meilisearch::reset();

		// Update Meilisearch filterable attributes (only in ecommerce mode).
		if ( wss_is_ecommerce_mode() ) {
			WSS_Product_Sync::update_filterable_attributes();
		}

		// Auto-clear index when content source changes (old documents would pollute results).
		if ( $content_source_changed ) {
			$engine = WSS_Meilisearch::get_instance();
			if ( $engine ) {
				$index_name = $settings['index_name'] ?? 'woo_products';
				$engine->delete_all_documents( $index_name );
				wss_log( __( 'Index cleared automatically due to content source change. Please run a Full Sync.', 'woo-smart-search' ), 'info' );
			}
		}

		wss_log( __( 'Settings updated', 'woo-smart-search' ), 'info' );

		$message = __( 'Settings saved successfully.', 'woo-smart-search' );
		if ( $content_source_changed ) {
			$message .= ' ' . __( 'Content source changed — index has been cleared. Please run a Full Sync from the Indexing tab.', 'woo-smart-search' );
		}

		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * Test the Meilisearch connection.
	 */
	public function test_connection() {
		$this->verify_request();

		// Use form values, but fall back to saved API key when field is empty
		// (the password field is always rendered empty for security).
		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		if ( empty( $api_key ) ) {
			$saved_key = wss_get_option( 'api_key', '' );
			$api_key   = ! empty( $saved_key ) ? WSS_Meilisearch::decrypt_key( $saved_key ) : '';
		}

		$config = array(
			'host'     => isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '',
			'port'     => isset( $_POST['port'] ) ? sanitize_text_field( wp_unslash( $_POST['port'] ) ) : '',
			'protocol' => isset( $_POST['protocol'] ) ? sanitize_text_field( wp_unslash( $_POST['protocol'] ) ) : 'http',
			'api_key'  => $api_key,
		);

		$engine = WSS_Meilisearch::create( $config );
		if ( ! $engine ) {
			wp_send_json_error( array( 'message' => __( 'Could not create Meilisearch instance.', 'woo-smart-search' ) ) );
			return;
		}

		$result = $engine->test_connection();

		if ( $result['success'] ) {
			do_action( 'wss_connection_established', 'meilisearch' );
			wss_log(
				sprintf(
					/* translators: %s: Meilisearch version */
					__( 'Connection test successful: Meilisearch v%s', 'woo-smart-search' ),
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

		// Always clean up stale flag from previous sync attempts.
		delete_option( 'wss_skip_index_configure' );

		$content_source = wss_get_content_source();

		if ( 'mixed' === $content_source ) {
			// Mixed mode: configure index with combined attributes, then sync both.
			$engine     = wss_get_engine();
			$index_name = wss_get_option( 'index_name', 'woo_products' );

			if ( $engine ) {
				$engine->create_index( $index_name );
				$this->configure_mixed_index( $engine, $index_name );
			}

			// Flag to prevent start_full_sync from overwriting the combined config.
			update_option( 'wss_skip_index_configure', true, false );

			$results = array();

			if ( wss_is_woocommerce_active() ) {
				$product_sync = new WSS_Product_Sync();
				$results[] = $product_sync->start_full_sync();
			}

			$post_sync = new WSS_Post_Sync();
			$results[] = $post_sync->start_full_sync();

			delete_option( 'wss_skip_index_configure' );

			$total = 0;
			$messages = array();
			foreach ( $results as $r ) {
				$total += $r['total'] ?? 0;
				$messages[] = $r['message'] ?? '';
			}

			wp_send_json_success( array(
				'success' => true,
				'message' => implode( ' | ', array_filter( $messages ) ),
				'total'   => $total,
			) );
		} elseif ( wss_is_ecommerce_mode() ) {
			$sync = new WSS_Product_Sync();
		} else {
			$sync = new WSS_Post_Sync();
		}

		if ( isset( $sync ) ) {
			$result = $sync->start_full_sync();

			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( $result );
			}
		}
	}

	/**
	 * Configure a mixed-mode index with merged attributes from both product and post sync.
	 *
	 * @param WSS_Meilisearch $engine     The Meilisearch engine instance.
	 * @param string          $index_name The index name.
	 */
	private function configure_mixed_index( $engine, $index_name ) {
		// Product searchable attributes.
		$product_searchable = apply_filters(
			'wss_searchable_attributes',
			array( 'name', 'sku', 'all_skus', 'categories', 'tags', 'brand', 'description', 'attributes_text', 'variations_text' )
		);

		// Post searchable attributes.
		$post_searchable = apply_filters(
			'wss_wp_searchable_attributes',
			array( 'name', 'description', 'full_description', 'categories', 'tags', 'taxonomies_text', 'author' )
		);

		$searchable = array_values( array_unique( array_merge( $product_searchable, $post_searchable ) ) );

		// Product filterable attributes.
		$product_filterable = apply_filters(
			'wss_filterable_attributes',
			array(
				'categories', 'category_ids', 'category_slugs', 'tags',
				'price', 'price_min', 'price_max', 'stock_status', 'on_sale',
				'featured', 'rating', 'brand', 'type', 'content_source',
			)
		);

		// Dynamically add WC product attributes as filterable.
		if ( wss_is_woocommerce_active() && function_exists( 'wc_get_attribute_taxonomies' ) ) {
			$attribute_taxonomies = wc_get_attribute_taxonomies();
			$attribute_names      = array();
			if ( ! empty( $attribute_taxonomies ) ) {
				foreach ( $attribute_taxonomies as $tax ) {
					$label               = $tax->attribute_label ? $tax->attribute_label : $tax->attribute_name;
					$product_filterable[] = 'attributes.' . $label;
					$attribute_names[]    = $label;
				}
			}
			update_option( 'wss_product_attribute_names', $attribute_names, true );
		}

		// Post filterable attributes.
		$post_filterable = apply_filters(
			'wss_wp_filterable_attributes',
			array( 'categories', 'category_ids', 'category_slugs', 'tags', 'post_type', 'author', 'content_source' )
		);

		$filterable = array_values( array_unique( array_merge( $product_filterable, $post_filterable ) ) );

		// Sortable: merge both sets.
		$product_sortable = array( 'price', 'price_min', 'price_max', 'date_created', 'date_modified', 'name', 'rating', 'total_sales', 'menu_order' );
		$post_sortable    = array( 'date_created', 'date_modified', 'name', 'menu_order', 'comment_count' );
		$sortable         = array_values( array_unique( array_merge( $product_sortable, $post_sortable ) ) );

		// Displayed: merge both sets.
		$product_displayed = apply_filters(
			'wss_displayed_attributes',
			array(
				'id', 'name', 'slug', 'description', 'sku', 'permalink',
				'image', 'gallery',
				'price', 'regular_price', 'sale_price', 'price_min', 'price_max',
				'on_sale', 'currency',
				'stock_status',
				'categories', 'category_slugs',
				'tags', 'brand',
				'attributes',
				'rating', 'review_count',
				'type',
				'content_source',
			)
		);

		$post_displayed = apply_filters(
			'wss_wp_displayed_attributes',
			array(
				'id', 'name', 'slug', 'description', 'permalink',
				'image', 'post_type',
				'categories', 'category_slugs',
				'tags', 'taxonomies',
				'author', 'date_created',
				'comment_count', 'content_source',
			)
		);

		$displayed = array_values( array_unique( array_merge( $product_displayed, $post_displayed ) ) );

		// Apply combined settings.
		$settings = apply_filters(
			'wss_index_settings',
			array(
				'searchableAttributes' => $searchable,
				'filterableAttributes' => $filterable,
				'sortableAttributes'   => $sortable,
				'displayedAttributes'  => $displayed,
			)
		);

		$engine->configure_index( $index_name, $settings );

		// Configure synonyms if set.
		$synonyms = wss_get_option( 'synonyms', '' );
		if ( ! empty( $synonyms ) ) {
			$synonyms_array = json_decode( $synonyms, true );
			if ( is_array( $synonyms_array ) ) {
				$engine->set_synonyms( $index_name, $synonyms_array );
			}
		}

		// Configure stop words if set.
		$stop_words = wss_get_option( 'stop_words', '' );
		if ( ! empty( $stop_words ) ) {
			$stop_words_array = array_map( 'trim', explode( ',', $stop_words ) );
			$engine->set_stop_words( $index_name, $stop_words_array );
		}
	}

	/**
	 * Get sync progress.
	 */
	public function sync_progress() {
		$this->verify_request();

		$progress = get_option( 'wss_sync_progress' );

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

		$engine = WSS_Meilisearch::get_instance();
		if ( ! $engine ) {
			wp_send_json_error( array( 'message' => __( 'Meilisearch is not configured.', 'woo-smart-search' ) ) );
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

		$table  = $wpdb->prefix . 'wss_logs';
		$type   = isset( $_POST['log_type'] ) ? sanitize_text_field( wp_unslash( $_POST['log_type'] ) ) : '';
		$page   = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$limit  = 50;
		$offset = ( $page - 1 ) * $limit;

		$where  = '1=1';
		$params = array();

		if ( ! empty( $type ) ) {
			$where   .= ' AND type = %s';
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

		$csv_rows   = array();
		$csv_rows[] = array( 'Type', 'Message', 'Context', 'Date' );
		foreach ( $logs as $log ) {
			$csv_rows[] = array( $log['type'], $log['message'], $log['context'], $log['created_at'] );
		}

		$csv = '';
		foreach ( $csv_rows as $row ) {
			$csv .= '"' . implode( '","', array_map( function ( $v ) { return str_replace( '"', '""', $v ); }, $row ) ) . "\"\n";
		}

		wp_send_json_success( array( 'csv' => $csv ) );
	}

	/**
	 * Get index statistics.
	 */
	public function get_index_stats() {
		$this->verify_request();

		$engine = WSS_Meilisearch::get_instance();
		if ( ! $engine ) {
			wp_send_json_error( array( 'message' => __( 'Meilisearch is not configured.', 'woo-smart-search' ) ) );
			return;
		}

		$index_name = wss_get_option( 'index_name', 'woo_products' );
		$stats      = $engine->get_index_stats( $index_name );

		$stats['last_sync'] = wss_get_option( 'last_sync', 0 );

		wp_send_json_success( $stats );
	}

	/**
	 * Get search analytics data.
	 *
	 * Returns top queries, zero-result queries, search volume, and CTR.
	 */
	public function get_analytics() {
		$this->verify_request();

		$analytics = new WSS_Search_Analytics();
		$period    = isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : 'week';

		$top_queries         = $analytics->get_top_queries( 20 );
		$zero_result_queries = $analytics->get_zero_result_queries( 20 );
		$search_volume       = $analytics->get_search_volume( $period );
		$ctr                 = $analytics->get_click_through_rate();

		// Calculate totals for today, week, and month.
		$volume_today = $analytics->get_search_volume( 'today' );
		$volume_week  = $analytics->get_search_volume( 'week' );
		$volume_month = $analytics->get_search_volume( 'month' );

		$total_today = 0;
		foreach ( $volume_today as $day ) {
			$total_today += (int) $day->count;
		}

		$total_week = 0;
		foreach ( $volume_week as $day ) {
			$total_week += (int) $day->count;
		}

		$total_month = 0;
		foreach ( $volume_month as $day ) {
			$total_month += (int) $day->count;
		}

		wp_send_json_success(
			array(
				'top_queries'         => $top_queries,
				'zero_result_queries' => $zero_result_queries,
				'search_volume'       => $search_volume,
				'click_through_rate'  => $ctr,
				'totals'              => array(
					'today' => $total_today,
					'week'  => $total_week,
					'month' => $total_month,
				),
			)
		);
	}

	/**
	 * Get the current Meilisearch connection status.
	 *
	 * Returns status (connected, error, not_configured), version, and document count.
	 */
	public function get_connection_status() {
		$this->verify_request();

		$error = get_transient( 'wss_connection_error' );
		if ( $error ) {
			wp_send_json_success(
				array(
					'status'  => 'error',
					'message' => $error,
				)
			);
			return;
		}

		$engine = WSS_Meilisearch::get_instance();
		if ( ! $engine ) {
			wp_send_json_success(
				array(
					'status'  => 'not_configured',
					'message' => __( 'Meilisearch is not configured.', 'woo-smart-search' ),
				)
			);
			return;
		}

		$result = $engine->test_connection();
		if ( ! $result['success'] ) {
			wp_send_json_success(
				array(
					'status'  => 'error',
					'message' => $result['message'],
				)
			);
			return;
		}

		// Get document count.
		$index_name = wss_get_option( 'index_name', 'woo_products' );
		$stats      = $engine->get_index_stats( $index_name );
		$doc_count  = isset( $stats['numberOfDocuments'] ) ? (int) $stats['numberOfDocuments'] : 0;

		wp_send_json_success(
			array(
				'status'    => 'connected',
				'version'   => isset( $result['version'] ) ? $result['version'] : '',
				'documents' => $doc_count,
			)
		);
	}
}
