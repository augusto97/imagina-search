<?php
/**
 * Frontend logic.
 *
 * Handles search widget output, asset enqueuing, CSS variables,
 * and search results page replacement.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Frontend
 */
class WSS_Frontend {

	/**
	 * Cached settings array to avoid multiple get_option() calls.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Get plugin settings (cached per request).
	 *
	 * @return array
	 */
	private function get_settings(): array {
		if ( null === $this->settings ) {
			$this->settings = get_option( 'wss_settings', array() );
		}
		return $this->settings;
	}

	/**
	 * Initialize frontend hooks.
	 */
	public function init() {
		$mode = wss_get_option( 'integration_mode', 'replace' );

		if ( 'replace' === $mode ) {
			add_filter( 'get_search_form', array( $this, 'replace_search_form' ), 20 );
			add_filter( 'get_product_search_form', array( $this, 'replace_search_form' ), 20 );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_head', array( $this, 'output_css_variables' ) );

		// Make CSS non-render-blocking and add defer to scripts.
		add_filter( 'style_loader_tag', array( $this, 'optimize_style_loading' ), 10, 4 );
		add_filter( 'script_loader_tag', array( $this, 'add_defer_attribute' ), 10, 3 );

		// Add DNS prefetch for REST API (same origin, but helps browsers prioritize).
		add_action( 'wp_head', array( $this, 'output_preconnect_hints' ), 1 );

		// Enqueue search results page assets when on search.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_results_page_assets' ) );

		// Redirect ?s= to ?q= on the results page, and redirect native
		// WooCommerce search to the designated results page.
		if ( 'replace' === $mode ) {
			add_action( 'template_redirect', array( $this, 'handle_search_redirects' ) );
		}
	}

	/**
	 * Replace the native search form.
	 *
	 * @param string $form The original form HTML.
	 * @return string
	 */
	public function replace_search_form( $form ) {
		return $this->get_search_widget_html();
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_assets() {
		$mode      = wss_get_option( 'integration_mode', 'replace' );
		$is_search = is_search() || is_shop() || is_product_category() || is_product_tag();

		if ( 'replace' !== $mode && ! $is_search && ! $this->has_shortcode_or_widget() ) {
			return;
		}

		wp_enqueue_style(
			'wss-search-widget',
			WSS_PLUGIN_URL . 'assets/css/search-widget.css',
			array(),
			WSS_VERSION
		);

		wp_enqueue_script(
			'wss-search-widget',
			WSS_PLUGIN_URL . 'assets/js/search-widget.js',
			array(),
			WSS_VERSION,
			true
		);

		$settings = $this->get_settings();

		wp_localize_script(
			'wss-search-widget',
			'wssConfig',
			array(
				'apiUrl'         => esc_url_raw( rest_url( 'wss/v1/search' ) ),
				'popularUrl'     => esc_url_raw( rest_url( 'wss/v1/popular' ) ),
				'trackClickUrl'  => esc_url_raw( rest_url( 'wss/v1/track-click' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'maxResults'     => (int) ( $settings['max_autocomplete_results'] ?? 8 ),
				'debounceTime'   => (int) apply_filters( 'wss_debounce_time', 150 ),
				'minQueryLength' => 2,
				'showImage'      => ( $settings['show_image'] ?? 'yes' ) === 'yes',
				'showPrice'      => ( $settings['show_price'] ?? 'yes' ) === 'yes',
				'showCategory'   => ( $settings['show_category'] ?? 'yes' ) === 'yes',
				'showSku'        => ( $settings['show_sku'] ?? 'no' ) === 'yes',
				'showStock'      => ( $settings['show_stock'] ?? 'yes' ) === 'yes',
				'showRating'     => ( $settings['show_rating'] ?? 'no' ) === 'yes',
				'showSaleBadge'  => ( $settings['show_sale_badge'] ?? 'yes' ) === 'yes',
				'theme'          => $settings['theme'] ?? 'light',
				'currency'       => get_woocommerce_currency(),
				'currencySymbol' => html_entity_decode( get_woocommerce_currency_symbol() ),
				'currencyPos'    => get_option( 'woocommerce_currency_pos', 'left' ),
				'decimals'       => (int) get_option( 'woocommerce_price_num_decimals', 2 ),
				'decimalSep'     => get_option( 'woocommerce_price_decimal_sep', '.' ),
				'thousandSep'    => get_option( 'woocommerce_price_thousand_sep', ',' ),
				'searchUrl'      => self::get_search_url_template(),
				'placeholderImg' => WSS_PLUGIN_URL . 'assets/images/placeholder.svg',
				'i18n'           => array(
					'placeholder'      => ! empty( $settings['placeholder_text'] ) ? $settings['placeholder_text'] : __( 'Search products...', 'woo-smart-search' ),
					'noResults'        => __( 'No results found for', 'woo-smart-search' ),
					'viewAll'          => __( 'View all %d results', 'woo-smart-search' ),
					'viewAllResults'   => __( 'View all results', 'woo-smart-search' ),
					'error'            => __( 'Connection error, please try again', 'woo-smart-search' ),
					'inStock'          => __( 'In stock', 'woo-smart-search' ),
					'outOfStock'       => __( 'Out of stock', 'woo-smart-search' ),
					'onBackorder'      => __( 'On backorder', 'woo-smart-search' ),
					'clearSearch'      => __( 'Clear search', 'woo-smart-search' ),
					'close'            => __( 'Close', 'woo-smart-search' ),
					'popularSearches'  => __( 'Popular', 'woo-smart-search' ),
					'suggestions'      => __( 'Suggestions', 'woo-smart-search' ),
					'products'         => __( 'Products', 'woo-smart-search' ),
					'categories'       => __( 'Categories', 'woo-smart-search' ),
					'startTyping'      => __( 'Start typing to search products...', 'woo-smart-search' ),
				),
				'widgetLayout'   => $settings['widget_layout'] ?? 'standard',
				'visibleFacets'  => implode( ',', $settings['visible_facets'] ?? array( 'categories', 'price', 'stock', 'attributes' ) ),
			)
		);

		// Custom CSS.
		$custom_css = $settings['custom_css'] ?? '';
		if ( ! empty( $custom_css ) ) {
			wp_add_inline_style( 'wss-search-widget', $custom_css );
		}
	}

	/**
	 * Enqueue search results page assets when applicable.
	 *
	 * @param bool $force Force enqueue regardless of context (used by shortcode).
	 */
	public function enqueue_results_page_assets( $force = false ) {
		if ( ! $force ) {
			// Also enqueue on the designated results page.
			$results_page_id = (int) wss_get_option( 'results_page_id', 0 );
			$is_results_page = $results_page_id && is_page( $results_page_id );

			if ( ! $is_results_page && ( ! is_search() || get_query_var( 'post_type' ) !== 'product' ) ) {
				return;
			}
		}

		wp_enqueue_style(
			'wss-results-page',
			WSS_PLUGIN_URL . 'assets/css/results-page.css',
			array( 'wss-search-widget' ),
			WSS_VERSION
		);

		wp_enqueue_script(
			'wss-results-page',
			WSS_PLUGIN_URL . 'assets/js/results-page.js',
			array( 'wss-search-widget' ),
			WSS_VERSION,
			true
		);
	}

	/**
	 * Output CSS custom properties.
	 */
	public function output_css_variables() {
		// Cache generated CSS variables to avoid recomputing every page load.
		$cache_key = 'wss_css_vars_' . WSS_VERSION;
		$css       = get_transient( $cache_key );

		if ( false === $css ) {
			$settings = $this->get_settings();
			$theme    = $settings['theme'] ?? 'light';

			$vars = array(
				'--wss-primary-color'   => $settings['primary_color'] ?? '#2563eb',
				'--wss-primary-hover'   => self::darken_color( $settings['primary_color'] ?? '#2563eb', 15 ),
				'--wss-bg-color'        => $settings['bg_color'] ?? '#ffffff',
				'--wss-text-color'      => $settings['text_color'] ?? '#1f2937',
				'--wss-text-secondary'  => '#6b7280',
				'--wss-border-color'    => $settings['border_color'] ?? '#e5e7eb',
				'--wss-highlight-bg'    => $settings['highlight_bg'] ?? '#fef3c7',
				'--wss-highlight-text'  => $settings['highlight_text'] ?? '#92400e',
				'--wss-font-size-base'  => ( $settings['font_size'] ?? '14' ) . 'px',
				'--wss-border-radius'   => ( $settings['border_radius'] ?? '8' ) . 'px',
			);

			if ( 'dark' === $theme ) {
				$vars['--wss-bg-color']       = '#1f2937';
				$vars['--wss-text-color']     = '#f9fafb';
				$vars['--wss-text-secondary'] = '#9ca3af';
				$vars['--wss-border-color']   = '#374151';
			}

			$css = ':root {';
			foreach ( $vars as $prop => $value ) {
				// Strip any characters that could break out of CSS context.
				$safe_value = preg_replace( '/[^a-zA-Z0-9#%,.\-()_ ]/', '', $value );
				$css .= esc_attr( $prop ) . ':' . $safe_value . ';';
			}
			$css .= '}';

			set_transient( $cache_key, $css, DAY_IN_SECONDS );
		}

		echo '<style id="wss-css-vars">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get the search widget HTML.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function get_search_widget_html( $atts = array() ) {
		$settings    = $this->get_settings();
		$placeholder = ! empty( $atts['placeholder'] )
			? $atts['placeholder']
			: ( ! empty( $settings['placeholder_text'] ) ? $settings['placeholder_text'] : __( 'Search products...', 'woo-smart-search' ) );

		$width = isset( $atts['width'] ) ? $atts['width'] : '100%';
		$theme = $settings['theme'] ?? 'light';

		// Allow layout override from shortcode/block attributes.
		if ( ! empty( $atts['layout'] ) ) {
			$settings['widget_layout'] = sanitize_text_field( $atts['layout'] );
		}

		$template = locate_template( 'woo-smart-search/search-widget.php' );
		if ( ! $template ) {
			$template = WSS_PLUGIN_DIR . 'templates/search-widget.php';
		}

		ob_start();
		include $template;
		$html = ob_get_clean();

		return apply_filters( 'wss_search_widget_html', $html, $atts );
	}

	/**
	 * Handle search-related redirects.
	 *
	 * 1) Native WooCommerce search (?s=query&post_type=product) → redirect to results page with ?q=
	 * 2) Results page with ?s= parameter → rewrite to ?q= to prevent WordPress search hijack
	 */
	public function handle_search_redirects() {
		$results_page_id = (int) wss_get_option( 'results_page_id', 0 );

		if ( ! $results_page_id || get_post_status( $results_page_id ) !== 'publish' ) {
			return;
		}

		// Case 1: Native WooCommerce search → redirect to our results page.
		if ( is_search() && get_query_var( 'post_type' ) === 'product' ) {
			$query       = get_search_query();
			$results_url = get_permalink( $results_page_id );
			$results_url = add_query_arg( 'q', rawurlencode( $query ), $results_url );

			wp_safe_redirect( $results_url, 302 );
			exit;
		}

		// Case 2: Someone lands on the results page with ?s= (from cache, bookmark,
		// or direct link). WordPress treats ?s= as a search query which breaks page
		// resolution. Rewrite to ?q= so the page loads normally.
		if ( is_page( $results_page_id ) ) {
			return; // Page resolved fine, nothing to do.
		}

		// WordPress may have failed to resolve the page because of ?s=.
		// Check if the current URL path matches the results page.
		$s_param = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $s_param ) ) {
			$current_path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$page_path    = wp_parse_url( get_permalink( $results_page_id ), PHP_URL_PATH );

			if ( $current_path && $page_path && trailingslashit( $current_path ) === trailingslashit( $page_path ) ) {
				$results_url = get_permalink( $results_page_id );
				$results_url = add_query_arg( 'q', rawurlencode( $s_param ), $results_url );

				wp_safe_redirect( $results_url, 302 );
				exit;
			}
		}
	}

	/**
	 * Check if current page has a shortcode or widget.
	 *
	 * @return bool
	 */
	private function has_shortcode_or_widget(): bool {
		global $post;

		if ( $post ) {
			if ( has_shortcode( $post->post_content, 'woo_smart_search' ) || has_shortcode( $post->post_content, 'woo_smart_search_results' ) ) {
				return true;
			}

			// Detect Gutenberg block.
			if ( function_exists( 'has_block' ) && has_block( 'woo-smart-search/search-bar', $post ) ) {
				return true;
			}
		}

		if ( is_active_widget( false, false, 'wss_search_widget' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Build the search URL template used by the JS widget.
	 *
	 * If a results page is configured, returns its permalink with {query} placeholder.
	 * Otherwise, returns the default WordPress search URL.
	 *
	 * @return string
	 */
	private static function get_search_url_template() {
		$results_page_id = (int) wss_get_option( 'results_page_id', 0 );
		if ( $results_page_id && get_post_status( $results_page_id ) === 'publish' ) {
			$base = get_permalink( $results_page_id );
			// Use 'q' instead of 's' to avoid WordPress search hijack.
			return add_query_arg( 'q', '{query}', $base );
		}
		return home_url( '/?s={query}&post_type=product' );
	}

	/**
	 * Darken a hex color by a percentage.
	 *
	 * @param string $hex    Hex color.
	 * @param int    $percent Percentage to darken.
	 * @return string
	 */
	/**
	 * Output preconnect/prefetch hints for the REST API endpoint.
	 */
	public function output_preconnect_hints() {
		// Preload the REST API discovery for faster first search.
		echo '<link rel="preconnect" href="' . esc_url( rest_url() ) . '" crossorigin />' . "\n";
	}

	/**
	 * Make plugin CSS non-render-blocking using media="print" swap technique.
	 *
	 * This prevents the CSS from blocking initial page render.
	 * The onload handler swaps media to "all" once the stylesheet is loaded.
	 *
	 * @param string $html   Link tag HTML.
	 * @param string $handle Style handle.
	 * @param string $href   Stylesheet URL.
	 * @param string $media  Media attribute value.
	 * @return string
	 */
	public function optimize_style_loading( $html, $handle, $href, $media ) {
		// Only defer results-page CSS (not needed until user navigates to results).
		// Keep search-widget CSS render-blocking since the widget is visible immediately.
		if ( 'wss-results-page' !== $handle ) {
			return $html;
		}

		// Use media="print" + onload swap for non-blocking CSS.
		$html = str_replace(
			"media='all'",
			"media='print' onload=\"this.media='all'\"",
			$html
		);

		// Add noscript fallback.
		$html .= '<noscript><link rel="stylesheet" href="' . esc_url( $href ) . '" media="all" /></noscript>' . "\n";

		return $html;
	}

	/**
	 * Add defer attribute to plugin scripts.
	 *
	 * @param string $tag    Script tag HTML.
	 * @param string $handle Script handle.
	 * @param string $src    Script URL.
	 * @return string
	 */
	public function add_defer_attribute( $tag, $handle, $src ) {
		if ( ! in_array( $handle, array( 'wss-search-widget', 'wss-results-page' ), true ) ) {
			return $tag;
		}

		// Skip if already has defer or async.
		if ( strpos( $tag, 'defer' ) !== false || strpos( $tag, 'async' ) !== false ) {
			return $tag;
		}

		return str_replace( ' src=', ' defer src=', $tag );
	}

	private static function darken_color( string $hex, int $percent ): string {
		$hex = ltrim( $hex, '#' );
		if ( strlen( $hex ) !== 6 ) {
			return '#' . $hex;
		}

		$r = max( 0, hexdec( substr( $hex, 0, 2 ) ) - ( 255 * $percent / 100 ) );
		$g = max( 0, hexdec( substr( $hex, 2, 2 ) ) - ( 255 * $percent / 100 ) );
		$b = max( 0, hexdec( substr( $hex, 4, 2 ) ) - ( 255 * $percent / 100 ) );

		return sprintf( '#%02x%02x%02x', (int) $r, (int) $g, (int) $b );
	}
}
