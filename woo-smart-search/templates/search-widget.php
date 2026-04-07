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
			<input type="search" class="wss-fullscreen-input" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off" />
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

	<!-- Mobile Close Button (improved for touch) -->
	<button class="wss-mobile-close-btn" type="button" aria-label="<?php esc_attr_e( 'Close', 'woo-smart-search' ); ?>">
		<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
		<?php echo esc_html( $i18n['close'] ?? __( 'Close', 'woo-smart-search' ) ); ?>
	</button>

	<!-- Mobile Overlay Backdrop -->
	<div class="wss-mobile-backdrop" aria-hidden="true"></div>
</div>
