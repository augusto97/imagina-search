<?php
/**
 * Translations tab template.
 *
 * Allows customizing all user-facing strings from the admin panel.
 *
 * @package WooSmartSearch
 * @var array $settings Plugin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$t = $settings['translations'] ?? array();

if ( ! function_exists( 'wss_tr' ) ) {
	/**
	 * Helper to get a translation value or empty string (shows placeholder).
	 */
	function wss_tr( $translations, $key ) {
		return isset( $translations[ $key ] ) ? $translations[ $key ] : '';
	}
}
?>
<form id="wss-translations-form" class="wss-form">
	<input type="hidden" name="_wss_tab" value="translations" />

	<p class="description" style="margin-bottom: 20px;">
		<?php esc_html_e( 'Customize all frontend text. Leave a field empty to use the default value shown as placeholder.', 'woo-smart-search' ); ?>
	</p>

	<!-- Search Widget -->
	<h2><?php esc_html_e( 'Search Widget', 'woo-smart-search' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="wss-tr-placeholder"><?php esc_html_e( 'Placeholder', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-placeholder" name="translations[placeholder]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'placeholder' ) ); ?>" placeholder="Search products..." /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-noResults"><?php esc_html_e( 'No results', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-noResults" name="translations[noResults]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'noResults' ) ); ?>" placeholder="No results found for" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-viewAll"><?php esc_html_e( 'View all (with count)', 'woo-smart-search' ); ?></label></th>
			<td>
				<input type="text" id="wss-tr-viewAll" name="translations[viewAll]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'viewAll' ) ); ?>" placeholder="View all %d results" />
				<p class="description"><?php esc_html_e( 'Use %d for the result count.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-viewAllResults"><?php esc_html_e( 'View all (no count)', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-viewAllResults" name="translations[viewAllResults]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'viewAllResults' ) ); ?>" placeholder="View all results" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-error"><?php esc_html_e( 'Connection error', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-error" name="translations[error]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'error' ) ); ?>" placeholder="Connection error, please try again" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-startTyping"><?php esc_html_e( 'Start typing hint', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-startTyping" name="translations[startTyping]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'startTyping' ) ); ?>" placeholder="Start typing to search products..." /></td>
		</tr>
	</table>

	<!-- Section Headers -->
	<h2><?php esc_html_e( 'Section Headers', 'woo-smart-search' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="wss-tr-products"><?php esc_html_e( 'Products', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-products" name="translations[products]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'products' ) ); ?>" placeholder="Products" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-results"><?php esc_html_e( 'Results', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-results" name="translations[results]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'results' ) ); ?>" placeholder="Results" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-content"><?php esc_html_e( 'Content', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-content" name="translations[content]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'content' ) ); ?>" placeholder="Content" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-categories"><?php esc_html_e( 'Categories', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-categories" name="translations[categories]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'categories' ) ); ?>" placeholder="Categories" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-popularSearches"><?php esc_html_e( 'Popular searches', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-popularSearches" name="translations[popularSearches]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'popularSearches' ) ); ?>" placeholder="Popular" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-suggestions"><?php esc_html_e( 'Suggestions', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-suggestions" name="translations[suggestions]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'suggestions' ) ); ?>" placeholder="Suggestions" /></td>
		</tr>
	</table>

	<!-- Product Details -->
	<h2><?php esc_html_e( 'Product Details', 'woo-smart-search' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="wss-tr-inStock"><?php esc_html_e( 'In stock', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-inStock" name="translations[inStock]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'inStock' ) ); ?>" placeholder="In stock" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-outOfStock"><?php esc_html_e( 'Out of stock', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-outOfStock" name="translations[outOfStock]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'outOfStock' ) ); ?>" placeholder="Out of stock" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-onBackorder"><?php esc_html_e( 'On backorder', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-onBackorder" name="translations[onBackorder]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'onBackorder' ) ); ?>" placeholder="On backorder" /></td>
		</tr>
	</table>

	<!-- Facets / Filters -->
	<h2><?php esc_html_e( 'Facets &amp; Filters', 'woo-smart-search' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="wss-tr-tags"><?php esc_html_e( 'Tags', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-tags" name="translations[tags]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'tags' ) ); ?>" placeholder="Tags" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-stock"><?php esc_html_e( 'Stock', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-stock" name="translations[stock]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'stock' ) ); ?>" placeholder="Stock" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-brand"><?php esc_html_e( 'Brand', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-brand" name="translations[brand]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'brand' ) ); ?>" placeholder="Brand" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-rating"><?php esc_html_e( 'Rating', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-rating" name="translations[rating]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'rating' ) ); ?>" placeholder="Rating" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-price"><?php esc_html_e( 'Price', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-price" name="translations[price]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'price' ) ); ?>" placeholder="Price" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-priceMin"><?php esc_html_e( 'Price min placeholder', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-priceMin" name="translations[priceMin]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'priceMin' ) ); ?>" placeholder="Min" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-priceMax"><?php esc_html_e( 'Price max placeholder', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-priceMax" name="translations[priceMax]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'priceMax' ) ); ?>" placeholder="Max" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-contentType"><?php esc_html_e( 'Content Type', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-contentType" name="translations[contentType]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'contentType' ) ); ?>" placeholder="Content Type" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-author"><?php esc_html_e( 'Author', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-author" name="translations[author]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'author' ) ); ?>" placeholder="Author" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-clearAll"><?php esc_html_e( 'Clear all filters', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-clearAll" name="translations[clearAll]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'clearAll' ) ); ?>" placeholder="Clear all" /></td>
		</tr>
	</table>

	<!-- Results Page -->
	<h2><?php esc_html_e( 'Results Page', 'woo-smart-search' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="wss-tr-resultsFor"><?php esc_html_e( 'Results for heading', 'woo-smart-search' ); ?></label></th>
			<td>
				<input type="text" id="wss-tr-resultsFor" name="translations[resultsFor]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'resultsFor' ) ); ?>" placeholder='Results for "%s"' />
				<p class="description"><?php esc_html_e( 'Use %s for the search query.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-xResults"><?php esc_html_e( 'Results count', 'woo-smart-search' ); ?></label></th>
			<td>
				<input type="text" id="wss-tr-xResults" name="translations[xResults]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'xResults' ) ); ?>" placeholder="<?php esc_attr_e( '%d results', 'woo-smart-search' ); ?>" />
				<p class="description"><?php esc_html_e( 'Use %d for the number. Example: %d resultados', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-xProducts"><?php esc_html_e( 'Products count', 'woo-smart-search' ); ?></label></th>
			<td>
				<input type="text" id="wss-tr-xProducts" name="translations[xProducts]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'xProducts' ) ); ?>" placeholder="<?php esc_attr_e( '%d products', 'woo-smart-search' ); ?>" />
				<p class="description"><?php esc_html_e( 'Use %d for the number. Example: %d productos', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-noResultsPage"><?php esc_html_e( 'No results message', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-noResultsPage" name="translations[noResultsPage]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'noResultsPage' ) ); ?>" placeholder="No results found matching your search." /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-errorLoading"><?php esc_html_e( 'Error loading', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-errorLoading" name="translations[errorLoading]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'errorLoading' ) ); ?>" placeholder="Error loading results. Please try again." /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-filters"><?php esc_html_e( 'Filters button', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-filters" name="translations[filters]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'filters' ) ); ?>" placeholder="Filters" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-sortRelevance"><?php esc_html_e( 'Sort: Relevance', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-sortRelevance" name="translations[sortRelevance]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'sortRelevance' ) ); ?>" placeholder="Relevance" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-sortPriceLow"><?php esc_html_e( 'Sort: Price low to high', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-sortPriceLow" name="translations[sortPriceLow]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'sortPriceLow' ) ); ?>" placeholder="Price: Low to High" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-sortPriceHigh"><?php esc_html_e( 'Sort: Price high to low', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-sortPriceHigh" name="translations[sortPriceHigh]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'sortPriceHigh' ) ); ?>" placeholder="Price: High to Low" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-sortNewest"><?php esc_html_e( 'Sort: Newest', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-sortNewest" name="translations[sortNewest]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'sortNewest' ) ); ?>" placeholder="Newest" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-sortPopular"><?php esc_html_e( 'Sort: Most popular', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-sortPopular" name="translations[sortPopular]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'sortPopular' ) ); ?>" placeholder="Most Popular" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-sortRating"><?php esc_html_e( 'Sort: Best rated', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-sortRating" name="translations[sortRating]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'sortRating' ) ); ?>" placeholder="Best Rated" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-sortNameAZ"><?php esc_html_e( 'Sort: Name A-Z', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-sortNameAZ" name="translations[sortNameAZ]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'sortNameAZ' ) ); ?>" placeholder="Name: A–Z" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-sortNameZA"><?php esc_html_e( 'Sort: Name Z-A', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-sortNameZA" name="translations[sortNameZA]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'sortNameZA' ) ); ?>" placeholder="Name: Z–A" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-addToCart"><?php esc_html_e( 'Add to Cart', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-addToCart" name="translations[addToCart]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'addToCart' ) ); ?>" placeholder="Add to Cart" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-freeShipping"><?php esc_html_e( 'Free shipping', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-freeShipping" name="translations[freeShipping]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'freeShipping' ) ); ?>" placeholder="Free shipping" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-sold"><?php esc_html_e( 'Sold', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-sold" name="translations[sold]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'sold' ) ); ?>" placeholder="sold" /></td>
		</tr>
	</table>

	<!-- Fullscreen Layout -->
	<h2><?php esc_html_e( 'Fullscreen Layout', 'woo-smart-search' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="wss-tr-searchOurStore"><?php esc_html_e( 'Search title', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-searchOurStore" name="translations[searchOurStore]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'searchOurStore' ) ); ?>" placeholder="Search our store" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-collections"><?php esc_html_e( 'Collections', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-collections" name="translations[collections]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'collections' ) ); ?>" placeholder="Collections" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-brands"><?php esc_html_e( 'Brands', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-brands" name="translations[brands]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'brands' ) ); ?>" placeholder="Brands" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-relatedBrands"><?php esc_html_e( 'Related Brands', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-relatedBrands" name="translations[relatedBrands]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'relatedBrands' ) ); ?>" placeholder="Related Brands" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="wss-tr-relatedCategories"><?php esc_html_e( 'Related Categories', 'woo-smart-search' ); ?></label></th>
			<td><input type="text" id="wss-tr-relatedCategories" name="translations[relatedCategories]" class="regular-text" value="<?php echo esc_attr( wss_tr( $t, 'relatedCategories' ) ); ?>" placeholder="Related Categories" /></td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary wss-save-settings">
			<?php esc_html_e( 'Save Translations', 'woo-smart-search' ); ?>
		</button>
		<span class="wss-status-message"></span>
	</p>
</form>
