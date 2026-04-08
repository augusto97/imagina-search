<?php
/**
 * Results Page tab template.
 *
 * Controls the search results page settings: page selector, results per page,
 * facets configuration, search behavior, cache, rate limits, and synonyms.
 *
 * @package WooSmartSearch
 * @var array $settings Plugin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form id="wss-search-form" class="wss-form">
	<input type="hidden" name="_wss_tab" value="search" />
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="wss-results-page"><?php esc_html_e( 'Search Results Page', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<?php
				$results_page_id = $settings['results_page_id'] ?? 0;
				wp_dropdown_pages(
					array(
						'name'             => 'results_page_id',
						'id'               => 'wss-results-page',
						'sort_column'      => 'menu_order',
						'sort_order'       => 'ASC',
						'show_option_none' => __( '— Select a page —', 'woo-smart-search' ),
						'option_none_value' => '0',
						'selected'         => $results_page_id,
						'echo'             => true,
						'post_status'      => 'publish,draft,private',
					)
				);
				?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: shortcode */
						esc_html__( 'Select the page that contains the %s shortcode. This page will display the faceted search results.', 'woo-smart-search' ),
						'<code>[woo_smart_search_results]</code>'
					);
					?>
				</p>
				<?php if ( $results_page_id ) : ?>
					<p>
						<a href="<?php echo esc_url( get_edit_post_link( $results_page_id ) ); ?>" class="button button-small">
							<?php esc_html_e( 'Edit Page', 'woo-smart-search' ); ?>
						</a>
						<a href="<?php echo esc_url( get_permalink( $results_page_id ) ); ?>" class="button button-small" target="_blank">
							<?php esc_html_e( 'View Page', 'woo-smart-search' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-results-layout"><?php esc_html_e( 'Results Page Layout', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<select id="wss-results-layout" name="results_layout">
					<option value="default" <?php selected( $settings['results_layout'] ?? 'default', 'default' ); ?>><?php esc_html_e( 'Default — Clean grid with sidebar filters', 'woo-smart-search' ); ?></option>
					<option value="amazon" <?php selected( $settings['results_layout'] ?? '', 'amazon' ); ?>><?php esc_html_e( 'Amazon — Prominent ratings, compact cards, Add to Cart buttons', 'woo-smart-search' ); ?></option>
					<option value="temu" <?php selected( $settings['results_layout'] ?? '', 'temu' ); ?>><?php esc_html_e( 'Temu — Vibrant discount badges, large images, dense grid', 'woo-smart-search' ); ?></option>
					<option value="mercadolibre" <?php selected( $settings['results_layout'] ?? '', 'mercadolibre' ); ?>><?php esc_html_e( 'MercadoLibre — List-first view, spacious cards, shipping badges', 'woo-smart-search' ); ?></option>
					<option value="aliexpress" <?php selected( $settings['results_layout'] ?? '', 'aliexpress' ); ?>><?php esc_html_e( 'AliExpress — Dense multi-column grid, orders count, big discounts', 'woo-smart-search' ); ?></option>
					<option value="shopify" <?php selected( $settings['results_layout'] ?? '', 'shopify' ); ?>><?php esc_html_e( 'Shopify — Minimal, elegant, wide spacing, large images', 'woo-smart-search' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Choose the visual style for the search results page. Each layout is inspired by major e-commerce platforms.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-results-columns"><?php esc_html_e( 'Grid Columns', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<select id="wss-results-columns" name="results_columns">
					<option value="2" <?php selected( $settings['results_columns'] ?? '3', '2' ); ?>>2</option>
					<option value="3" <?php selected( $settings['results_columns'] ?? '3', '3' ); ?>>3</option>
					<option value="4" <?php selected( $settings['results_columns'] ?? '3', '4' ); ?>>4</option>
					<option value="5" <?php selected( $settings['results_columns'] ?? '3', '5' ); ?>>5</option>
				</select>
				<p class="description"><?php esc_html_e( 'Number of columns in grid view on desktop.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-per-page"><?php esc_html_e( 'Results Per Page', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="number" id="wss-per-page" name="results_per_page" value="<?php echo esc_attr( $settings['results_per_page'] ?? 20 ); ?>" class="small-text" min="1" max="100" />
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Faceted Filters', 'woo-smart-search' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="enable_facets" value="yes" <?php checked( $settings['enable_facets'] ?? 'yes', 'yes' ); ?> />
					<?php esc_html_e( 'Enable faceted filters in search results', 'woo-smart-search' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Visible Facets', 'woo-smart-search' ); ?></th>
			<td>
				<?php
				$is_ecommerce_tab = wss_is_ecommerce_mode();
				$is_mixed_tab     = 'mixed' === wss_get_content_source();

				// Common facets.
				$facet_options = array(
					'categories' => __( 'Categories', 'woo-smart-search' ),
					'tags'       => __( 'Tags', 'woo-smart-search' ),
				);

				// WooCommerce-specific facets.
				if ( $is_ecommerce_tab || $is_mixed_tab ) {
					$facet_options['price']      = __( 'Price', 'woo-smart-search' );
					$facet_options['stock']      = __( 'Stock', 'woo-smart-search' );
					$facet_options['attributes'] = __( 'Attributes', 'woo-smart-search' );
					$facet_options['brands']     = __( 'Brands', 'woo-smart-search' );
					$facet_options['rating']     = __( 'Rating', 'woo-smart-search' );
				}

				// WordPress-specific facets.
				if ( ! $is_ecommerce_tab || $is_mixed_tab ) {
					$facet_options['post_type'] = __( 'Post Type', 'woo-smart-search' );
					$facet_options['author']    = __( 'Author', 'woo-smart-search' );

					// Custom taxonomies — discover from configured post types.
					$wp_post_types = $settings['wp_post_types'] ?? array( 'post' );
					$excluded_taxonomies = array( 'category', 'post_tag', 'product_cat', 'product_tag', 'post_format' );
					$custom_tax_found = array();
					foreach ( $wp_post_types as $pt ) {
						$pt_taxonomies = get_object_taxonomies( $pt, 'objects' );
						foreach ( $pt_taxonomies as $tax_name => $tax_obj ) {
							if ( in_array( $tax_name, $excluded_taxonomies, true ) || ! $tax_obj->public ) {
								continue;
							}
							$custom_tax_found[ $tax_name ] = $tax_obj->label;
						}
					}
					foreach ( $custom_tax_found as $tax_name => $tax_label ) {
						$facet_options[ 'tax_' . $tax_name ] = $tax_label;
					}
				}

				// Custom fields configured for indexing.
				$wp_custom_fields = $settings['wp_custom_fields'] ?? array();
				if ( ! empty( $wp_custom_fields ) ) {
					foreach ( $wp_custom_fields as $cf_key ) {
						$facet_options[ 'cf_' . $cf_key ] = sprintf(
							/* translators: %s: custom field key */
							__( 'Field: %s', 'woo-smart-search' ),
							$cf_key
						);
					}
				}

				$visible_facets = $settings['visible_facets'] ?? array( 'categories', 'price', 'stock', 'attributes' );
				foreach ( $facet_options as $key => $label ) :
					?>
					<label style="display:block; margin-bottom:4px;">
						<input type="checkbox" name="visible_facets[]" value="<?php echo esc_attr( $key ); ?>" <?php echo in_array( $key, $visible_facets, true ) ? 'checked' : ''; ?> />
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Select which filter facets to show in the results page sidebar.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<?php if ( $is_ecommerce_tab || $is_mixed_tab ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Search by SKU', 'woo-smart-search' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="search_by_sku" value="yes" <?php checked( $settings['search_by_sku'] ?? 'yes', 'yes' ); ?> />
					<?php esc_html_e( 'Include SKU in searchable fields', 'woo-smart-search' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Out of Stock Results', 'woo-smart-search' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="show_out_of_stock_results" value="yes" <?php checked( $settings['show_out_of_stock_results'] ?? 'yes', 'yes' ); ?> />
					<?php esc_html_e( 'Show out-of-stock products in search results', 'woo-smart-search' ); ?>
				</label>
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<th scope="row">
				<label for="wss-cache-ttl"><?php esc_html_e( 'Cache TTL (seconds)', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="number" id="wss-cache-ttl" name="cache_ttl" value="<?php echo esc_attr( $settings['cache_ttl'] ?? 300 ); ?>" class="small-text" min="0" max="3600" />
				<p class="description"><?php esc_html_e( 'How long to cache search results. 0 to disable.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rate-limit"><?php esc_html_e( 'Rate Limit', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="number" id="wss-rate-limit" name="rate_limit" value="<?php echo esc_attr( $settings['rate_limit'] ?? 30 ); ?>" class="small-text" min="1" max="200" />
				<p class="description"><?php esc_html_e( 'Max requests per minute per IP.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-synonyms"><?php esc_html_e( 'Synonyms', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<textarea id="wss-synonyms" name="synonyms" rows="5" class="large-text code"><?php echo esc_textarea( $settings['synonyms'] ?? '' ); ?></textarea>
				<p class="description"><?php esc_html_e( 'JSON format: {"hoodie": ["sweatshirt", "pullover"], "phone": ["mobile", "cell"]}', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-stop-words"><?php esc_html_e( 'Stop Words', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<textarea id="wss-stop-words" name="stop_words" rows="3" class="large-text code"><?php echo esc_textarea( $settings['stop_words'] ?? '' ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Comma-separated list of words to ignore in searches.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
	</table>

	<h2 class="title"><?php esc_html_e( 'Results Page Appearance', 'woo-smart-search' ); ?></h2>
	<p class="description" style="margin-bottom: 15px;"><?php esc_html_e( 'Customize colors and styles for the search results page. These settings are independent from the search widget.', 'woo-smart-search' ); ?></p>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="wss-rp-card-bg"><?php esc_html_e( 'Card Background', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="color" id="wss-rp-card-bg" name="rp_card_bg" value="<?php echo esc_attr( $settings['rp_card_bg'] ?? '#ffffff' ); ?>" />
				<p class="description"><?php esc_html_e( 'Background color for product/post cards.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-card-border"><?php esc_html_e( 'Card Border Color', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="color" id="wss-rp-card-border" name="rp_card_border" value="<?php echo esc_attr( $settings['rp_card_border'] ?? '#e5e7eb' ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-card-radius"><?php esc_html_e( 'Card Border Radius (px)', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="number" id="wss-rp-card-radius" name="rp_card_radius" value="<?php echo esc_attr( $settings['rp_card_radius'] ?? '8' ); ?>" class="small-text" min="0" max="30" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-card-shadow"><?php esc_html_e( 'Card Hover Shadow', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<select id="wss-rp-card-shadow" name="rp_card_shadow">
					<option value="none" <?php selected( $settings['rp_card_shadow'] ?? 'medium', 'none' ); ?>><?php esc_html_e( 'None', 'woo-smart-search' ); ?></option>
					<option value="subtle" <?php selected( $settings['rp_card_shadow'] ?? '', 'subtle' ); ?>><?php esc_html_e( 'Subtle', 'woo-smart-search' ); ?></option>
					<option value="medium" <?php selected( $settings['rp_card_shadow'] ?? 'medium', 'medium' ); ?>><?php esc_html_e( 'Medium', 'woo-smart-search' ); ?></option>
					<option value="strong" <?php selected( $settings['rp_card_shadow'] ?? '', 'strong' ); ?>><?php esc_html_e( 'Strong', 'woo-smart-search' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-price-color"><?php esc_html_e( 'Price Color', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="color" id="wss-rp-price-color" name="rp_price_color" value="<?php echo esc_attr( $settings['rp_price_color'] ?? '#1f2937' ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-sale-color"><?php esc_html_e( 'Sale Price Color', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="color" id="wss-rp-sale-color" name="rp_sale_color" value="<?php echo esc_attr( $settings['rp_sale_color'] ?? '#dc2626' ); ?>" />
				<p class="description"><?php esc_html_e( 'Color for discounted prices.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-badge-bg"><?php esc_html_e( 'Sale Badge Background', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="color" id="wss-rp-badge-bg" name="rp_badge_bg" value="<?php echo esc_attr( $settings['rp_badge_bg'] ?? '#ef4444' ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-badge-text"><?php esc_html_e( 'Sale Badge Text', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="color" id="wss-rp-badge-text" name="rp_badge_text" value="<?php echo esc_attr( $settings['rp_badge_text'] ?? '#ffffff' ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-stars-color"><?php esc_html_e( 'Rating Stars Color', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="color" id="wss-rp-stars-color" name="rp_stars_color" value="<?php echo esc_attr( $settings['rp_stars_color'] ?? '#f59e0b' ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-button-bg"><?php esc_html_e( 'Button Background', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="color" id="wss-rp-button-bg" name="rp_button_bg" value="<?php echo esc_attr( $settings['rp_button_bg'] ?? '#2563eb' ); ?>" />
				<p class="description"><?php esc_html_e( 'Background for Add to Cart and action buttons (layouts that include them).', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-button-text"><?php esc_html_e( 'Button Text Color', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="color" id="wss-rp-button-text" name="rp_button_text" value="<?php echo esc_attr( $settings['rp_button_text'] ?? '#ffffff' ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-sidebar-bg"><?php esc_html_e( 'Sidebar Background', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="color" id="wss-rp-sidebar-bg" name="rp_sidebar_bg" value="<?php echo esc_attr( $settings['rp_sidebar_bg'] ?? '#ffffff' ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-toolbar-bg"><?php esc_html_e( 'Toolbar Background', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="color" id="wss-rp-toolbar-bg" name="rp_toolbar_bg" value="<?php echo esc_attr( $settings['rp_toolbar_bg'] ?? '#ffffff' ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-page-bg"><?php esc_html_e( 'Page Background', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="color" id="wss-rp-page-bg" name="rp_page_bg" value="<?php echo esc_attr( $settings['rp_page_bg'] ?? '#f9fafb' ); ?>" />
				<p class="description"><?php esc_html_e( 'Background color for the entire results page area.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-image-ratio"><?php esc_html_e( 'Image Aspect Ratio', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<select id="wss-rp-image-ratio" name="rp_image_ratio">
					<option value="1:1" <?php selected( $settings['rp_image_ratio'] ?? '1:1', '1:1' ); ?>>1:1 — <?php esc_html_e( 'Square', 'woo-smart-search' ); ?></option>
					<option value="4:3" <?php selected( $settings['rp_image_ratio'] ?? '', '4:3' ); ?>>4:3 — <?php esc_html_e( 'Landscape', 'woo-smart-search' ); ?></option>
					<option value="3:4" <?php selected( $settings['rp_image_ratio'] ?? '', '3:4' ); ?>>3:4 — <?php esc_html_e( 'Portrait', 'woo-smart-search' ); ?></option>
					<option value="16:9" <?php selected( $settings['rp_image_ratio'] ?? '', '16:9' ); ?>>16:9 — <?php esc_html_e( 'Wide', 'woo-smart-search' ); ?></option>
					<option value="auto" <?php selected( $settings['rp_image_ratio'] ?? '', 'auto' ); ?>><?php esc_html_e( 'Auto — Original ratio', 'woo-smart-search' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-image-fit"><?php esc_html_e( 'Image Fit', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<select id="wss-rp-image-fit" name="rp_image_fit">
					<option value="cover" <?php selected( $settings['rp_image_fit'] ?? 'cover', 'cover' ); ?>><?php esc_html_e( 'Cover — Fill area, crop edges', 'woo-smart-search' ); ?></option>
					<option value="contain" <?php selected( $settings['rp_image_fit'] ?? '', 'contain' ); ?>><?php esc_html_e( 'Contain — Fit entire image, may show background', 'woo-smart-search' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-card-gap"><?php esc_html_e( 'Card Spacing (px)', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="number" id="wss-rp-card-gap" name="rp_card_gap" value="<?php echo esc_attr( $settings['rp_card_gap'] ?? '20' ); ?>" class="small-text" min="0" max="48" />
				<p class="description"><?php esc_html_e( 'Gap between product cards in the grid.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-name-size"><?php esc_html_e( 'Product Name Size (px)', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="number" id="wss-rp-name-size" name="rp_name_size" value="<?php echo esc_attr( $settings['rp_name_size'] ?? '14' ); ?>" class="small-text" min="10" max="24" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-price-size"><?php esc_html_e( 'Price Size (px)', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="number" id="wss-rp-price-size" name="rp_price_size" value="<?php echo esc_attr( $settings['rp_price_size'] ?? '16' ); ?>" class="small-text" min="10" max="32" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-name-lines"><?php esc_html_e( 'Product Name Lines', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<select id="wss-rp-name-lines" name="rp_name_lines">
					<option value="1" <?php selected( $settings['rp_name_lines'] ?? '2', '1' ); ?>>1</option>
					<option value="2" <?php selected( $settings['rp_name_lines'] ?? '2', '2' ); ?>>2</option>
					<option value="3" <?php selected( $settings['rp_name_lines'] ?? '2', '3' ); ?>>3</option>
				</select>
				<p class="description"><?php esc_html_e( 'Max lines to show for product names before truncating.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-rp-custom-css"><?php esc_html_e( 'Results Page Custom CSS', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<textarea id="wss-rp-custom-css" name="rp_custom_css" rows="5" class="large-text code"><?php echo esc_textarea( $settings['rp_custom_css'] ?? '' ); ?></textarea>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary wss-save-settings">
			<?php esc_html_e( 'Save Settings', 'woo-smart-search' ); ?>
		</button>
		<span class="wss-status-message"></span>
	</p>
</form>
