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

// Find wp-load.php by walking up directories. Start from both the resolved
// file path and the requested script path (they differ when the plugin folder
// is a symlink), and also check a "wp/" subfolder (Bedrock-style installs
// where wp-content lives outside the WordPress core directory).
$wp_load    = '';
$wss_starts = array( dirname( __FILE__ ) );
if ( ! empty( $_SERVER['SCRIPT_FILENAME'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$wss_starts[] = dirname( $_SERVER['SCRIPT_FILENAME'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
}
foreach ( array_unique( $wss_starts ) as $wss_dir ) {
	for ( $wss_i = 0; $wss_i < 6 && ! $wp_load; $wss_i++ ) {
		$wss_dir = dirname( $wss_dir );
		foreach ( array( $wss_dir . '/wp-load.php', $wss_dir . '/wp/wp-load.php' ) as $wss_candidate ) {
			if ( file_exists( $wss_candidate ) ) {
				$wp_load = $wss_candidate;
				break;
			}
		}
	}
	if ( $wp_load ) {
		break;
	}
}
if ( ! $wp_load ) {
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

// Restrict CORS to same-origin only (no wildcard).
$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? $_SERVER['HTTP_ORIGIN'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
$site_url = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl' LIMIT 1" );
if ( $site_url && $origin ) {
	// Only allow requests from the same site.
	$site_host   = parse_url( $site_url, PHP_URL_HOST );
	$origin_host = parse_url( $origin, PHP_URL_HOST );
	if ( $site_host && $origin_host && $site_host === $origin_host ) {
		header( 'Access-Control-Allow-Origin: ' . $origin );
	}
}

// Only serve while the plugin is active and the local engine is selected:
// otherwise a stale local index (possibly with since-unpublished content)
// would stay publicly searchable.
$wss_settings_raw = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wss_settings' LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wss_settings_arr = is_serialized( (string) $wss_settings_raw ) ? @unserialize( trim( $wss_settings_raw ), array( 'allowed_classes' => false ) ) : array(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions
$wss_active_raw   = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'active_plugins' LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wss_active       = is_serialized( (string) $wss_active_raw ) ? (array) @unserialize( trim( $wss_active_raw ), array( 'allowed_classes' => false ) ) : array(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions
$wss_basename     = basename( __DIR__ ) . '/woo-smart-search.php';
$wss_is_active    = in_array( $wss_basename, $wss_active, true );
if ( ! $wss_is_active && is_multisite() ) {
	// option.php (get_site_option) isn't loaded in SHORTINIT: read sitemeta.
	$wss_network_raw = $wpdb->get_var( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = 'active_sitewide_plugins' LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wss_network     = is_serialized( (string) $wss_network_raw ) ? @unserialize( trim( $wss_network_raw ), array( 'allowed_classes' => false ) ) : array(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions
	$wss_is_active   = is_array( $wss_network ) && isset( $wss_network[ $wss_basename ] );
}
if ( ! $wss_is_active || ! is_array( $wss_settings_arr ) || 'local' !== ( $wss_settings_arr['search_engine'] ?? '' ) ) {
	http_response_code( 404 );
	echo '{"error":"Local search is not enabled"}';
	exit;
}

// Rate limiting: fixed 60-second window per IP. (The window start is kept,
// not refreshed on every request — refreshing made it a sliding window that
// blocked anyone typing continuously.) A matching _transient_timeout_ row lets
// WordPress' expired-transient cleanup delete the counters.
$wss_client_ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-fA-F.:\/]/', '', $_SERVER['REMOTE_ADDR'] ) : '127.0.0.1'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
$wss_rate_key   = 'wss_rl_' . md5( $wss_client_ip );
$wss_rate_limit = 120; // Shared IPs (mobile carrier NAT, offices) need headroom.
$wss_now        = time();
$wss_rate_row   = $wpdb->get_var( $wpdb->prepare(
	"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
	'_transient_' . $wss_rate_key
) );
$wss_rate_count = 0;
$wss_rate_start = $wss_now;
if ( $wss_rate_row ) {
	$rate_data = json_decode( $wss_rate_row, true );
	if ( is_array( $rate_data ) && isset( $rate_data['c'], $rate_data['t'] ) && ( $wss_now - (int) $rate_data['t'] ) < 60 ) {
		$wss_rate_count = (int) $rate_data['c'];
		$wss_rate_start = (int) $rate_data['t'];
	}
}
if ( $wss_rate_count >= $wss_rate_limit ) {
	http_response_code( 429 );
	header( 'Retry-After: ' . max( 1, 60 - ( $wss_now - $wss_rate_start ) ) );
	echo '{"error":"Rate limit exceeded"}';
	exit;
}
$new_rate = wp_json_encode( array( 'c' => $wss_rate_count + 1, 't' => $wss_rate_start ) );
$wpdb->query( $wpdb->prepare(
	"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')
	 ON DUPLICATE KEY UPDATE option_value = %s",
	'_transient_' . $wss_rate_key, $new_rate, $new_rate
) );
if ( 0 === $wss_rate_count ) {
	$wss_expires = $wss_rate_start + 120;
	$wpdb->query( $wpdb->prepare(
		"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %d, 'no')
		 ON DUPLICATE KEY UPDATE option_value = %d",
		'_transient_timeout_' . $wss_rate_key, $wss_expires, $wss_expires
	) );
}

// Parse request parameters (strings only: `q[]=` used to be a fatal TypeError).
$wss_get = static function ( $key ) {
	return ( isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ) ? stripslashes( $_GET[ $key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
};
$query      = trim( $wss_get( 'q' ) );
$limit      = '' !== $wss_get( 'limit' ) ? max( 1, min( 100, (int) $wss_get( 'limit' ) ) ) : 12;
$page       = '' !== $wss_get( 'page' ) ? max( 1, min( 100, (int) $wss_get( 'page' ) ) ) : 1;
$offset     = ( $page - 1 ) * $limit;
$filter_str = $wss_get( 'filters' );
$sort       = $wss_get( 'sort' );
$facets_str = $wss_get( 'facets' );

// Bound the filter expression (it is evaluated per candidate document).
if ( strlen( $filter_str ) > 1000 || preg_match_all( '/\s(AND|OR)\s/i', $filter_str ) > 30 ) {
	$filter_str = '';
}

// Sanitize filter string — strip dangerous characters (only allow field names, operators, values).
$filter_str = preg_replace( '/[^\w\s=<>!"\'\-.,()&\/]/u', '', $filter_str );

// Sanitize sort — only allow "field:direction" format.
if ( ! empty( $sort ) && ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_.]*:(asc|desc)$/i', $sort ) ) {
	$sort = '';
}

// Sanitize facets — only allow comma-separated alphanumeric field names.
if ( ! empty( $facets_str ) ) {
	$facets_str = preg_replace( '/[^a-zA-Z0-9_.,]/', '', $facets_str );
}

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
$query = function_exists( 'mb_substr' ) ? mb_substr( strip_tags( $query ), 0, 100 ) : substr( strip_tags( $query ), 0, 100 );

// Load plugin constants and the local engine.
if ( ! defined( 'WSS_VERSION' ) ) {
	define( 'WSS_VERSION', '5.1.0' );
}
if ( ! defined( 'WSS_PLUGIN_DIR' ) ) {
	define( 'WSS_PLUGIN_DIR', dirname( __FILE__ ) . '/' );
}

// remove_accents() (used to fold accents in queries) calls get_locale(),
// which SHORTINIT doesn't load — without this, any accented query was a fatal.
if ( ! function_exists( 'get_locale' ) ) {
	function get_locale() {
		global $wpdb;
		$locale = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'WPLANG' LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $locale ? $locale : 'en_US';
	}
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
$settings   = $wss_settings_arr; // Read (class-safe) during the activation check above.
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
			// Prevent PHP object injection — only allow scalar/array types.
			return @unserialize( $data, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
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

// Hide out-of-stock PRODUCTS if configured.
// Use "!= outofstock" so WordPress content (no stock_status field) is kept.
$show_oos = isset( $settings['show_out_of_stock_results'] ) ? $settings['show_out_of_stock_results'] : 'yes';
if ( 'yes' !== $show_oos ) {
	$stock_filter = 'stock_status != "outofstock"';
	if ( ! empty( $search_options['filters'] ) ) {
		$search_options['filters'] .= ' AND ' . $stock_filter;
	} else {
		$search_options['filters'] = $stock_filter;
	}
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

// Log the search for analytics. The local engine never goes through the REST
// proxy (which logs server-side) nor fires the direct-mode tracking beacon, so
// without this every local-engine search would go unrecorded and the Analytics
// panel would stay at zero. SHORTINIT has no plugin API, so write directly with
// $wpdb. Gated by the enable_analytics setting (default on); empty queries are
// skipped. Stored in UTC to match the analytics reader's date filters.
$wss_analytics_on = ! isset( $settings['enable_analytics'] ) || 'yes' === $settings['enable_analytics'];
if ( $wss_analytics_on && '' !== trim( (string) $query ) ) {
	$wss_ua = isset( $_SERVER['HTTP_USER_AGENT'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		? substr( preg_replace( '/[\x00-\x1F\x7F]/', '', wp_strip_all_tags( (string) $_SERVER['HTTP_USER_AGENT'] ) ), 0, 100 )
		: '';
	// Anonymize the IP (GDPR) — the REST logger hashes with wp_hash(), which is
	// unavailable in SHORTINIT, so mirror it with an HMAC over a WP salt.
	$wss_salt   = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wss' );
	$wss_ip_hash = hash_hmac( 'md5', $wss_client_ip, $wss_salt );
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->prepare(
			"INSERT INTO {$wpdb->prefix}wss_search_log ( query, results_count, ip_address, user_agent, created_at ) VALUES ( %s, %d, %s, %s, %s )",
			substr( wp_strip_all_tags( (string) $query ), 0, 191 ),
			(int) $result['estimatedTotalHits'],
			$wss_ip_hash,
			$wss_ua,
			gmdate( 'Y-m-d H:i:s' )
		)
	);
}

echo wp_json_encode( $response );
exit;
