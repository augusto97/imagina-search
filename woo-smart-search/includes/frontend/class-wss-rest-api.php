<?php
/**
 * REST API proxy endpoint.
 *
 * Proxies search requests to Meilisearch, handles rate limiting,
 * caching, analytics logging, and click tracking.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Rest_Api
 */
class WSS_Rest_Api {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'wss/v1';

	/**
	 * Initialize REST API.
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'       => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit'   => array(
						'default'           => 8,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'page'    => array(
						'default'           => 1,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'filters' => array(
						'default'           => '',
						'type'              => 'string',
						// No sanitize_callback: sanitize_text_field converts & to &amp;
						// breaking filter values. Security handled by sanitize_filter_string().
					),
					'sort'    => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'facets'  => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Popular searches endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/popular',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_popular' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit' => array(
						'default'           => 6,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Click tracking endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/track-click',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_track_click' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'query'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'product_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Handle search request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_search( $request ) {
		// Rate limiting.
		if ( ! $this->check_rate_limit() ) {
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Rate limit exceeded. Please try again later.', 'woo-smart-search' ),
				array( 'status' => 429 )
			);
		}

		$query = $request->get_param( 'q' );

		// Minimum query length.
		if ( mb_strlen( $query ) < 2 ) {
			return rest_ensure_response( array(
				'hits'  => array(),
				'total' => 0,
				'query' => $query,
			) );
		}

		$limit   = min( $request->get_param( 'limit' ), 50 );
		$page    = $request->get_param( 'page' );
		$filters = $request->get_param( 'filters' );
		$sort    = $request->get_param( 'sort' );
		$facets  = $request->get_param( 'facets' );

		// Check cache.
		$cache_ttl = (int) wss_get_option( 'cache_ttl', 300 );
		$cache_key = 'wss_search_' . md5( $query . $limit . $page . $filters . $sort . $facets );

		if ( $cache_ttl > 0 ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				$this->log_search( $query, $cached['total'] ?? 0 );
				return rest_ensure_response( apply_filters( 'wss_proxy_response', $cached, $query ) );
			}
		}

		// Check if fallback is active.
		$fallback_active = get_option( 'wss_fallback_active', false );

		// Get Meilisearch instance.
		$engine = wss_get_engine();
		if ( ! $engine || $fallback_active ) {
			$response = $this->fallback_search( $query, $limit, $page );
			$this->log_search( $query, $response['total'] );
			return rest_ensure_response( $response );
		}

		$index_name = wss_get_option( 'index_name', 'woo_products' );

		// Build search options.
		$options = array(
			'limit'  => $limit,
			'offset' => ( $page - 1 ) * $limit,
		);

		if ( ! empty( $filters ) ) {
			$options['filters'] = $this->sanitize_filter_string( $filters );
		}

		if ( ! empty( $sort ) ) {
			$options['sort'] = array( $sort );
		}

		if ( ! empty( $facets ) ) {
			$options['facets'] = explode( ',', $facets );
		} else {
			$default_facets = array( 'categories', 'stock_status', 'on_sale', 'brand', 'rating' );
			// Include product attribute facets (e.g., attributes.Color, attributes.Size).
			$attr_names = self::get_product_attribute_names();
			foreach ( $attr_names as $attr_name ) {
				$default_facets[] = 'attributes.' . $attr_name;
			}
			$options['facets'] = $default_facets;
		}

		$options['highlight_fields'] = array( 'name', 'description', 'categories' );

		// Hide out-of-stock if configured.
		if ( 'yes' !== wss_get_option( 'show_out_of_stock_results', 'yes' ) ) {
			$stock_filter = 'stock_status = "instock"';
			if ( ! empty( $options['filters'] ) ) {
				$options['filters'] .= ' AND ' . $stock_filter;
			} else {
				$options['filters'] = $stock_filter;
			}
		}

		$results = $engine->search( $index_name, $query, $options );

		// If search returned an error, try fallback.
		if ( isset( $results['error'] ) ) {
			$response = $this->fallback_search( $query, $limit, $page );
			$this->log_search( $query, $response['total'] );
			return rest_ensure_response( $response );
		}

		// Format response.
		$response = array(
			'hits'             => $this->format_hits( $results['hits'] ),
			'total'            => $results['estimatedTotalHits'],
			'query'            => $query,
			'processingTimeMs' => isset( $results['processingTimeMs'] ) ? $results['processingTimeMs'] : 0,
			'facets'           => isset( $results['facetDistribution'] ) ? $results['facetDistribution'] : array(),
		);

		$response = apply_filters( 'wss_proxy_response', $response, $query );

		// Cache.
		$cache_ttl = apply_filters( 'wss_cache_ttl', $cache_ttl, $query );
		if ( $cache_ttl > 0 ) {
			set_transient( $cache_key, $response, $cache_ttl );
		}

		// Log search for analytics.
		$this->log_search( $query, $response['total'] );

		do_action( 'wss_search_performed', $query, $response['total'] );

		return rest_ensure_response( $response );
	}

	/**
	 * Handle popular searches request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_popular( $request ) {
		$limit = min( $request->get_param( 'limit' ), 20 );

		$cache_key = 'wss_popular_' . $limit;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$analytics = new WSS_Search_Analytics();
		$top       = $analytics->get_top_queries( $limit );

		$items = array();
		foreach ( $top as $row ) {
			$items[] = array(
				'query' => $row->query,
				'count' => (int) $row->count,
			);
		}

		$response = array( 'searches' => $items );
		set_transient( $cache_key, $response, 300 );

		return rest_ensure_response( $response );
	}

	/**
	 * Handle click tracking.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_track_click( $request ) {
		if ( wss_get_option( 'enable_analytics', 'yes' ) !== 'yes' ) {
			return rest_ensure_response( array( 'tracked' => false ) );
		}

		$query      = $request->get_param( 'query' );
		$product_id = $request->get_param( 'product_id' );

		$analytics = new WSS_Search_Analytics();
		$analytics->log_click( $query, $product_id );

		return rest_ensure_response( array( 'tracked' => true ) );
	}

	/**
	 * Log search for analytics.
	 *
	 * @param string $query         Search query.
	 * @param int    $results_count Number of results.
	 */
	private function log_search( string $query, int $results_count ) {
		if ( wss_get_option( 'enable_analytics', 'yes' ) !== 'yes' ) {
			return;
		}

		// Anonymize IP: store only a hash for GDPR compliance.
		$ip              = $this->get_client_ip();
		$anonymized_ip   = wp_hash( $ip );
		// Truncate user agent to reduce PII exposure.
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$user_agent = substr( $user_agent, 0, 100 );

		$analytics = new WSS_Search_Analytics();
		$analytics->log_search( $query, $results_count, $anonymized_ip, $user_agent );
	}

