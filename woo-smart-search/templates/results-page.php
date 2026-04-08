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

$wss_settings    = get_option( 'wss_settings', array() );
$i18n            = WSS_Frontend::get_frontend_i18n( $wss_settings );
$results_layout  = $wss_settings['results_layout'] ?? 'default';
$layout_class    = 'default' !== $results_layout ? ' wss-layout-' . esc_attr( $results_layout ) : '';
?>

<style>
.wss-results-page{display:flex;gap:30px;max-width:1200px;width:100%;margin:0 auto;padding:20px 0;font-size:14px;box-sizing:border-box;opacity:0;transition:opacity .15s ease-out}
.wss-results-page.wss-ready{opacity:1}
.wss-results-page *,.wss-results-page *::before,.wss-results-page *::after{box-sizing:border-box}
.wss-results-main{flex:1;min-width:0;width:0;position:relative}
.wss-filters-sidebar{width:260px;flex-shrink:0}
.wss-results-toolbar{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;margin-bottom:20px;border:1px solid #e5e7eb;border-radius:8px;gap:12px;flex-wrap:wrap}
.wss-sort-select{padding:6px 28px 6px 10px;border:1px solid #e5e7eb;border-radius:4px;font-size:.9em;appearance:none}
.wss-view-toggle{display:flex;gap:4px}
.wss-view-toggle button{padding:6px 10px;border:1px solid #e5e7eb;background:#fff;font-size:1em;line-height:1;border-radius:4px;cursor:pointer}
.wss-products-grid{display:grid;grid-template-columns:repeat(var(--wss-rp-columns,3),1fr);gap:var(--wss-rp-card-gap,20px)}
.wss-mobile-filter-toggle{display:none}
.wss-results-loading{display:none;position:absolute;top:0;left:0;right:0;bottom:0;z-index:10;background:rgba(255,255,255,.7);justify-content:center;align-items:center}
@media(max-width:768px){.wss-results-page{flex-direction:column}.wss-results-main{width:100%}.wss-filters-sidebar{width:100%;position:fixed;top:0;left:-100%;bottom:0;z-index:999999}.wss-mobile-filter-toggle{display:block;width:100%;padding:10px 16px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;font-weight:600;cursor:pointer;margin-bottom:16px;text-align:center}.wss-products-grid{grid-template-columns:repeat(2,1fr);gap:12px}}
</style>
<div class="wss-results-page<?php echo esc_attr( $layout_class ); ?>" style="background:var(--wss-rp-page-bg,#f9fafb)">

	<!-- Mobile filter toggle -->
	<button class="wss-mobile-filter-toggle" type="button">
		<?php echo esc_html( $i18n['filters'] ); ?>
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
				<?php echo esc_html( sprintf( $i18n['resultsFor'], $query ) ); ?>
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
					<option value=""><?php echo esc_html( $i18n['sortRelevance'] ); ?></option>
					<?php if ( $show_wc_sorts ) : ?>
					<option value="price:asc"><?php echo esc_html( $i18n['sortPriceLow'] ); ?></option>
					<option value="price:desc"><?php echo esc_html( $i18n['sortPriceHigh'] ); ?></option>
					<?php endif; ?>
					<option value="date_created:desc"><?php echo esc_html( $i18n['sortNewest'] ); ?></option>
					<?php if ( $show_wc_sorts ) : ?>
					<option value="total_sales:desc"><?php echo esc_html( $i18n['sortPopular'] ); ?></option>
					<option value="rating:desc"><?php echo esc_html( $i18n['sortRating'] ); ?></option>
					<?php endif; ?>
					<option value="name:asc"><?php echo esc_html( $i18n['sortNameAZ'] ); ?></option>
					<option value="name:desc"><?php echo esc_html( $i18n['sortNameZA'] ); ?></option>
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
			<p><?php echo esc_html( $i18n['noResultsPage'] ); ?></p>
		</div>

		<!-- Pagination -->
		<div class="wss-pagination"></div>

	</div>

</div>
<script>setTimeout(function(){var p=document.querySelector('.wss-results-page');if(p)p.classList.add('wss-ready')},2000)</script>
