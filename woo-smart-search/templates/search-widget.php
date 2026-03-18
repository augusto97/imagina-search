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
?>
<div class="wss-search-wrapper wss-layout-<?php echo esc_attr( $widget_layout ); ?>" role="search" aria-label="<?php esc_attr_e( 'Product search', 'woo-smart-search' ); ?>" style="width:<?php echo esc_attr( $width ); ?>">
	<div class="wss-search-input-container">
		<input
			type="search"
			class="wss-search-input"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
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
	</div>

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
		<?php else : ?>
		<!-- Standard / Compact layout -->
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

	<!-- Mobile Overlay Backdrop -->
	<div class="wss-mobile-backdrop" aria-hidden="true"></div>
</div>
