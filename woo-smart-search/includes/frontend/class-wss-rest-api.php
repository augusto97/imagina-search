<?php
/**
 * REST API proxy endpoint.
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
						'sanitize_callback' => 'sanitize_text_field',
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
			return rest_ensure_response(
				array(
					'hits'  => array(),
					'total' => 0,
					'query' => $query,
				)
			);
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
				$cached_response = apply_filters( 'wss_proxy_response', $cached, $query );
				return rest_ensure_response( $cached_response );
			}
		}

		// Get engine.
		$engine = wss_get_engine();
		if ( ! $engine ) {
			// Fallback to native WooCommerce search.
			return rest_ensure_response( $this->fallback_search( $query, $limit, $page ) );
		}

		$index_name = wss_get_option( 'index_name', 'woo_products' );

		// Build options.
		$options = array(
			'limit'  => $limit,
			'offset' => ( $page - 1 ) * $limit,
		);

		if ( ! empty( $filters ) ) {
			$options['filters'] = $filters;
		}

		if ( ! empty( $sort ) ) {
			$options['sort'] = array( $sort );
		}

		if ( ! empty( $facets ) ) {
			$options['facets'] = explode( ',', $facets );
		} else {
			$options['facets'] = array( 'categories', 'stock_status', 'on_sale', 'brand' );
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

		// Format response.
		$response = array(
			'hits'            => $this->format_hits( $results['hits'] ),
			'total'           => $results['estimatedTotalHits'],
			'query'           => $query,
			'processingTimeMs' => isset( $results['processingTimeMs'] ) ? $results['processingTimeMs'] : 0,
			'facets'          => isset( $results['facetDistribution'] ) ? $results['facetDistribution'] : array(),
		);

		$response = apply_filters( 'wss_proxy_response', $response, $query );

		// Cache.
		$cache_ttl = apply_filters( 'wss_cache_ttl', $cache_ttl, $query );
		if ( $cache_ttl > 0 ) {
			set_transient( $cache_key, $response, $cache_ttl );
		}

		do_action( 'wss_search_performed', $query, $response['total'] );

		return rest_ensure_response( $response );
	}

	/**
	 * Format hits for the response.
	 *
	 * @param array $hits Raw hits.
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

			// Add highlighted name if available.
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

			$item = apply_filters( 'wss_result_item_html', $item, $hit );

			$formatted[] = $item;
		}

		return $formatted;
	}

	/**
	 * Check rate limiting.
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
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip_keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs.
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '127.0.0.1';
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

			$image_id = $product->get_image_id();
			$hits[]   = array(
				'id'           => $product->get_id(),
				'name'         => $product->get_name(),
				'permalink'    => get_permalink( $product->get_id() ),
				'image'        => $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '',
				'price'        => (float) $product->get_price(),
				'regular_price' => (float) $product->get_regular_price(),
				'on_sale'      => $product->is_on_sale(),
				'stock_status' => $product->get_stock_status(),
				'currency'     => get_woocommerce_currency(),
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
