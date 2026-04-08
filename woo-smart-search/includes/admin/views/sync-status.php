<?php
/**
 * Indexing/Sync tab template.
 *
 * Adapts to content source mode (WooCommerce products vs WordPress content).
 *
 * @package WooSmartSearch
 * @var array $settings Plugin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_ecommerce = wss_is_ecommerce_mode();
$is_mixed     = 'mixed' === wss_get_content_source();
$last_sync     = wss_get_option( 'last_sync', 0 );

// Determine content counts and labels.
if ( $is_mixed ) {
	$product_count = wp_count_posts( 'product' );
	$published     = isset( $product_count->publish ) ? (int) $product_count->publish : 0;

	$post_types = WSS_Post_Sync::get_configured_post_types();
	foreach ( $post_types as $pt ) {
		$counts = wp_count_posts( $pt );
		$published += isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	$type_labels   = array_map( function( $pt ) {
		$obj = get_post_type_object( $pt );
		return $obj ? $obj->labels->name : $pt;
	}, $post_types );
	$content_label = __( 'Products', 'woo-smart-search' ) . ' + ' . implode( ', ', $type_labels );
} elseif ( $is_ecommerce ) {
	$product_count = wp_count_posts( 'product' );
	$published     = isset( $product_count->publish ) ? (int) $product_count->publish : 0;
	$content_label = __( 'WooCommerce Products', 'woo-smart-search' );
} else {
	$post_types = WSS_Post_Sync::get_configured_post_types();
	$published  = 0;
	foreach ( $post_types as $pt ) {
		$counts = wp_count_posts( $pt );
		$published += isset( $counts->publish ) ? (int) $counts->publish : 0;
	}
	$type_labels   = array_map( function( $pt ) {
		$obj = get_post_type_object( $pt );
		return $obj ? $obj->labels->name : $pt;
	}, $post_types );
	$content_label = implode( ', ', $type_labels );
}

// Product categories (for ecommerce / mixed).
$product_categories = array();
if ( $is_ecommerce || $is_mixed ) {
	$product_categories = get_terms( array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	) );
	if ( is_wp_error( $product_categories ) ) {
		$product_categories = array();
	}
}

// Discover all public taxonomies for configured WP post types (for wordpress / mixed).
$wp_taxonomies = array();
if ( ! $is_ecommerce || $is_mixed ) {
	$configured_pts = class_exists( 'WSS_Post_Sync' ) ? WSS_Post_Sync::get_configured_post_types() : array( 'post' );
	// WooCommerce taxonomies to skip (handled separately above).
	$skip_taxonomies = array( 'product_cat', 'product_tag', 'product_type', 'product_visibility', 'product_shipping_class', 'post_format' );
	$seen = array();
	foreach ( $configured_pts as $pt ) {
		$taxonomies = get_object_taxonomies( $pt, 'objects' );
		foreach ( $taxonomies as $tax_slug => $tax_obj ) {
			if ( ! $tax_obj->public || in_array( $tax_slug, $skip_taxonomies, true ) || isset( $seen[ $tax_slug ] ) ) {
				continue;
			}
			$seen[ $tax_slug ] = true;
			$terms = get_terms( array(
				'taxonomy'   => $tax_slug,
				'hide_empty' => false,
			) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			$wp_taxonomies[ $tax_slug ] = array(
				'label' => $tax_obj->labels->name,
				'terms' => $terms,
			);
		}
	}
}

// Product meta keys (for ecommerce / mixed).
$product_meta_keys = array();
if ( $is_ecommerce || $is_mixed ) {
	global $wpdb;
	$product_meta_keys = $wpdb->get_col(
		"SELECT DISTINCT meta_key FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE p.post_type = 'product' AND pm.meta_key NOT LIKE '\_%'
		 ORDER BY meta_key LIMIT 100"
	);
}
?>
<div class="wss-sync-section">
	<h2><?php esc_html_e( 'Synchronization', 'woo-smart-search' ); ?></h2>

	<div class="wss-stats-grid">
		<div class="wss-stat-card">
			<span class="wss-stat-label"><?php echo esc_html( $content_label ); ?></span>
			<span class="wss-stat-value"><?php echo esc_html( number_format_i18n( $published ) ); ?></span>
		</div>
		<div class="wss-stat-card">
			<span class="wss-stat-label"><?php esc_html_e( 'Indexed Documents', 'woo-smart-search' ); ?></span>
			<span class="wss-stat-value" id="wss-indexed-count">—</span>
		</div>
		<div class="wss-stat-card">
			<span class="wss-stat-label"><?php esc_html_e( 'Last Sync', 'woo-smart-search' ); ?></span>
			<span class="wss-stat-value">
				<?php
				if ( $last_sync ) {
					echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_sync ) );
				} else {
					esc_html_e( 'Never', 'woo-smart-search' );
				}
				?>
			</span>
		</div>
	</div>

	<div class="wss-sync-actions">
		<button type="button" id="wss-full-sync" class="button button-primary">
			<?php esc_html_e( 'Full Sync', 'woo-smart-search' ); ?>
		</button>
		<button type="button" id="wss-clear-index" class="button button-secondary">
			<?php esc_html_e( 'Clear Index', 'woo-smart-search' ); ?>
		</button>
		<span class="wss-status-message" id="wss-sync-message"></span>
	</div>

	<div id="wss-sync-progress" class="wss-progress-bar" style="display:none;">
		<div class="wss-progress-bar-inner">
			<div class="wss-progress-bar-fill" style="width:0%"></div>
		</div>
		<span class="wss-progress-text">0%</span>
	</div>
</div>

<form id="wss-indexing-form" class="wss-form">
	<input type="hidden" name="_wss_tab" value="indexing" />
	<h2><?php esc_html_e( 'Indexing Settings', 'woo-smart-search' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="wss-batch-size"><?php esc_html_e( 'Batch Size', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="number" id="wss-batch-size" name="batch_size" value="<?php echo esc_attr( $settings['batch_size'] ?? 100 ); ?>" class="small-text" min="10" max="500" />
				<p class="description"><?php esc_html_e( 'Items per batch during full sync. Default: 100.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<?php if ( $is_ecommerce || $is_mixed ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Index Out of Stock', 'woo-smart-search' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="index_out_of_stock" value="yes" <?php checked( $settings['index_out_of_stock'] ?? 'yes', 'yes' ); ?> />
					<?php esc_html_e( 'Include out-of-stock products in the index', 'woo-smart-search' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Index Hidden Products', 'woo-smart-search' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="index_hidden" value="yes" <?php checked( $settings['index_hidden'] ?? 'no', 'yes' ); ?> />
					<?php esc_html_e( 'Include products hidden from catalog', 'woo-smart-search' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-exclude-cats"><?php esc_html_e( 'Exclude Product Categories', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<select id="wss-exclude-cats" name="exclude_categories[]" multiple class="wss-select-multi">
					<?php
					$excluded = $settings['exclude_categories'] ?? array();
					foreach ( $product_categories as $cat ) :
						?>
						<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php echo in_array( (int) $cat->term_id, $excluded, true ) ? 'selected' : ''; ?>>
							<?php echo esc_html( $cat->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Products in these categories will not be indexed.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-custom-fields"><?php esc_html_e( 'Product Custom Fields', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<select id="wss-custom-fields" name="custom_fields[]" multiple class="wss-select-multi">
					<?php
					$selected_fields = $settings['custom_fields'] ?? array();
					foreach ( $product_meta_keys as $key ) :
						?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php echo in_array( $key, $selected_fields, true ) ? 'selected' : ''; ?>>
							<?php echo esc_html( $key ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Product meta fields / ACF fields to include in the index.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<?php endif; ?>

		<?php
		if ( ! empty( $wp_taxonomies ) ) :
			$exclude_tax_settings = $settings['exclude_taxonomies'] ?? array();
			foreach ( $wp_taxonomies as $tax_slug => $tax_data ) :
				$saved_ids = isset( $exclude_tax_settings[ $tax_slug ] ) ? array_map( 'intval', $exclude_tax_settings[ $tax_slug ] ) : array();
		?>
		<tr>
			<th scope="row">
				<label for="wss-exclude-tax-<?php echo esc_attr( $tax_slug ); ?>">
					<?php
					printf(
						/* translators: %s: taxonomy name */
						esc_html__( 'Exclude %s', 'woo-smart-search' ),
						esc_html( $tax_data['label'] )
					);
					?>
				</label>
			</th>
			<td>
				<select id="wss-exclude-tax-<?php echo esc_attr( $tax_slug ); ?>" name="exclude_taxonomies[<?php echo esc_attr( $tax_slug ); ?>][]" multiple class="wss-select-multi">
					<?php foreach ( $tax_data['terms'] as $term ) : ?>
						<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php echo in_array( (int) $term->term_id, $saved_ids, true ) ? 'selected' : ''; ?>>
							<?php echo esc_html( $term->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					<?php
					printf(
						/* translators: %s: taxonomy name */
						esc_html__( 'Content with these %s will not be indexed.', 'woo-smart-search' ),
						esc_html( strtolower( $tax_data['label'] ) )
					);
					?>
				</p>
			</td>
		</tr>
		<?php
			endforeach;
		endif;
		?>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary wss-save-settings">
			<?php esc_html_e( 'Save Settings', 'woo-smart-search' ); ?>
		</button>
		<span class="wss-status-message"></span>
	</p>
</form>
