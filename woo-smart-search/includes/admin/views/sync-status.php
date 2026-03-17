<?php
/**
 * Indexing/Sync tab template.
 *
 * @package WooSmartSearch
 * @var array $settings Plugin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$product_count = wp_count_posts( 'product' );
$published     = isset( $product_count->publish ) ? (int) $product_count->publish : 0;
$last_sync     = wss_get_option( 'last_sync', 0 );

$categories = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	)
);

// Get available meta keys for custom fields.
global $wpdb;
$meta_keys = $wpdb->get_col(
	"SELECT DISTINCT meta_key FROM {$wpdb->postmeta} pm
	 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
	 WHERE p.post_type = 'product' AND pm.meta_key NOT LIKE '\_%'
	 ORDER BY meta_key LIMIT 100"
);
?>
<div class="wss-sync-section">
	<h2><?php esc_html_e( 'Synchronization', 'woo-smart-search' ); ?></h2>

	<div class="wss-stats-grid">
		<div class="wss-stat-card">
			<span class="wss-stat-label"><?php esc_html_e( 'WooCommerce Products', 'woo-smart-search' ); ?></span>
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
	<h2><?php esc_html_e( 'Indexing Settings', 'woo-smart-search' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="wss-batch-size"><?php esc_html_e( 'Batch Size', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="number" id="wss-batch-size" name="batch_size" value="<?php echo esc_attr( $settings['batch_size'] ?? 100 ); ?>" class="small-text" min="10" max="500" />
				<p class="description"><?php esc_html_e( 'Products per batch during full sync. Default: 100.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
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
				<label for="wss-exclude-cats"><?php esc_html_e( 'Exclude Categories', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<select id="wss-exclude-cats" name="exclude_categories[]" multiple class="wss-select-multi">
					<?php
					$excluded = $settings['exclude_categories'] ?? array();
					if ( ! is_wp_error( $categories ) ) :
						foreach ( $categories as $cat ) :
							?>
							<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php echo in_array( (int) $cat->term_id, $excluded, true ) ? 'selected' : ''; ?>>
								<?php echo esc_html( $cat->name ); ?>
							</option>
							<?php
						endforeach;
					endif;
					?>
				</select>
				<p class="description"><?php esc_html_e( 'Products in these categories will not be indexed.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-custom-fields"><?php esc_html_e( 'Custom Fields', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<select id="wss-custom-fields" name="custom_fields[]" multiple class="wss-select-multi">
					<?php
					$selected_fields = $settings['custom_fields'] ?? array();
					foreach ( $meta_keys as $key ) :
						?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php echo in_array( $key, $selected_fields, true ) ? 'selected' : ''; ?>>
							<?php echo esc_html( $key ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Custom meta fields / ACF fields to include in the index.', 'woo-smart-search' ); ?></p>
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
