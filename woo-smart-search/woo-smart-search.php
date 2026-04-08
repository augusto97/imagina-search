<?php
/**
 * Plugin Name:       Woo Smart Search
 * Plugin URI:        https://example.com/woo-smart-search
 * Description:       Ultra-fast search powered by Meilisearch for WooCommerce products, blog posts, pages, and custom post types.
 * Version:           5.2.0
 * Author:            Imagina
 * Author URI:        https://example.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woo-smart-search
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   8.5
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WSS_VERSION', '5.2.0' );
define( 'WSS_PLUGIN_FILE', __FILE__ );
define( 'WSS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WSS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WSS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active.
 *
 * @return bool
 */
function wss_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Get the active content source mode.
 *
 * @return string 'woocommerce' or 'wordpress'
 */
function wss_get_content_source() {
	$source = wss_get_option( 'content_source', 'auto' );
	if ( 'auto' === $source ) {
		return wss_is_woocommerce_active() ? 'woocommerce' : 'wordpress';
	}
	// Mixed mode requires WooCommerce for the product part.
	if ( 'mixed' === $source && ! wss_is_woocommerce_active() ) {
		return 'wordpress';
	}
	return $source;
}

/**
 * Check if current content source is WooCommerce products.
 *
 * @return bool
 */
function wss_is_ecommerce_mode() {
	return 'woocommerce' === wss_get_content_source() && wss_is_woocommerce_active();
}

/**
 * Autoloader for plugin classes.
 *
 * @param string $class_name The class name to load.
 */
function wss_autoloader( $class_name ) {
	if ( strpos( $class_name, 'WSS_' ) !== 0 ) {
		return;
	}

	$class_file = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

	$directories = array(
		WSS_PLUGIN_DIR . 'includes/',
		WSS_PLUGIN_DIR . 'includes/sync/',
		WSS_PLUGIN_DIR . 'includes/admin/',
		WSS_PLUGIN_DIR . 'includes/frontend/',
		WSS_PLUGIN_DIR . 'includes/content-sources/',
	);

	foreach ( $directories as $dir ) {
		if ( file_exists( $dir . $class_file ) ) {
			require_once $dir . $class_file;
			return;
		}
	}
}
spl_autoload_register( 'wss_autoloader' );

/**
 * Plugin activation hook.
 */
function wss_activate() {
	require_once WSS_PLUGIN_DIR . 'includes/class-wss-activator.php';
	WSS_Activator::activate();
}
register_activation_hook( __FILE__, 'wss_activate' );

/**
 * Plugin deactivation hook.
 */
function wss_deactivate() {
	require_once WSS_PLUGIN_DIR . 'includes/class-wss-deactivator.php';
	WSS_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'wss_deactivate' );

/**
 * Initialize the plugin.
 */
function wss_init() {
	// Load text domain.
	load_plugin_textdomain( 'woo-smart-search', false, dirname( WSS_PLUGIN_BASENAME ) . '/languages' );

	// Declare HPOS compatibility if WooCommerce is active.
	if ( wss_is_woocommerce_active() ) {
		add_action(
			'before_woocommerce_init',
			function () {
				if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				}
			}
		);
	}

	// Initialize loader.
	$loader = new WSS_Loader();
	$loader->run();
}
add_action( 'plugins_loaded', 'wss_init' );

/**
 * Get the search engine instance (singleton).
 *
 * Returns the configured engine: WSS_Meilisearch or WSS_Local_Engine.
 *
 * @return WSS_Search_Engine|null
 */
function wss_get_engine() {
	$engine_type = wss_get_option( 'search_engine', 'meilisearch' );

	if ( 'local' === $engine_type ) {
		return WSS_Local_Engine::get_instance();
	}

	return WSS_Meilisearch::get_instance();
}

/**
 * Check if the local search engine is active.
 *
 * @return bool
 */
function wss_is_local_engine() {
	return 'local' === wss_get_option( 'search_engine', 'meilisearch' );
}

/**
 * Get a plugin option.
 *
 * @param string $key     Option key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function wss_get_option( $key, $default = '', $force_refresh = false ) {
	static $options = null;
	if ( null === $options || $force_refresh ) {
		$options = get_option( 'wss_settings', array() );
	}
	return isset( $options[ $key ] ) ? $options[ $key ] : $default;
}

/**
 * Update a plugin option.
 *
 * @param string $key   Option key.
 * @param mixed  $value Option value.
 */
function wss_update_option( $key, $value ) {
	$options         = get_option( 'wss_settings', array() );
	$options[ $key ] = $value;
	update_option( 'wss_settings', $options );
}

/**
 * Log a message to the plugin's activity log.
 *
 * @param string $message Log message.
 * @param string $type    Log type: info, warning, error.
 * @param array  $context Additional context.
 */
function wss_log( $message, $type = 'info', $context = array() ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'wss_logs';

	$wpdb->insert(
		$table_name,
		array(
			'type'       => sanitize_text_field( $type ),
			'message'    => sanitize_text_field( $message ),
			'context'    => wp_json_encode( $context ),
			'created_at' => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s' )
	);

	// Periodically trim old logs (every ~100 inserts, not every call).
	if ( wp_rand( 1, 100 ) === 1 ) {
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count > 10000 ) {
			$wpdb->query( "DELETE FROM {$table_name} ORDER BY id ASC LIMIT 1000" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
}
