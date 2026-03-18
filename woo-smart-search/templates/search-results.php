<?php
/**
 * Search results page wrapper template.
 *
 * Replaces the WooCommerce search results page with the
 * faceted search interface powered by Meilisearch.
 *
 * Can be overridden by copying to yourtheme/woo-smart-search/search-results.php
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header( 'shop' );

$query = get_search_query();
?>

<div class="wss-results-page-wrapper">
	<?php
	$template = locate_template( 'woo-smart-search/results-page.php' );
	if ( ! $template ) {
		$template = WSS_PLUGIN_DIR . 'templates/results-page.php';
	}
	include $template;
	?>
</div>

<?php
get_footer( 'shop' );
