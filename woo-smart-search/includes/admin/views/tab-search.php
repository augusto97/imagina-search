<?php
/**
 * Search tab template.
 *
 * @package WooSmartSearch
 * @var array $settings Plugin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form id="wss-search-form" class="wss-form">
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="wss-max-results"><?php esc_html_e( 'Autocomplete Results', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="number" id="wss-max-results" name="max_autocomplete_results" value="<?php echo esc_attr( $settings['max_autocomplete_results'] ?? 8 ); ?>" class="small-text" min="1" max="20" />
				<p class="description"><?php esc_html_e( 'Maximum results shown in the autocomplete dropdown.', 'woo-smart-search' ); ?></p>
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
				$facet_options = array(
					'categories' => __( 'Categories', 'woo-smart-search' ),
					'price'      => __( 'Price', 'woo-smart-search' ),
					'stock'      => __( 'Stock', 'woo-smart-search' ),
					'attributes' => __( 'Attributes', 'woo-smart-search' ),
					'brands'     => __( 'Brands', 'woo-smart-search' ),
					'rating'     => __( 'Rating', 'woo-smart-search' ),
				);
				$visible_facets = $settings['visible_facets'] ?? array( 'categories', 'price', 'stock', 'attributes' );
				foreach ( $facet_options as $key => $label ) :
					?>
					<label style="display:block; margin-bottom:4px;">
						<input type="checkbox" name="visible_facets[]" value="<?php echo esc_attr( $key ); ?>" <?php echo in_array( $key, $visible_facets, true ) ? 'checked' : ''; ?> />
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</td>
		</tr>
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
