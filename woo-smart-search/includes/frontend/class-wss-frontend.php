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

		// Enqueue search results page assets when on search.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_results_page_assets' ) );

		// Replace search results page content with faceted results.
		if ( 'replace' === $mode ) {
			add_filter( 'template_include', array( $this, 'maybe_replace_search_template' ) );
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

		$settings = get_option( 'wss_settings', array() );

		wp_localize_script(
			'wss-search-widget',
			'wssConfig',
			array(
				'apiUrl'         => esc_url_raw( rest_url( 'wss/v1/search' ) ),
				'popularUrl'     => esc_url_raw( rest_url( 'wss/v1/popular' ) ),
				'trackClickUrl'  => esc_url_raw( rest_url( 'wss/v1/track-click' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'maxResults'     => (int) ( $settings['max_autocomplete_results'] ?? 8 ),
				'debounceTime'   => (int) apply_filters( 'wss_debounce_time', 200 ),
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
				'searchUrl'      => home_url( '/?s={query}&post_type=product' ),
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
					'popularSearches'  => __( 'Popular searches', 'woo-smart-search' ),
					'suggestions'      => __( 'Suggestions', 'woo-smart-search' ),
				),
				'widgetLayout'   => $settings['widget_layout'] ?? 'standard',
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
	 */
	public function enqueue_results_page_assets() {
		if ( ! is_search() || get_query_var( 'post_type' ) !== 'product' ) {
			return;
		}

		$mode = wss_get_option( 'integration_mode', 'replace' );
		if ( 'replace' !== $mode ) {
			return;
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
		$settings = get_option( 'wss_settings', array() );
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
			$css .= $prop . ':' . $value . ';';
		}
		$css .= '}';

		echo '<style id="wss-css-vars">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get the search widget HTML.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function get_search_widget_html( $atts = array() ) {
		$settings    = get_option( 'wss_settings', array() );
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
	 * Replace the WooCommerce product search results template.
	 *
	 * Injects the faceted results page HTML via the_content filter
	 * instead of replacing the entire template file.
	 *
	 * @param string $template Current template path.
	 * @return string
	 */
	public function maybe_replace_search_template( $template ) {
		if ( ! is_search() || get_query_var( 'post_type' ) !== 'product' ) {
			return $template;
		}

		$custom_template = locate_template( 'woo-smart-search/search-results.php' );
		if ( ! $custom_template ) {
			$custom_template = WSS_PLUGIN_DIR . 'templates/search-results.php';
		}

		return $custom_template;
	}

	/**
	 * Check if current page has a shortcode or widget.
	 *
	 * @return bool
	 */
	private function has_shortcode_or_widget(): bool {
		global $post;

		if ( $post ) {
			if ( has_shortcode( $post->post_content, 'woo_smart_search' ) ) {
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
	 * Darken a hex color by a percentage.
	 *
	 * @param string $hex    Hex color.
	 * @param int    $percent Percentage to darken.
	 * @return string
	 */
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
