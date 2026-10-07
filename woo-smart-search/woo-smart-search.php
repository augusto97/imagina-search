<?php
/**
 * Plugin Name:       Woo Smart Search
 * Plugin URI:        https://example.com/woo-smart-search
 * Description:       Ultra-fast search powered by Meilisearch for WooCommerce products, blog posts, pages, and custom post types.
 * Version:           6.36.0
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
define( 'WSS_VERSION', '6.36.0' );
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
	// Every Spanish variant (es_PE, es_MX, es_CO…) uses the bundled es_ES
	// translation unless a file for that exact locale exists.
	add_filter(
		'load_textdomain_mofile',
		function ( $mofile, $domain ) {
			if ( 'woo-smart-search' !== $domain || file_exists( $mofile ) || ! preg_match( '/-es_[A-Z]{2}(_[a-z]+)?\.mo$/', $mofile ) ) {
				return $mofile;
			}
			$fallback = preg_replace( '/-es_[A-Z]{2}(_[a-z]+)?\.mo$/', '-es_ES.mo', $mofile );
			return file_exists( $fallback ) ? $fallback : $mofile;
		},
		10,
		2
	);
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
 * Acquire a cross-process lock (atomic INSERT IGNORE on the options table).
 *
 * Transients are not atomic (get + set), so two workers — the browser-driven
 * Full Sync and Action Scheduler — could both "win" and process the same batch.
 *
 * @param string $name Lock name.
 * @param int    $ttl  Seconds after which a lock is considered stale.
 * @return bool True when the lock was acquired.
 */
function wss_acquire_lock( $name, $ttl = 120 ) {
	global $wpdb;

	$key = 'wss_lock_' . $name;
	$now = time();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, $now ) );
	if ( $inserted ) {
		return true;
	}

	// Lock exists: take it over only if stale (crashed worker), atomically.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$taken = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value < %d", $now, $key, $now - $ttl ) );

	return (bool) $taken;
}

/**
 * Release a lock acquired with wss_acquire_lock().
 *
 * @param string $name Lock name.
 */
function wss_release_lock( $name ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete( $wpdb->options, array( 'option_name' => 'wss_lock_' . $name ), array( '%s' ) );
}

/**
 * Read the full-sync progress straight from the DB (another process may have
 * just updated it, so the request-level option cache can be stale).
 *
 * @return array
 */
function wss_get_sync_progress() {
	wp_cache_delete( 'wss_sync_progress', 'options' );
	$progress = get_option( 'wss_sync_progress', array() );
	return is_array( $progress ) ? $progress : array();
}

/**
 * Record that a sync just happened.
 *
 * Stored in its own option: re-saving the whole wss_settings array from a
 * background sync could overwrite settings an admin saved in parallel.
 */
function wss_touch_last_sync() {
	update_option( 'wss_last_sync', time(), false );
	// Indexed content changed: start a new search-cache generation.
	update_option( 'wss_cache_gen', (int) get_option( 'wss_cache_gen', 0 ) + 1, true );
}

/**
 * Timestamp of the last sync (0 = never).
 *
 * @return int
 */
function wss_get_last_sync() {
	$ts = (int) get_option( 'wss_last_sync', 0 );
	return $ts ? $ts : (int) wss_get_option( 'last_sync', 0 ); // Pre-6.35 location.
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

	// Cap context size — a stray product object would bloat the logs table.
	$context_json = wp_json_encode( $context );
	if ( strlen( (string) $context_json ) > 10000 ) {
		$context_json = wp_json_encode( array( '_truncated' => true ) );
	}

	$wpdb->insert(
		$table_name,
		array(
			'type'       => sanitize_text_field( $type ),
			'message'    => sanitize_text_field( $message ),
			'context'    => $context_json,
			'created_at' => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s' )
	);

	// Periodically trim old logs (every ~100 inserts, not every call).
	if ( wp_rand( 1, 100 ) === 1 ) {
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count > 10000 ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} ORDER BY id ASC LIMIT %d", 1000 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
}
