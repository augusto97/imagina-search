<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package WooSmartSearch
 */

// Define test environment.
define( 'WSS_TESTING', true );

// Paths.
$_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib';

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php\n";
	echo "Set the WP_TESTS_DIR environment variable to the WordPress test library path.\n";
	exit( 1 );
}

// Load WordPress test functions.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin and WooCommerce.
 */
function _manually_load_plugin() {
	// Load WooCommerce.
	$wc_plugin = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
	if ( file_exists( $wc_plugin ) ) {
		require $wc_plugin;
	}

	// Load our plugin.
	require dirname( __DIR__ ) . '/woo-smart-search.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start the WordPress test suite.
require $_tests_dir . '/includes/bootstrap.php';
