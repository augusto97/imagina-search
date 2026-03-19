<?php
/**
 * Plugin Name:       Woo Smart Search
 * Plugin URI:        https://example.com/woo-smart-search
 * Description:       Replace WooCommerce native search with an instant, ultra-fast search experience powered by Meilisearch.
 * Version:           2.3.1
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
define( 'WSS_VERSION', '2.3.1' );
define( 'WSS_PLUGIN_FILE', __FILE__ );
define( 'WSS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WSS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WSS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active.
 */
function wss_check_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wss_woocommerce_missing_notice' );
		return false;
	}
	return true;
}

/**
 * Admin notice when WooCommerce is not active.
 */
function wss_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Woo Smart Search requires WooCommerce to be installed and active.', 'woo-smart-search' ); ?></p>
	</div>
	<?php
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
	if ( ! wss_check_woocommerce() ) {
		return;
	}

	// Load text domain.
	load_plugin_textdomain( 'woo-smart-search', false, dirname( WSS_PLUGIN_BASENAME ) . '/languages' );

	// Declare HPOS compatibility.
	add_action(
		'before_woocommerce_init',
		function () {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}
	);

	// Initialize loader.
	$loader = new WSS_Loader();
	$loader->run();
}
add_action( 'plugins_loaded', 'wss_init' );

/**
 * Get the Meilisearch instance (singleton).
 *
 * @return WSS_Meilisearch|null
 */
function wss_get_engine() {
	return WSS_Meilisearch::get_instance();
}

/**
 * Get a plugin option.
 *
 * @param string $key     Option key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function wss_get_option( $key, $default = '' ) {
	$options = get_option( 'wss_settings', array() );
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

	// Keep only last 10000 entries.
	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $count > 10000 ) {
		$wpdb->query( "DELETE FROM {$table_name} ORDER BY id ASC LIMIT 1000" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
