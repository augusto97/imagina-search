<?php
/**
 * Plugin activator.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Activator
 */
class WSS_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		self::maybe_create_results_page();

		flush_rewrite_rules();
		set_transient( 'wss_activation_redirect', true, 30 );
	}

	/**
	 * Create custom database tables.
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wss_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			type varchar(20) NOT NULL DEFAULT 'info',
			message text NOT NULL,
			context longtext,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY type (type),
			KEY created_at (created_at)
		) {$charset_collate};

		CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wss_sync_queue (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			product_id bigint(20) NOT NULL,
			action varchar(20) NOT NULL DEFAULT 'update',
			priority int(11) NOT NULL DEFAULT 10,
			scheduled_at datetime NOT NULL,
			processed_at datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			PRIMARY KEY (id),
			KEY product_id (product_id),
			KEY status (status),
			KEY scheduled_at (scheduled_at)
		) {$charset_collate};

		CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wss_search_log (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			query varchar(255) NOT NULL DEFAULT '',
			results_count int(11) NOT NULL DEFAULT 0,
			clicked_product_id bigint(20) UNSIGNED DEFAULT NULL,
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

		update_option( 'wss_db_version', WSS_VERSION );
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options() {
		$defaults = array(
			'host'                      => 'localhost',
			'port'                      => '',
			'protocol'                  => 'http',
			'api_key'                   => '',
			'search_api_key'            => '',
			'index_name'                => 'woo_products',
			'batch_size'                => 100,
			'index_out_of_stock'        => 'yes',
			'index_hidden'              => 'no',
			'exclude_categories'        => array(),
			'custom_fields'             => array(),
			'max_autocomplete_results'  => 8,
			'results_per_page'          => 20,
			'enable_facets'             => 'yes',
			'visible_facets'            => array( 'categories', 'price', 'stock', 'attributes' ),
			'search_by_sku'             => 'yes',
			'show_out_of_stock_results' => 'yes',
			'integration_mode'          => 'replace',
			'theme'                     => 'light',
			'primary_color'             => '#2563eb',
			'bg_color'                  => '#ffffff',
			'text_color'                => '#1f2937',
			'border_color'              => '#e5e7eb',
			'highlight_bg'              => '#fef3c7',
			'highlight_text'            => '#92400e',
			'font_size'                 => '14',
			'border_radius'             => '8',
			'show_image'                => 'yes',
			'show_price'                => 'yes',
			'show_category'             => 'yes',
			'show_sku'                  => 'no',
			'show_stock'                => 'yes',
			'show_rating'               => 'no',
			'show_sale_badge'           => 'yes',
			'placeholder_text'          => '',
			'custom_css'                => '',
			'cache_ttl'                 => 300,
			'rate_limit'                => 30,
			'synonyms'                  => '',
			'stop_words'                => '',
			'enable_analytics'          => 'yes',
			'results_page_id'           => 0,
		);

		$existing = get_option( 'wss_settings', array() );
		if ( empty( $existing ) ) {
			update_option( 'wss_settings', $defaults );
		}
	}

	/**
	 * Create the search results page if it doesn't exist yet.
	 *
	 * Works like WooCommerce's auto-created Cart/Checkout/My Account pages.
	 */
	private static function maybe_create_results_page() {
		$settings = get_option( 'wss_settings', array() );

		// Already configured.
		if ( ! empty( $settings['results_page_id'] ) && get_post_status( $settings['results_page_id'] ) ) {
			return;
		}

		// Check if a page with the shortcode already exists.
		$existing = get_posts(
			array(
				'post_type'      => 'page',
				's'              => '[woo_smart_search_results]',
				'posts_per_page' => 1,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing ) ) {
			$settings['results_page_id'] = $existing[0];
			update_option( 'wss_settings', $settings );
			return;
		}

		// Create the page.
		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Search Results', 'woo-smart-search' ),
				'post_content' => '[woo_smart_search_results]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_name'    => 'product-search-results',
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			$settings['results_page_id'] = $page_id;
			update_option( 'wss_settings', $settings );
		}
	}
}