	/**
	 * Format hits for the response.
	 *
	 * @param array $hits Raw hits from Meilisearch.
	 * @return array
	 */
	private function format_hits( array $hits ): array {
		$formatted = array();
		$settings  = get_option( 'wss_settings', array() );

		foreach ( $hits as $hit ) {
			$item = array(
				'id'        => isset( $hit['id'] ) ? (int) $hit['id'] : 0,
				'name'      => isset( $hit['name'] ) ? $hit['name'] : '',
				'permalink' => isset( $hit['permalink'] ) ? $hit['permalink'] : '',
			);

			// Highlighted name.
			if ( isset( $hit['_formatted']['name'] ) ) {
				$item['name_highlighted'] = $hit['_formatted']['name'];
			}

			if ( ( $settings['show_image'] ?? 'yes' ) === 'yes' ) {
				$item['image'] = isset( $hit['image'] ) ? $hit['image'] : '';
			}

			if ( ( $settings['show_price'] ?? 'yes' ) === 'yes' ) {
				$item['price']         = isset( $hit['price'] ) ? (float) $hit['price'] : 0;
				$item['regular_price'] = isset( $hit['regular_price'] ) ? (float) $hit['regular_price'] : 0;
				$item['sale_price']    = isset( $hit['sale_price'] ) ? (float) $hit['sale_price'] : 0;
				$item['on_sale']       = isset( $hit['on_sale'] ) ? (bool) $hit['on_sale'] : false;
				$item['currency']      = isset( $hit['currency'] ) ? $hit['currency'] : get_woocommerce_currency();
				$item['price_min']     = isset( $hit['price_min'] ) ? (float) $hit['price_min'] : 0;
				$item['price_max']     = isset( $hit['price_max'] ) ? (float) $hit['price_max'] : 0;
			}

			if ( ( $settings['show_category'] ?? 'yes' ) === 'yes' ) {
				$item['categories'] = isset( $hit['categories'] ) ? $hit['categories'] : array();
			}

			if ( ( $settings['show_sku'] ?? 'no' ) === 'yes' ) {
				$item['sku'] = isset( $hit['sku'] ) ? $hit['sku'] : '';
			}

			if ( ( $settings['show_stock'] ?? 'yes' ) === 'yes' ) {
				$item['stock_status'] = isset( $hit['stock_status'] ) ? $hit['stock_status'] : '';
			}

			if ( ( $settings['show_rating'] ?? 'no' ) === 'yes' ) {
				$item['rating']       = isset( $hit['rating'] ) ? (float) $hit['rating'] : 0;
				$item['review_count'] = isset( $hit['review_count'] ) ? (int) $hit['review_count'] : 0;
			}

			if ( ( $settings['show_sale_badge'] ?? 'yes' ) === 'yes' ) {
				$item['show_sale_badge'] = true;
			}

			$item['type'] = isset( $hit['type'] ) ? $hit['type'] : 'simple';

			$item = apply_filters( 'wss_result_item_html', $item, $hit );

			$formatted[] = $item;
		}

		return $formatted;
	}

