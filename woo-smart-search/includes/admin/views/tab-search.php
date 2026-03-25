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

	<p class="submit">
		<button type="submit" class="button button-primary wss-save-settings">
			<?php esc_html_e( 'Save Settings', 'woo-smart-search' ); ?>
		</button>
		<span class="wss-status-message"></span>
	</p>
</form>
