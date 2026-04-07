<?php
/**
 * Search results page template.
 *
 * Outputs the faceted search results layout.
 * Can be overridden by copying to yourtheme/woo-smart-search/results-page.php.
 *
 * @package WooSmartSearch
 * @var string $query The search query.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<style>
.wss-results-page{display:flex;gap:30px;max-width:1200px;width:100%;margin:0 auto;padding:20px 0;font-size:14px;box-sizing:border-box;opacity:0;transition:opacity .15s ease-out}
.wss-results-page.wss-ready{opacity:1}
.wss-results-page *,.wss-results-page *::before,.wss-results-page *::after{box-sizing:border-box}
.wss-results-main{flex:1;min-width:0;width:0}
.wss-filters-sidebar{width:260px;flex-shrink:0}
.wss-results-toolbar{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;margin-bottom:20px;border:1px solid #e5e7eb;border-radius:8px;gap:12px;flex-wrap:wrap}
.wss-sort-select{padding:6px 28px 6px 10px;border:1px solid #e5e7eb;border-radius:4px;font-size:.9em;appearance:none}
.wss-view-toggle{display:flex;gap:4px}
.wss-view-toggle button{padding:6px 10px;border:1px solid #e5e7eb;background:#fff;font-size:1em;line-height:1;border-radius:4px;cursor:pointer}
.wss-products-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.wss-mobile-filter-toggle{display:none}
.wss-results-loading{display:none}
@media(max-width:768px){.wss-results-page{flex-direction:column}.wss-results-main{width:100%}.wss-filters-sidebar{width:100%;position:fixed;top:0;left:-100%;bottom:0;z-index:999999}.wss-mobile-filter-toggle{display:block;width:100%;padding:10px 16px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;font-weight:600;cursor:pointer;margin-bottom:16px;text-align:center}.wss-products-grid{grid-template-columns:repeat(2,1fr);gap:12px}}
</style>
<div class="wss-results-page">

	<!-- Mobile filter toggle -->
	<button class="wss-mobile-filter-toggle" type="button">
		<?php esc_html_e( 'Filters', 'woo-smart-search' ); ?>
	</button>

	<!-- Mobile overlay -->
	<div class="wss-mobile-filter-overlay"></div>

	<!-- Sidebar filters -->
	<aside class="wss-filters-sidebar">
		<div class="wss-filter-panel-close">
			<button type="button" aria-label="<?php esc_attr_e( 'Close', 'woo-smart-search' ); ?>">&times;</button>
		</div>
		<!-- Facets rendered by JS -->
	</aside>

	<!-- Main content -->
	<div class="wss-results-main">

		<div class="wss-results-header">
			<h1>
				<?php
				printf(
					/* translators: %s: search query */
					esc_html__( 'Results for "%s"', 'woo-smart-search' ),
					esc_html( $query )
				);
				?>
			</h1>
			<span class="wss-results-count"></span>
		</div>

		<!-- Active filters -->
		<div class="wss-active-filters"></div>

		<!-- Toolbar -->
		<div class="wss-results-toolbar">
			<div>
				<label for="wss-sort" class="screen-reader-text"><?php esc_html_e( 'Sort by', 'woo-smart-search' ); ?></label>
				<?php
				$content_source  = wss_get_content_source();
				$is_ecommerce    = wss_is_ecommerce_mode();
				$show_wc_sorts   = $is_ecommerce || 'mixed' === $content_source;
			?>
				<select id="wss-sort" class="wss-sort-select">
					<option value=""><?php esc_html_e( 'Relevance', 'woo-smart-search' ); ?></option>
					<?php if ( $show_wc_sorts ) : ?>
					<option value="price:asc"><?php esc_html_e( 'Price: Low to High', 'woo-smart-search' ); ?></option>
					<option value="price:desc"><?php esc_html_e( 'Price: High to Low', 'woo-smart-search' ); ?></option>
					<?php endif; ?>
					<option value="date_created:desc"><?php esc_html_e( 'Newest', 'woo-smart-search' ); ?></option>
					<?php if ( $show_wc_sorts ) : ?>
					<option value="total_sales:desc"><?php esc_html_e( 'Most Popular', 'woo-smart-search' ); ?></option>
					<option value="rating:desc"><?php esc_html_e( 'Best Rated', 'woo-smart-search' ); ?></option>
					<?php endif; ?>
					<option value="name:asc"><?php esc_html_e( 'Name: A–Z', 'woo-smart-search' ); ?></option>
					<option value="name:desc"><?php esc_html_e( 'Name: Z–A', 'woo-smart-search' ); ?></option>
				</select>
			</div>
			<div class="wss-view-toggle">
				<button type="button" data-view="grid" class="wss-active" aria-label="<?php esc_attr_e( 'Grid view', 'woo-smart-search' ); ?>">&#9638;</button>
				<button type="button" data-view="list" aria-label="<?php esc_attr_e( 'List view', 'woo-smart-search' ); ?>">&#9776;</button>
			</div>
		</div>

		<!-- Loading -->
		<div class="wss-results-loading">
			<div class="wss-spinner"></div>
		</div>

		<!-- Product grid -->
		<div class="wss-products-grid"></div>

		<!-- No results -->
		<div class="wss-no-results" style="display:none;">
			<p><?php esc_html_e( 'No results found matching your search.', 'woo-smart-search' ); ?></p>
		</div>

		<!-- Pagination -->
		<div class="wss-pagination"></div>

	</div>

</div>
<script>setTimeout(function(){var p=document.querySelector('.wss-results-page');if(p)p.classList.add('wss-ready')},2000)</script>
