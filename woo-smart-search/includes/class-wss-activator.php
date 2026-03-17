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

		// Flush rewrite rules for REST API.
		flush_rewrite_rules();

		// Set activation flag for redirect.
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
			'engine'                    => 'meilisearch',
			'host'                      => 'http://localhost',
			'port'                      => '7700',
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
			'primary_color'             => '#2271b1',
			'bg_color'                  => '#ffffff',
			'text_color'                => '#1d2327',
			'border_color'              => '#c3c4c7',
			'font_size'                 => '14',
			'border_radius'             => '4',
			'show_image'                => 'yes',
			'show_price'                => 'yes',
			'show_category'             => 'yes',
			'show_sku'                  => 'no',
			'show_stock'                => 'yes',
			'show_rating'               => 'no',
			'placeholder_text'          => '',
			'custom_css'                => '',
			'cache_ttl'                 => 300,
			'rate_limit'                => 30,
			'synonyms'                  => '',
			'stop_words'                => '',
		);

		$existing = get_option( 'wss_settings', array() );
		if ( empty( $existing ) ) {
			update_option( 'wss_settings', $defaults );
		}
	}
}
