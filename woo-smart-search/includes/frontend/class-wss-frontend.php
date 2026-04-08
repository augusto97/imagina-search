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
		// Always load assets — lightweight CSS/JS that must be available for
		// shortcodes, widgets, and search-replace mode regardless of the page.
		// Conditional loading caused shortcodes on non-search pages to render
		// without styles because wp_head had already been output.

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

		// Direct Meilisearch connection for ultra-fast frontend search.
		$search_api_key     = $settings['search_api_key'] ?? '';
		$meili_url          = '';
		$meili_index        = wss_get_option( 'index_name', 'woo_products' );
		$local_endpoint_url = '';
		$engine_type        = wss_get_option( 'search_engine', 'meilisearch' );

		if ( 'local' === $engine_type ) {
			// Local engine: use the SHORTINIT search endpoint.
			$local_endpoint_url = plugins_url( 'search-endpoint.php', WSS_PLUGIN_FILE );
		} elseif ( ! empty( $search_api_key ) ) {
			$engine = wss_get_engine();
			if ( $engine ) {
				$meili_url = $engine->get_base_url();
			}
		}

		// Build default facets list based on content source mode.
		$content_source = wss_get_content_source();
		$is_ecom        = wss_is_ecommerce_mode();
		$is_mixed       = 'mixed' === $content_source;

		// Discover custom taxonomy and custom field facet keys.
		$custom_tax_facets = array();
		$custom_cf_facets  = array();
		$custom_tax_labels = array(); // key => label for frontend rendering.
		$custom_cf_labels  = array();

		if ( ! $is_ecom || $is_mixed ) {
			$wp_post_types       = $settings['wp_post_types'] ?? array( 'post' );
			$excluded_taxonomies = array( 'category', 'post_tag', 'product_cat', 'product_tag', 'post_format' );
			if ( ! empty( $wp_post_types ) && is_array( $wp_post_types ) ) {
				foreach ( $wp_post_types as $pt ) {
					$pt_taxonomies = get_object_taxonomies( $pt, 'objects' );
					foreach ( $pt_taxonomies as $tax_name => $tax_obj ) {
						if ( in_array( $tax_name, $excluded_taxonomies, true ) || ! $tax_obj->public ) {
							continue;
						}
						$key = 'tax_' . $tax_name;
						if ( ! isset( $custom_tax_labels[ $key ] ) ) {
							$custom_tax_facets[]       = $key;
							$custom_tax_labels[ $key ] = $tax_obj->label;
						}
					}
				}
			}

			$wp_custom_fields = $settings['wp_custom_fields'] ?? array();
			if ( ! empty( $wp_custom_fields ) && is_array( $wp_custom_fields ) ) {
				foreach ( $wp_custom_fields as $cf_key ) {
					$prefixed                   = 'cf_' . $cf_key;
					$custom_cf_facets[]         = $prefixed;
					$custom_cf_labels[ $prefixed ] = $cf_key;
				}
			}
		}

		if ( $is_ecom || $is_mixed ) {
			$default_facets = array( 'categories', 'tags', 'stock_status', 'on_sale', 'brand', 'rating' );
			if ( class_exists( 'WSS_REST_API' ) ) {
				$attr_names = WSS_REST_API::get_product_attribute_names();
				foreach ( $attr_names as $attr_name ) {
					$default_facets[] = 'attributes.' . $attr_name;
				}
			}
			if ( $is_mixed ) {
				$default_facets = array_merge( $default_facets, array( 'post_type', 'author' ), $custom_tax_facets, $custom_cf_facets );
			}
		} else {
			// WordPress content mode — no WC-specific facets.
			$default_facets = array_merge( array( 'categories', 'tags', 'post_type', 'author' ), $custom_tax_facets, $custom_cf_facets );
		}

		wp_localize_script(
			'wss-search-widget',
			'wssConfig',
			array(
				// Engine type: 'meilisearch' or 'local'.
				'engineType'     => $engine_type,
				// Local engine search endpoint (SHORTINIT ultra-fast).
				'localSearchUrl' => esc_url_raw( $local_endpoint_url ),
				// Direct Meilisearch (ultra-fast mode) — used when search_api_key is set.
				'meiliUrl'       => esc_url_raw( $meili_url ),
				'meiliKey'       => $search_api_key,
				'meiliIndex'     => $meili_index,
				'meilieFacets'   => $default_facets,
				// WordPress REST API fallback.
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
				'showExcerpt'    => ( $settings['show_excerpt'] ?? 'yes' ) === 'yes',
				'showAuthor'     => ( $settings['show_author'] ?? 'yes' ) === 'yes',
				'showDate'       => ( $settings['show_date'] ?? 'yes' ) === 'yes',
				'showPostType'   => ( $settings['show_post_type'] ?? 'no' ) === 'yes',
				'theme'          => $settings['theme'] ?? 'light',
				'contentSource'  => wss_get_content_source(),
				'isMixed'       => 'mixed' === wss_get_content_source(),
				'isEcommerce'   => wss_is_ecommerce_mode(),
				'currency'       => wss_is_ecommerce_mode() ? get_woocommerce_currency() : '',
				'currencySymbol' => wss_is_ecommerce_mode() ? html_entity_decode( get_woocommerce_currency_symbol() ) : '',
				'currencyPos'    => wss_is_ecommerce_mode() ? get_option( 'woocommerce_currency_pos', 'left' ) : 'left',
				'decimals'       => wss_is_ecommerce_mode() ? (int) get_option( 'woocommerce_price_num_decimals', 2 ) : 2,
				'decimalSep'     => wss_is_ecommerce_mode() ? get_option( 'woocommerce_price_decimal_sep', '.' ) : '.',
				'thousandSep'    => wss_is_ecommerce_mode() ? get_option( 'woocommerce_price_thousand_sep', ',' ) : ',',
				'searchUrl'      => self::get_search_url_template(),
				'placeholderImg' => WSS_PLUGIN_URL . 'assets/images/placeholder.svg',
				'i18n'           => self::get_frontend_i18n( $settings ),
				'widgetLayout'   => $settings['widget_layout'] ?? 'standard',
				'resultsLayout'  => $settings['results_layout'] ?? 'default',
				'resultsColumns' => (int) ( $settings['results_columns'] ?? 3 ),
				'resultsPerPage' => (int) ( $settings['results_per_page'] ?? 20 ),
				'rpImageRatio'   => $settings['rp_image_ratio'] ?? '1:1',
				'rpImageFit'     => $settings['rp_image_fit'] ?? 'cover',
				'rpCardShadow'   => $settings['rp_card_shadow'] ?? 'medium',
				'visibleFacets'  => implode( ',', $settings['visible_facets'] ?? (
				$is_ecom ? array( 'categories', 'tags', 'price', 'stock', 'attributes' ) :
				( $is_mixed ? array( 'categories', 'tags', 'price', 'stock', 'attributes', 'post_type', 'author' ) :
				array( 'categories', 'tags', 'post_type', 'author' ) )
			) ),
				'customFacetLabels' => array_merge( $custom_tax_labels, $custom_cf_labels ),
			)
		);

		// Custom CSS.
		$custom_css = $settings['custom_css'] ?? '';
		if ( ! empty( $custom_css ) ) {
			wp_add_inline_style( 'wss-search-widget', $custom_css );
		}

		// Results page custom CSS (added to widget stylesheet so it's always available).
		$rp_custom_css = $settings['rp_custom_css'] ?? '';
		if ( ! empty( $rp_custom_css ) ) {
			wp_add_inline_style( 'wss-search-widget', $rp_custom_css );
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

			$is_search = is_search();
			if ( wss_is_ecommerce_mode() ) {
				$is_search = $is_search && get_query_var( 'post_type' ) === 'product';
			}
			if ( ! $is_results_page && ! $is_search ) {
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
				// Results page variables.
				'--wss-rp-card-bg'      => $settings['rp_card_bg'] ?? '#ffffff',
				'--wss-rp-card-border'  => $settings['rp_card_border'] ?? '#e5e7eb',
				'--wss-rp-card-radius'  => ( $settings['rp_card_radius'] ?? '8' ) . 'px',
				'--wss-rp-price-color'  => $settings['rp_price_color'] ?? '#1f2937',
				'--wss-rp-sale-color'   => $settings['rp_sale_color'] ?? '#dc2626',
				'--wss-rp-badge-bg'     => $settings['rp_badge_bg'] ?? '#ef4444',
				'--wss-rp-badge-text'   => $settings['rp_badge_text'] ?? '#ffffff',
				'--wss-rp-stars-color'  => $settings['rp_stars_color'] ?? '#f59e0b',
				'--wss-rp-button-bg'    => $settings['rp_button_bg'] ?? '#2563eb',
				'--wss-rp-button-text'  => $settings['rp_button_text'] ?? '#ffffff',
				'--wss-rp-sidebar-bg'   => $settings['rp_sidebar_bg'] ?? '#ffffff',
				'--wss-rp-toolbar-bg'   => $settings['rp_toolbar_bg'] ?? '#ffffff',
				'--wss-rp-page-bg'      => $settings['rp_page_bg'] ?? '#f9fafb',
				'--wss-rp-card-gap'     => ( $settings['rp_card_gap'] ?? '20' ) . 'px',
				'--wss-rp-name-size'    => ( $settings['rp_name_size'] ?? '14' ) . 'px',
				'--wss-rp-price-size'   => ( $settings['rp_price_size'] ?? '16' ) . 'px',
				'--wss-rp-name-lines'   => $settings['rp_name_lines'] ?? '2',
				'--wss-rp-columns'      => $settings['results_columns'] ?? '3',
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

		// Case 1: Native search → redirect to our results page.
		$should_redirect = is_search();
		if ( wss_is_ecommerce_mode() ) {
			$should_redirect = $should_redirect && get_query_var( 'post_type' ) === 'product';
		}
		if ( $should_redirect ) {
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
	 * Build the frontend i18n array with admin translation overrides.
	 *
	 * @param array $settings Plugin settings.
	 * @return array
	 */
	public static function get_frontend_i18n( array $settings ): array {
		$t    = $settings['translations'] ?? array();
		$ecom = wss_is_ecommerce_mode();

		return array(
			'placeholder'      => ! empty( $t['placeholder'] )
				? $t['placeholder']
				: ( ! empty( $settings['placeholder_text'] )
					? $settings['placeholder_text']
					: ( $ecom ? __( 'Search products...', 'woo-smart-search' ) : __( 'Search...', 'woo-smart-search' ) ) ),
			'noResults'        => ! empty( $t['noResults'] ) ? $t['noResults'] : __( 'No results found for', 'woo-smart-search' ),
			'viewAll'          => ! empty( $t['viewAll'] ) ? $t['viewAll'] : __( 'View all %d results', 'woo-smart-search' ),
			'viewAllResults'   => ! empty( $t['viewAllResults'] ) ? $t['viewAllResults'] : __( 'View all results', 'woo-smart-search' ),
			'error'            => ! empty( $t['error'] ) ? $t['error'] : __( 'Connection error, please try again', 'woo-smart-search' ),
			'inStock'          => ! empty( $t['inStock'] ) ? $t['inStock'] : __( 'In stock', 'woo-smart-search' ),
			'outOfStock'       => ! empty( $t['outOfStock'] ) ? $t['outOfStock'] : __( 'Out of stock', 'woo-smart-search' ),
			'onBackorder'      => ! empty( $t['onBackorder'] ) ? $t['onBackorder'] : __( 'On backorder', 'woo-smart-search' ),
			'clearSearch'      => ! empty( $t['clearSearch'] ) ? $t['clearSearch'] : __( 'Clear search', 'woo-smart-search' ),
			'close'            => ! empty( $t['close'] ) ? $t['close'] : __( 'Close', 'woo-smart-search' ),
			'popularSearches'  => ! empty( $t['popularSearches'] ) ? $t['popularSearches'] : __( 'Popular', 'woo-smart-search' ),
			'suggestions'      => ! empty( $t['suggestions'] ) ? $t['suggestions'] : __( 'Suggestions', 'woo-smart-search' ),
			'products'         => ! empty( $t['products'] ) ? $t['products'] : ( $ecom ? __( 'Products', 'woo-smart-search' ) : __( 'Results', 'woo-smart-search' ) ),
			'results'          => ! empty( $t['results'] ) ? $t['results'] : __( 'Results', 'woo-smart-search' ),
			'content'          => ! empty( $t['content'] ) ? $t['content'] : __( 'Content', 'woo-smart-search' ),
			'categories'       => ! empty( $t['categories'] ) ? $t['categories'] : __( 'Categories', 'woo-smart-search' ),
			'startTyping'      => ! empty( $t['startTyping'] ) ? $t['startTyping']
				: ( $ecom ? __( 'Start typing to search products...', 'woo-smart-search' ) : __( 'Start typing to search...', 'woo-smart-search' ) ),
			'searchOurStore'   => ! empty( $t['searchOurStore'] ) ? $t['searchOurStore'] : __( 'Search our store', 'woo-smart-search' ),
			'collections'      => ! empty( $t['collections'] ) ? $t['collections'] : __( 'Collections', 'woo-smart-search' ),
			'brands'           => ! empty( $t['brands'] ) ? $t['brands'] : __( 'Brands', 'woo-smart-search' ),
			'relatedBrands'    => ! empty( $t['relatedBrands'] ) ? $t['relatedBrands'] : __( 'Related Brands', 'woo-smart-search' ),
			'relatedCategories' => ! empty( $t['relatedCategories'] ) ? $t['relatedCategories'] : __( 'Related Categories', 'woo-smart-search' ),
			'filters'          => ! empty( $t['filters'] ) ? $t['filters'] : __( 'Filters', 'woo-smart-search' ),
			'resultsFor'       => ! empty( $t['resultsFor'] ) ? $t['resultsFor'] : __( 'Results for "%s"', 'woo-smart-search' ),
			'noResultsPage'    => ! empty( $t['noResultsPage'] ) ? $t['noResultsPage'] : __( 'No results found matching your search.', 'woo-smart-search' ),
			'sortRelevance'    => ! empty( $t['sortRelevance'] ) ? $t['sortRelevance'] : __( 'Relevance', 'woo-smart-search' ),
			'sortPriceLow'     => ! empty( $t['sortPriceLow'] ) ? $t['sortPriceLow'] : __( 'Price: Low to High', 'woo-smart-search' ),
			'sortPriceHigh'    => ! empty( $t['sortPriceHigh'] ) ? $t['sortPriceHigh'] : __( 'Price: High to Low', 'woo-smart-search' ),
			'sortNewest'       => ! empty( $t['sortNewest'] ) ? $t['sortNewest'] : __( 'Newest', 'woo-smart-search' ),
			'sortPopular'      => ! empty( $t['sortPopular'] ) ? $t['sortPopular'] : __( 'Most Popular', 'woo-smart-search' ),
			'sortRating'       => ! empty( $t['sortRating'] ) ? $t['sortRating'] : __( 'Best Rated', 'woo-smart-search' ),
			'sortNameAZ'       => ! empty( $t['sortNameAZ'] ) ? $t['sortNameAZ'] : __( 'Name: A–Z', 'woo-smart-search' ),
			'sortNameZA'       => ! empty( $t['sortNameZA'] ) ? $t['sortNameZA'] : __( 'Name: Z–A', 'woo-smart-search' ),
			'addToCart'        => ! empty( $t['addToCart'] ) ? $t['addToCart'] : __( 'Add to Cart', 'woo-smart-search' ),
			'freeShipping'     => ! empty( $t['freeShipping'] ) ? $t['freeShipping'] : __( 'Free shipping', 'woo-smart-search' ),
			'sold'             => ! empty( $t['sold'] ) ? $t['sold'] : __( 'sold', 'woo-smart-search' ),
			// Facet / filter labels.
			'tags'             => ! empty( $t['tags'] ) ? $t['tags'] : __( 'Tags', 'woo-smart-search' ),
			'stock'            => ! empty( $t['stock'] ) ? $t['stock'] : __( 'Stock', 'woo-smart-search' ),
			'brand'            => ! empty( $t['brand'] ) ? $t['brand'] : __( 'Brand', 'woo-smart-search' ),
			'rating'           => ! empty( $t['rating'] ) ? $t['rating'] : __( 'Rating', 'woo-smart-search' ),
			'price'            => ! empty( $t['price'] ) ? $t['price'] : __( 'Price', 'woo-smart-search' ),
			'contentType'      => ! empty( $t['contentType'] ) ? $t['contentType'] : __( 'Content Type', 'woo-smart-search' ),
			'author'           => ! empty( $t['author'] ) ? $t['author'] : __( 'Author', 'woo-smart-search' ),
			'onSale'           => ! empty( $t['onSale'] ) ? $t['onSale'] : __( 'On Sale', 'woo-smart-search' ),
			'priceMin'         => ! empty( $t['priceMin'] ) ? $t['priceMin'] : __( 'Min', 'woo-smart-search' ),
			'priceMax'         => ! empty( $t['priceMax'] ) ? $t['priceMax'] : __( 'Max', 'woo-smart-search' ),
			'clearAll'         => ! empty( $t['clearAll'] ) ? $t['clearAll'] : __( 'Clear all', 'woo-smart-search' ),
			// Results count patterns.
			'xResults'         => ! empty( $t['xResults'] ) ? $t['xResults'] : __( '%d results', 'woo-smart-search' ),
			'xProducts'        => ! empty( $t['xProducts'] ) ? $t['xProducts'] : __( '%d products', 'woo-smart-search' ),
			'errorLoading'     => ! empty( $t['errorLoading'] ) ? $t['errorLoading'] : __( 'Error loading results. Please try again.', 'woo-smart-search' ),
		);
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
		if ( wss_is_ecommerce_mode() ) {
			return home_url( '/?s={query}&post_type=product' );
		}
		return home_url( '/?s={query}' );
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
		// Preconnect to Meilisearch directly if search API key is configured (ultra-fast mode).
		$search_api_key = wss_get_option( 'search_api_key', '' );
		if ( ! empty( $search_api_key ) ) {
			$engine = wss_get_engine();
			if ( $engine ) {
				echo '<link rel="preconnect" href="' . esc_url( $engine->get_base_url() ) . '" crossorigin />' . "\n";
			}
		}
		// Also preconnect to REST API (for analytics, popular searches, fallback).
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