	/**
	 * Check rate limiting.
	 *
	 * Uses REMOTE_ADDR only to prevent IP spoofing via headers.
	 *
	 * @return bool
	 */
	private function check_rate_limit(): bool {
		$limit = (int) apply_filters( 'wss_rate_limit', wss_get_option( 'rate_limit', 30 ) );
		$ip    = $this->get_client_ip();
		$key   = 'wss_rate_' . md5( $ip );

		$current = (int) get_transient( $key );
		if ( $current >= $limit ) {
			return false;
		}

		set_transient( $key, $current + 1, 60 );
		return true;
	}

	/**
	 * Get client IP address.
	 *
	 * Only uses REMOTE_ADDR to prevent IP spoofing via
	 * X-Forwarded-For or X-Real-IP headers.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}
		return '127.0.0.1';
	}

	/**
	 * Get all WooCommerce product attribute names (labels).
	 *
	 * Reads directly from WooCommerce attribute taxonomies.
	 * Cached in a static variable per request.
	 *
	 * @return array List of attribute label strings.
	 */
	public static function get_product_attribute_names(): array {
		static $names = null;
		if ( null !== $names ) {
			return $names;
		}
		$names = array();
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return $names;
		}
		$taxonomies = wc_get_attribute_taxonomies();
		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $tax ) {
				$names[] = $tax->attribute_label ? $tax->attribute_label : $tax->attribute_name;
			}
		}
		return $names;
	}

	/**
	 * Sanitize the filter string passed from the frontend.
	 *
	 * Only allows known filterable attributes and safe operators.
	 *
	 * @param string $filter Raw filter string.
	 * @return string Sanitized filter string.
	 */
	private function sanitize_filter_string( string $filter ): string {
		// Allowed filterable attribute names.
		$allowed_attrs = array(
			'categories', 'stock_status', 'on_sale', 'brand', 'rating',
			'price', 'price_min', 'price_max', 'type',
		);
		// Also allow dynamic product attributes (attributes.Color, etc.).
		$attr_names = self::get_product_attribute_names();
		foreach ( $attr_names as $attr_name ) {
			$allowed_attrs[] = 'attributes.' . $attr_name;
		}
		$allowed_pattern = implode( '|', array_map( 'preg_quote', $allowed_attrs ) );

		// Strip anything that is not: attribute names, operators, values, AND/OR/NOT, parentheses, quotes, numbers.
		// This prevents injection of arbitrary Meilisearch filter syntax.
		// Note: & is allowed because filter values can contain it (e.g., "Baseball & Softball").
		$safe = preg_replace(
			'/[^\w\s=<>!"\'\-.,()&\/]/u',
			'',
			$filter
		);

		// Verify all attribute references are in the allowlist.
		// Extract tokens before operators (supports dotted names like attributes.Color).
		preg_match_all( '/\b([a-zA-Z_][a-zA-Z0-9_.]*)\b\s*(?:=|!=|>|<|>=|<=|TO|IN|NOT)/', $safe, $matches );
		if ( ! empty( $matches[1] ) ) {
			// Build lowercase lookup list.
			$allowed_lower = array_map( 'strtolower', array_merge( $allowed_attrs, array( 'and', 'or', 'not' ) ) );
			foreach ( $matches[1] as $attr ) {
				if ( ! in_array( strtolower( $attr ), $allowed_lower, true ) ) {
					// Unknown attribute found — reject the entire filter.
					return '';
				}
			}
		}

		return $safe;
	}

	/**
	 * Fallback to WooCommerce native search.
	 *
	 * @param string $query Search query.
	 * @param int    $limit Results limit.
	 * @param int    $page  Page number.
	 * @return array
	 */
	private function fallback_search( string $query, int $limit, int $page ): array {
		$args = array(
			's'              => $query,
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'paged'          => $page,
		);

		$wp_query = new WP_Query( $args );
		$hits     = array();

		while ( $wp_query->have_posts() ) {
			$wp_query->the_post();
			$product = wc_get_product( get_the_ID() );
			if ( ! $product ) {
				continue;
			}

			$image_id  = $product->get_image_id();
			$categories = array();
			$terms      = get_the_terms( $product->get_id(), 'product_cat' );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$categories = wp_list_pluck( $terms, 'name' );
			}

			$hits[] = array(
				'id'            => $product->get_id(),
				'name'          => $product->get_name(),
				'permalink'     => get_permalink( $product->get_id() ),
				'image'         => $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '',
				'price'         => (float) $product->get_price(),
				'regular_price' => (float) $product->get_regular_price(),
				'sale_price'    => $product->get_sale_price() ? (float) $product->get_sale_price() : 0,
				'on_sale'       => $product->is_on_sale(),
				'stock_status'  => $product->get_stock_status(),
				'categories'    => $categories,
				'currency'      => get_woocommerce_currency(),
				'type'          => $product->get_type(),
			);
		}
		wp_reset_postdata();

		return array(
			'hits'     => $hits,
			'total'    => $wp_query->found_posts,
			'query'    => $query,
			'fallback' => true,
		);
	}
}
