<?php
/**
 * Frontend logic.
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
	}

	/**
	 * Replace the native search form with the smart search widget.
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
		// Only load on pages that need it.
		$mode     = wss_get_option( 'integration_mode', 'replace' );
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
				'apiUrl'        => esc_url_raw( rest_url( 'wss/v1/search' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'maxResults'    => (int) ( $settings['max_autocomplete_results'] ?? 8 ),
				'debounceTime'  => (int) apply_filters( 'wss_debounce_time', 200 ),
				'minQueryLength' => 2,
				'showImage'     => ( $settings['show_image'] ?? 'yes' ) === 'yes',
				'showPrice'     => ( $settings['show_price'] ?? 'yes' ) === 'yes',
				'showCategory'  => ( $settings['show_category'] ?? 'yes' ) === 'yes',
				'showSku'       => ( $settings['show_sku'] ?? 'no' ) === 'yes',
				'showStock'     => ( $settings['show_stock'] ?? 'yes' ) === 'yes',
				'showRating'    => ( $settings['show_rating'] ?? 'no' ) === 'yes',
				'currency'      => get_woocommerce_currency(),
				'currencySymbol' => html_entity_decode( get_woocommerce_currency_symbol() ),
				'currencyPos'   => get_option( 'woocommerce_currency_pos', 'left' ),
				'decimals'      => (int) get_option( 'woocommerce_price_num_decimals', 2 ),
				'decimalSep'    => get_option( 'woocommerce_price_decimal_sep', '.' ),
				'thousandSep'   => get_option( 'woocommerce_price_thousand_sep', ',' ),
				'searchUrl'     => home_url( '/?s={query}&post_type=product' ),
				'placeholderImg' => WSS_PLUGIN_URL . 'assets/images/placeholder.svg',
				'i18n'          => array(
					'placeholder'  => ! empty( $settings['placeholder_text'] ) ? $settings['placeholder_text'] : __( 'Search products...', 'woo-smart-search' ),
					'noResults'    => __( 'No results found for', 'woo-smart-search' ),
					'viewAll'      => __( 'View all %d results', 'woo-smart-search' ),
					'error'        => __( 'Connection error, please try again', 'woo-smart-search' ),
					'inStock'      => __( 'In stock', 'woo-smart-search' ),
					'outOfStock'   => __( 'Out of stock', 'woo-smart-search' ),
					'onBackorder'  => __( 'On backorder', 'woo-smart-search' ),
					'clearSearch'  => __( 'Clear search', 'woo-smart-search' ),
				),
			)
		);

		// Custom CSS.
		$custom_css = $settings['custom_css'] ?? '';
		if ( ! empty( $custom_css ) ) {
			wp_add_inline_style( 'wss-search-widget', $custom_css );
		}
	}

	/**
	 * Output CSS custom properties in the head.
	 */
	public function output_css_variables() {
		$settings = get_option( 'wss_settings', array() );
		$theme    = $settings['theme'] ?? 'light';

		$vars = array(
			'--wss-primary'       => $settings['primary_color'] ?? '#2271b1',
			'--wss-bg'            => $settings['bg_color'] ?? '#ffffff',
			'--wss-text'          => $settings['text_color'] ?? '#1d2327',
			'--wss-border'        => $settings['border_color'] ?? '#c3c4c7',
			'--wss-font-size'     => ( $settings['font_size'] ?? '14' ) . 'px',
			'--wss-border-radius' => ( $settings['border_radius'] ?? '4' ) . 'px',
		);

		if ( 'dark' === $theme ) {
			$vars['--wss-bg']     = '#1d2327';
			$vars['--wss-text']   = '#f0f0f1';
			$vars['--wss-border'] = '#3c434a';
		}

		$css = ':root {';
		foreach ( $vars as $prop => $value ) {
			$css .= $prop . ':' . $value . ';';
		}
		$css .= '}';

		echo '<style id="wss-css-vars">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS variables are sanitized.
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

		// Allow template override.
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
	 * Check if current page has a shortcode or widget.
	 *
	 * @return bool
	 */
	private function has_shortcode_or_widget(): bool {
		global $post;

		if ( $post && has_shortcode( $post->post_content, 'woo_smart_search' ) ) {
			return true;
		}

		if ( is_active_widget( false, false, 'wss_search_widget' ) ) {
			return true;
		}

		return false;
	}
}
