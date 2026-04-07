<?php
/**
 * Ultra-fast local search endpoint using WordPress SHORTINIT mode.
 *
 * Bypasses theme, plugins, and most of WordPress core for maximum speed.
 * Only loads wpdb and the local search engine.
 *
 * @package WooSmartSearch
 */

// Security: verify this is a legitimate search request.
if ( ! isset( $_GET['wss_action'] ) || 'search' !== $_GET['wss_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
	http_response_code( 400 );
	echo '{"error":"Invalid request"}';
	exit;
}

// Load WordPress in SHORTINIT mode (minimal bootstrap).
define( 'SHORTINIT', true );

// Find wp-load.php by walking up directories.
$wp_load = dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	http_response_code( 500 );
	echo '{"error":"WordPress not found"}';
	exit;
}

require_once $wp_load;

// SHORTINIT only gives us $wpdb. We need a few more essentials.
// Load wp-includes files needed for our engine.
require_once ABSPATH . WPINC . '/formatting.php';
require_once ABSPATH . WPINC . '/kses.php';

// Set JSON headers.
header( 'Content-Type: application/json; charset=utf-8' );
header( 'X-Content-Type-Options: nosniff' );
header( 'Access-Control-Allow-Origin: *' );

// Parse request parameters.
$query      = isset( $_GET['q'] ) ? trim( stripslashes( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
$limit      = isset( $_GET['limit'] ) ? max( 1, min( 100, (int) $_GET['limit'] ) ) : 12; // phpcs:ignore WordPress.Security.NonceVerification
$page       = isset( $_GET['page'] ) ? max( 1, (int) $_GET['page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
$offset     = ( $page - 1 ) * $limit;
$filter_str = isset( $_GET['filters'] ) ? stripslashes( $_GET['filters'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
$sort       = isset( $_GET['sort'] ) ? stripslashes( $_GET['sort'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
$facets_str = isset( $_GET['facets'] ) ? stripslashes( $_GET['facets'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput

if ( '' === $query || strlen( $query ) < 2 ) {
	echo wp_json_encode( array(
		'hits'               => array(),
		'query'              => $query,
		'estimatedTotalHits' => 0,
		'facetDistribution'  => new stdClass(),
		'processingTimeMs'   => 0,
	) );
	exit;
}

// Sanitize query — strip HTML and limit length.
$query = substr( strip_tags( $query ), 0, 200 );

// Load plugin constants and the local engine.
if ( ! defined( 'WSS_VERSION' ) ) {
	define( 'WSS_VERSION', '4.1.0' );
}
if ( ! defined( 'WSS_PLUGIN_DIR' ) ) {
	define( 'WSS_PLUGIN_DIR', dirname( __FILE__ ) . '/' );
}

// Provide minimal wp_strip_all_tags if not available.
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
		$text = strip_tags( $text );
		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}
		return trim( $text );
	}
}

// Provide minimal wp_json_encode if not available.
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options | JSON_UNESCAPED_UNICODE, $depth );
	}
}

// Provide wp_list_pluck if not available.
if ( ! function_exists( 'wp_list_pluck' ) ) {
	function wp_list_pluck( $input_list, $field, $index_key = null ) {
		$output = array();
		foreach ( $input_list as $item ) {
			$item = (array) $item;
			if ( isset( $item[ $field ] ) ) {
				if ( null !== $index_key && isset( $item[ $index_key ] ) ) {
					$output[ $item[ $index_key ] ] = $item[ $field ];
				} else {
					$output[] = $item[ $field ];
				}
			}
		}
		return $output;
	}
}

// Load the interface and engine.
require_once WSS_PLUGIN_DIR . 'includes/class-wss-search-engine.php';
require_once WSS_PLUGIN_DIR . 'includes/class-wss-local-engine.php';

// Get settings.
$settings   = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wss_settings' LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$settings   = $settings ? maybe_unserialize( $settings ) : array();
$index_name = isset( $settings['index_name'] ) ? $settings['index_name'] : 'woo_products';

// Provide get_option for the engine's load_settings().
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $wpdb;
		$row = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $option ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return null !== $row ? maybe_unserialize( $row ) : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		// No-op in SHORTINIT mode — we don't write during search.
		return true;
	}
}

// Provide maybe_unserialize if not available.
if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $data ) {
		if ( is_serialized( $data ) ) {
			return @unserialize( $data );
		}
		return $data;
	}
}

if ( ! function_exists( 'is_serialized' ) ) {
	function is_serialized( $data, $strict = true ) {
		if ( ! is_string( $data ) ) {
			return false;
		}
		$data = trim( $data );
		if ( 'N;' === $data ) {
			return true;
		}
		if ( strlen( $data ) < 4 ) {
			return false;
		}
		if ( ':' !== $data[1] ) {
			return false;
		}
		if ( $strict ) {
			$lastc = substr( $data, -1 );
			if ( ';' !== $lastc && '}' !== $lastc ) {
				return false;
			}
		}
		$token = $data[0];
		return in_array( $token, array( 's', 'a', 'O', 'b', 'i', 'd' ), true );
	}
}

// Initialize the local engine.
$engine = WSS_Local_Engine::get_instance();

// Build search options.
$search_options = array(
	'limit'  => $limit,
	'offset' => $offset,
);

if ( ! empty( $filter_str ) ) {
	$search_options['filters'] = $filter_str;
}

if ( ! empty( $sort ) ) {
	$search_options['sort'] = array( $sort );
}

// Parse facets.
if ( ! empty( $facets_str ) ) {
	$search_options['facets'] = array_map( 'trim', explode( ',', $facets_str ) );
} else {
	// Default facets based on content.
	$content_source = isset( $settings['content_source'] ) ? $settings['content_source'] : 'auto';
	$is_wc          = class_exists( 'WooCommerce' ) || 'woocommerce' === $content_source;

	if ( $is_wc ) {
		$search_options['facets'] = array( 'categories', 'tags', 'stock_status', 'on_sale', 'brand', 'rating' );
	} else {
		$search_options['facets'] = array( 'categories', 'tags', 'post_type', 'author' );
	}
}

$search_options['highlight_fields'] = array( 'name' );

// Execute search (cache is handled inside the engine).
$result = $engine->search( $index_name, $query, $search_options );

// Format response to match Meilisearch format (for frontend compatibility).
$response = array(
	'hits'               => $result['hits'],
	'query'              => $result['query'],
	'estimatedTotalHits' => $result['estimatedTotalHits'],
	'facetDistribution'  => ! empty( $result['facetDistribution'] ) ? $result['facetDistribution'] : new stdClass(),
	'processingTimeMs'   => $result['processingTimeMs'],
);

// Add cache hit indicator for debugging (optional header).
if ( ! empty( $result['_cacheHit'] ) ) {
	header( 'X-WSS-Cache: HIT' );
} else {
	header( 'X-WSS-Cache: MISS' );
}

echo wp_json_encode( $response );
exit;
