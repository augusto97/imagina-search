<?php
/**
 * Search widget template.
 *
 * This template can be overridden by copying it to your theme:
 * yourtheme/woo-smart-search/search-widget.php
 *
 * @package WooSmartSearch
 * @var string $placeholder Widget placeholder text.
 * @var string $width       Widget width.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$widget_layout = $settings['widget_layout'] ?? 'standard';
$i18n          = WSS_Frontend::get_frontend_i18n( $settings );
$show_icon     = true;
if ( isset( $atts['show_icon'] ) && ( false === $atts['show_icon'] || '0' === $atts['show_icon'] || 'false' === $atts['show_icon'] || 'no' === $atts['show_icon'] ) ) {
	$show_icon = false;
}
$icon_position = ! empty( $atts['icon_position'] ) ? $atts['icon_position'] : 'left';
$input_height  = ! empty( $atts['input_height'] ) ? (int) $atts['input_height'] : 0;
$border_radius = ! empty( $atts['border_radius'] ) ? (int) $atts['border_radius'] : -1;

$wrapper_classes = 'wss-search-wrapper wss-layout-' . esc_attr( $widget_layout );
if ( $show_icon && 'right' === $icon_position ) {
	$wrapper_classes .= ' wss-icon-right';
}
if ( ! $show_icon ) {
	$wrapper_classes .= ' wss-icon-hidden';
}

$inline_styles = 'width:' . esc_attr( $width );
if ( $input_height > 0 ) {
	$inline_styles .= ';--wss-input-height:' . $input_height . 'px';
}
if ( $border_radius >= 0 ) {
	$inline_styles .= ';--wss-border-radius:' . $border_radius . 'px';
}
?>
<div class="<?php echo esc_attr( $wrapper_classes ); ?>" role="search" aria-label="<?php esc_attr_e( 'Product search', 'woo-smart-search' ); ?>" style="<?php echo $inline_styles; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
	<div class="wss-search-input-container">
		<input
			type="search"
			class="wss-search-input"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			maxlength="100"
			autocomplete="off"
			aria-autocomplete="list"
			aria-controls="wss-results-list"
			aria-expanded="false"
			role="combobox"
		/>
		<span class="wss-search-icon">
			<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
		</span>
		<span class="wss-search-spinner" style="display:none">
			<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="32"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></circle></svg>
		</span>
		<button class="wss-search-clear" style="display:none" aria-label="<?php esc_attr_e( 'Clear search', 'woo-smart-search' ); ?>" type="button">&times;</button>
		<!-- Mobile: back arrow at the left of the bar closes the overlay.
		     Styled by the inline block below — ships with this HTML, so it
		     can never be out of sync with a cached stylesheet. -->
		<button class="wss-mobile-back-btn" type="button" aria-label="<?php esc_attr_e( 'Close', 'woo-smart-search' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
		</button>
	</div>

	<?php if ( empty( $GLOBALS['wss_mobile_overlay_css_done'] ) ) : ?>
		<?php $GLOBALS['wss_mobile_overlay_css_done'] = true; ?>
	<style>
	/* Mobile overlay — inline with the widget HTML so HTML/CSS are always in
	   sync regardless of stylesheet caching. Layout: ← [input] × */
	.wss-mobile-back-btn{display:none}
	@media (max-width:767px){
	.wss-search-wrapper.wss-mobile-open .wss-search-input-container{position:fixed!important;top:0!important;left:0!important;right:0!important;z-index:999999;display:flex!important;align-items:center!important;gap:6px;padding:8px 12px!important;background:#fff!important;border-bottom:1px solid #e5e7eb;box-shadow:0 2px 8px rgba(0,0,0,.08);width:auto!important}
	.wss-search-wrapper.wss-mobile-open .wss-mobile-back-btn{display:flex!important;align-items:center;justify-content:center;width:36px;height:36px;flex-shrink:0;background:none;border:none;cursor:pointer;padding:0;color:#374151;order:-1;border-radius:50%}
	.wss-search-wrapper.wss-mobile-open .wss-search-icon,
	.wss-search-wrapper.wss-mobile-open .wss-search-spinner{display:none!important}
	.wss-search-wrapper.wss-mobile-open .wss-search-input{flex:1 1 auto!important;width:auto!important;min-width:0!important;border:none!important;box-shadow:none!important;background:transparent!important;outline:none!important;padding:8px 4px!important;font-size:16px!important;height:auto!important}
	.wss-search-wrapper.wss-mobile-open .wss-search-clear{position:static!important;transform:none!important;flex-shrink:0}
	.wss-search-wrapper.wss-mobile-open .wss-results-dropdown{position:fixed!important;top:53px!important;left:0!important;right:0!important;bottom:0!important;max-height:none!important;border:none!important;border-radius:0!important;z-index:999998;overflow-y:auto!important;padding-top:0!important}
	}
	</style>
	<?php endif; ?>

	<?php if ( 'fullscreen' === $widget_layout ) : ?>
	<!-- Fullscreen overlay (Shopify-style) -->
	<div class="wss-fullscreen-overlay" aria-label="<?php esc_attr_e( 'Search', 'woo-smart-search' ); ?>">
		<div class="wss-fullscreen-header">
			<h3 class="wss-fullscreen-title"><?php echo esc_html( $i18n['searchOurStore'] ); ?></h3>
			<button class="wss-fullscreen-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'woo-smart-search' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
			</button>
		</div>
		<div class="wss-fullscreen-search">
			<span class="wss-search-icon">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			</span>
			<input type="search" class="wss-fullscreen-input" placeholder="<?php echo esc_attr( $placeholder ); ?>" maxlength="100" autocomplete="off" />
			<button class="wss-search-clear wss-fullscreen-clear" style="display:none" type="button" aria-label="<?php esc_attr_e( 'Clear', 'woo-smart-search' ); ?>">&times;</button>
		</div>
		<div class="wss-fullscreen-body">
			<div class="wss-fullscreen-columns">
				<div class="wss-fullscreen-col wss-fullscreen-products-col">
					<div class="wss-fullscreen-col-header">
						<h4><?php echo esc_html( $i18n['products'] ); ?></h4>
						<a href="#" class="wss-view-all wss-fullscreen-view-all"><?php echo esc_html( $i18n['viewAllResults'] ); ?> &nearr;</a>
					</div>
					<div class="wss-results-products"></div>
				</div>
				<div class="wss-fullscreen-col wss-fullscreen-categories-col">
					<h4><?php echo esc_html( $i18n['collections'] ); ?></h4>
					<ul class="wss-fullscreen-categories-list"></ul>
				</div>
				<div class="wss-fullscreen-col wss-fullscreen-brands-col">
					<h4><?php echo esc_html( $i18n['brands'] ); ?></h4>
					<ul class="wss-fullscreen-brands-list"></ul>
				</div>
			</div>
			<div class="wss-results-empty" role="status"></div>
			<div class="wss-results-error" role="alert"></div>
		</div>
	</div>
	<?php else : ?>

	<div class="wss-results-dropdown" role="listbox" id="wss-results-list" aria-label="<?php esc_attr_e( 'Search results', 'woo-smart-search' ); ?>">

		<?php if ( 'expanded' === $widget_layout ) : ?>
		<!-- Expanded layout: two-column -->
		<div class="wss-expanded-columns">
			<!-- Left sidebar: suggestions, popular, categories -->
			<div class="wss-expanded-sidebar">
				<div class="wss-popular-searches">
					<h5 class="wss-sidebar-heading"></h5>
					<ul class="wss-popular-list"></ul>
				</div>
				<div class="wss-sidebar-categories">
					<h5 class="wss-sidebar-heading"></h5>
					<ul class="wss-sidebar-categories-list"></ul>
				</div>
				<div class="wss-suggestions">
					<h5 class="wss-sidebar-heading"></h5>
					<ul class="wss-suggestions-list"></ul>
				</div>
			</div>
			<!-- Right: product results -->
			<div class="wss-expanded-main">
				<h4 class="wss-expanded-main-heading" style="display:none"></h4>
				<div class="wss-results-categories" role="group" aria-label="<?php esc_attr_e( 'Category suggestions', 'woo-smart-search' ); ?>"></div>
				<div class="wss-results-skeleton" aria-hidden="true">
					<div class="wss-skeleton-item"><div class="wss-skeleton-image"></div><div class="wss-skeleton-lines"><div class="wss-skeleton-line wss-skeleton-line--short"></div><div class="wss-skeleton-line wss-skeleton-line--long"></div><div class="wss-skeleton-line wss-skeleton-line--medium"></div></div></div>
					<div class="wss-skeleton-item"><div class="wss-skeleton-image"></div><div class="wss-skeleton-lines"><div class="wss-skeleton-line wss-skeleton-line--short"></div><div class="wss-skeleton-line wss-skeleton-line--long"></div><div class="wss-skeleton-line wss-skeleton-line--medium"></div></div></div>
					<div class="wss-skeleton-item"><div class="wss-skeleton-image"></div><div class="wss-skeleton-lines"><div class="wss-skeleton-line wss-skeleton-line--short"></div><div class="wss-skeleton-line wss-skeleton-line--long"></div><div class="wss-skeleton-line wss-skeleton-line--medium"></div></div></div>
				</div>
				<div class="wss-results-products"></div>
				<div class="wss-results-empty" role="status"></div>
				<div class="wss-results-error" role="alert"></div>
			</div>
		</div>

		<?php elseif ( 'falabella' === $widget_layout ) : ?>
		<!-- Falabella layout: three text columns -->
		<div class="wss-falabella-columns">
			<div class="wss-falabella-col wss-falabella-suggestions-col">
				<div class="wss-results-products"></div>
			</div>
			<div class="wss-falabella-col wss-falabella-brands-col">
				<h5 class="wss-column-heading"><?php echo esc_html( $i18n['relatedBrands'] ); ?></h5>
				<ul class="wss-falabella-brands-list"></ul>
			</div>
			<div class="wss-falabella-col wss-falabella-categories-col">
				<h5 class="wss-column-heading"><?php echo esc_html( $i18n['relatedCategories'] ); ?></h5>
				<ul class="wss-falabella-categories-list"></ul>
			</div>
		</div>
		<div class="wss-results-empty" role="status"></div>
		<div class="wss-results-error" role="alert"></div>

		<?php else : ?>
		<!-- Standard / Compact / Amazon layout -->
		<div class="wss-results-categories" role="group" aria-label="<?php esc_attr_e( 'Category suggestions', 'woo-smart-search' ); ?>"></div>
		<div class="wss-results-skeleton" aria-hidden="true">
			<div class="wss-skeleton-item"><div class="wss-skeleton-image"></div><div class="wss-skeleton-lines"><div class="wss-skeleton-line wss-skeleton-line--short"></div><div class="wss-skeleton-line wss-skeleton-line--long"></div><div class="wss-skeleton-line wss-skeleton-line--medium"></div></div></div>
			<div class="wss-skeleton-item"><div class="wss-skeleton-image"></div><div class="wss-skeleton-lines"><div class="wss-skeleton-line wss-skeleton-line--short"></div><div class="wss-skeleton-line wss-skeleton-line--long"></div><div class="wss-skeleton-line wss-skeleton-line--medium"></div></div></div>
			<div class="wss-skeleton-item"><div class="wss-skeleton-image"></div><div class="wss-skeleton-lines"><div class="wss-skeleton-line wss-skeleton-line--short"></div><div class="wss-skeleton-line wss-skeleton-line--long"></div><div class="wss-skeleton-line wss-skeleton-line--medium"></div></div></div>
		</div>
		<div class="wss-results-products"></div>
		<div class="wss-results-empty" role="status"></div>
		<div class="wss-results-error" role="alert"></div>
		<?php endif; ?>

		<!-- Footer -->
		<div class="wss-results-footer">
			<a href="#" class="wss-view-all"></a>
		</div>
	</div>

	<?php endif; ?>

	<!-- Mobile Overlay Backdrop -->
	<div class="wss-mobile-backdrop" aria-hidden="true"></div>
</div>
