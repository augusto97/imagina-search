<?php
/**
 * Content Sources tab template.
 *
 * Allows selecting what content to index: WooCommerce products,
 * WordPress posts/pages, or custom post types.
 *
 * @package WooSmartSearch
 * @var array $settings Plugin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$content_source  = $settings['content_source'] ?? 'auto';
$wc_active       = wss_is_woocommerce_active();
$wp_post_types   = $settings['wp_post_types'] ?? array( 'post' );
$wp_custom_fields = $settings['wp_custom_fields'] ?? array();

// Get all public post types.
$available_post_types = get_post_types( array( 'public' => true ), 'objects' );
// Exclude attachments and WC product (handled separately).
$excluded_types = array( 'attachment', 'product', 'product_variation' );

// Get available meta keys for the selected post types.
global $wpdb;
$meta_post_types = ! empty( $wp_post_types ) ? $wp_post_types : array( 'post' );
$placeholders    = implode( ',', array_fill( 0, count( $meta_post_types ), '%s' ) );
$meta_keys       = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT DISTINCT meta_key FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE p.post_type IN ({$placeholders}) AND pm.meta_key NOT LIKE '\_%'
		 ORDER BY meta_key LIMIT 100",
		...$meta_post_types
	)
);
?>
<form id="wss-content-sources-form" class="wss-form">
	<input type="hidden" name="_wss_tab" value="content_sources" />

	<h2><?php esc_html_e( 'Content Source', 'woo-smart-search' ); ?></h2>
	<p class="description" style="margin-bottom: 15px;">
		<?php esc_html_e( 'Choose what type of content to index and search. You can index WooCommerce products, WordPress posts/pages, or any custom post type.', 'woo-smart-search' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Mode', 'woo-smart-search' ); ?></th>
			<td>
				<fieldset>
					<label style="display:block; margin-bottom:8px;">
						<input type="radio" name="content_source" value="auto" <?php checked( $content_source, 'auto' ); ?> />
						<strong><?php esc_html_e( 'Auto-detect', 'woo-smart-search' ); ?></strong>
						<span class="description">
							— <?php esc_html_e( 'Uses WooCommerce if active, otherwise WordPress content.', 'woo-smart-search' ); ?>
							<?php if ( $wc_active ) : ?>
								<em style="color: #46b450;">(<?php esc_html_e( 'WooCommerce detected', 'woo-smart-search' ); ?>)</em>
							<?php else : ?>
								<em style="color: #ffb900;">(<?php esc_html_e( 'WooCommerce not detected', 'woo-smart-search' ); ?>)</em>
							<?php endif; ?>
						</span>
					</label>
					<label style="display:block; margin-bottom:8px;">
						<input type="radio" name="content_source" value="woocommerce" <?php checked( $content_source, 'woocommerce' ); ?> <?php disabled( ! $wc_active ); ?> />
						<strong><?php esc_html_e( 'WooCommerce Products', 'woo-smart-search' ); ?></strong>
						<span class="description">
							— <?php esc_html_e( 'Index products with prices, stock, SKU, attributes, and categories.', 'woo-smart-search' ); ?>
						</span>
						<?php if ( ! $wc_active ) : ?>
							<em style="color: #dc3232;">(<?php esc_html_e( 'Requires WooCommerce', 'woo-smart-search' ); ?>)</em>
						<?php endif; ?>
					</label>
					<label style="display:block; margin-bottom:8px;">
						<input type="radio" name="content_source" value="wordpress" <?php checked( $content_source, 'wordpress' ); ?> />
						<strong><?php esc_html_e( 'WordPress Content', 'woo-smart-search' ); ?></strong>
						<span class="description">
							— <?php esc_html_e( 'Index posts, pages, and/or custom post types.', 'woo-smart-search' ); ?>
						</span>
					</label>
					<label style="display:block; margin-bottom:8px;">
						<input type="radio" name="content_source" value="mixed" <?php checked( $content_source, 'mixed' ); ?> <?php disabled( ! $wc_active ); ?> />
						<strong><?php esc_html_e( 'Mixed — Products + Content', 'woo-smart-search' ); ?></strong>
						<span class="description">
							— <?php esc_html_e( 'Index WooCommerce products AND posts/pages in the same search, displayed in separate sections (like Searchanise).', 'woo-smart-search' ); ?>
						</span>
						<?php if ( ! $wc_active ) : ?>
							<em style="color: #dc3232;">(<?php esc_html_e( 'Requires WooCommerce', 'woo-smart-search' ); ?>)</em>
						<?php endif; ?>
					</label>
				</fieldset>
			</td>
		</tr>
	</table>

	<div id="wss-wp-content-options" style="<?php echo ( 'wordpress' === $content_source || 'mixed' === $content_source || ( 'auto' === $content_source && ! $wc_active ) ) ? '' : 'display:none;'; ?>">
		<h2><?php esc_html_e( 'WordPress Content Settings', 'woo-smart-search' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Post Types to Index', 'woo-smart-search' ); ?></th>
				<td>
					<?php foreach ( $available_post_types as $pt_name => $pt_obj ) :
						if ( in_array( $pt_name, $excluded_types, true ) ) {
							continue;
						}
						?>
						<label style="display:block; margin-bottom:4px;">
							<input type="checkbox" name="wp_post_types[]" value="<?php echo esc_attr( $pt_name ); ?>"
								<?php echo in_array( $pt_name, $wp_post_types, true ) ? 'checked' : ''; ?> />
							<?php echo esc_html( $pt_obj->labels->name ); ?>
							<span class="description">(<?php echo esc_html( $pt_name ); ?>)</span>
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Select which post types to include in the search index.', 'woo-smart-search' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wss-wp-custom-fields"><?php esc_html_e( 'Custom Fields', 'woo-smart-search' ); ?></label>
				</th>
				<td>
					<select id="wss-wp-custom-fields" name="wp_custom_fields[]" multiple class="wss-select-multi">
						<?php foreach ( $meta_keys as $key ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php echo in_array( $key, $wp_custom_fields, true ) ? 'selected' : ''; ?>>
								<?php echo esc_html( $key ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Custom meta fields / ACF fields to include in the index for WordPress content.', 'woo-smart-search' ); ?></p>
				</td>
			</tr>
		</table>
	</div>

	<p class="submit">
		<button type="submit" class="button button-primary wss-save-settings">
			<?php esc_html_e( 'Save Settings', 'woo-smart-search' ); ?>
		</button>
		<span class="wss-status-message"></span>
	</p>
</form>

<script type="text/javascript">
	jQuery( document ).ready( function( $ ) {
		$( 'input[name="content_source"]' ).on( 'change', function() {
			var val = $( this ).val();
			var wcActive = <?php echo $wc_active ? 'true' : 'false'; ?>;

			if ( val === 'wordpress' || val === 'mixed' || ( val === 'auto' && ! wcActive ) ) {
				$( '#wss-wp-content-options' ).slideDown( 200 );
			} else {
				$( '#wss-wp-content-options' ).slideUp( 200 );
			}
		} );
	} );
</script>
