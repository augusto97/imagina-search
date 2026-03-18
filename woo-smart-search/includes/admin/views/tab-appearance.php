<?php
/**
 * Appearance tab template.
 *
 * @package WooSmartSearch
 * @var array $settings Plugin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form id="wss-appearance-form" class="wss-form">
	<input type="hidden" name="_wss_tab" value="appearance" />
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="wss-integration-mode"><?php esc_html_e( 'Integration Mode', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<select id="wss-integration-mode" name="integration_mode">
					<option value="replace" <?php selected( $settings['integration_mode'] ?? 'replace', 'replace' ); ?>><?php esc_html_e( 'Replace native search', 'woo-smart-search' ); ?></option>
					<option value="shortcode" <?php selected( $settings['integration_mode'] ?? '', 'shortcode' ); ?>><?php esc_html_e( 'Shortcode only', 'woo-smart-search' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-widget-layout"><?php esc_html_e( 'Widget Layout', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<select id="wss-widget-layout" name="widget_layout">
					<option value="standard" <?php selected( $settings['widget_layout'] ?? 'standard', 'standard' ); ?>><?php esc_html_e( 'Standard — Vertical list', 'woo-smart-search' ); ?></option>
					<option value="expanded" <?php selected( $settings['widget_layout'] ?? '', 'expanded' ); ?>><?php esc_html_e( 'Expanded — Two columns with suggestions & popular searches', 'woo-smart-search' ); ?></option>
					<option value="compact" <?php selected( $settings['widget_layout'] ?? '', 'compact' ); ?>><?php esc_html_e( 'Compact — Minimal list, no images', 'woo-smart-search' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Choose the dropdown autocomplete layout style.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-theme"><?php esc_html_e( 'Theme', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<select id="wss-theme" name="theme">
					<option value="light" <?php selected( $settings['theme'] ?? 'light', 'light' ); ?>><?php esc_html_e( 'Light', 'woo-smart-search' ); ?></option>
					<option value="dark" <?php selected( $settings['theme'] ?? '', 'dark' ); ?>><?php esc_html_e( 'Dark', 'woo-smart-search' ); ?></option>
					<option value="custom" <?php selected( $settings['theme'] ?? '', 'custom' ); ?>><?php esc_html_e( 'Custom', 'woo-smart-search' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-primary-color"><?php esc_html_e( 'Primary Color', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="color" id="wss-primary-color" name="primary_color" value="<?php echo esc_attr( $settings['primary_color'] ?? '#2271b1' ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-bg-color"><?php esc_html_e( 'Background Color', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="color" id="wss-bg-color" name="bg_color" value="<?php echo esc_attr( $settings['bg_color'] ?? '#ffffff' ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-text-color"><?php esc_html_e( 'Text Color', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="color" id="wss-text-color" name="text_color" value="<?php echo esc_attr( $settings['text_color'] ?? '#1d2327' ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-border-color"><?php esc_html_e( 'Border Color', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="color" id="wss-border-color" name="border_color" value="<?php echo esc_attr( $settings['border_color'] ?? '#c3c4c7' ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-font-size"><?php esc_html_e( 'Font Size (px)', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="number" id="wss-font-size" name="font_size" value="<?php echo esc_attr( $settings['font_size'] ?? '14' ); ?>" class="small-text" min="10" max="24" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-border-radius"><?php esc_html_e( 'Border Radius (px)', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="number" id="wss-border-radius" name="border_radius" value="<?php echo esc_attr( $settings['border_radius'] ?? '4' ); ?>" class="small-text" min="0" max="30" />
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Visible Elements', 'woo-smart-search' ); ?></th>
			<td>
				<?php
				$elements = array(
					'show_image'      => __( 'Product image', 'woo-smart-search' ),
					'show_price'      => __( 'Price', 'woo-smart-search' ),
					'show_category'   => __( 'Category', 'woo-smart-search' ),
					'show_sku'        => __( 'SKU', 'woo-smart-search' ),
					'show_stock'      => __( 'Stock status', 'woo-smart-search' ),
					'show_rating'     => __( 'Rating', 'woo-smart-search' ),
					'show_sale_badge' => __( 'Sale badge', 'woo-smart-search' ),
					'enable_analytics' => __( 'Enable search analytics', 'woo-smart-search' ),
				);
				foreach ( $elements as $key => $label ) :
					?>
					<?php
					// Default to 'yes' for most display options, 'no' for show_sku and show_rating.
					$default = in_array( $key, array( 'show_sku', 'show_rating' ), true ) ? 'no' : 'yes';
					?>
					<label style="display:block; margin-bottom:4px;">
						<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="yes" <?php checked( $settings[ $key ] ?? $default, 'yes' ); ?> />
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-placeholder"><?php esc_html_e( 'Placeholder Text', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="text" id="wss-placeholder" name="placeholder_text" value="<?php echo esc_attr( $settings['placeholder_text'] ?? '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Search products...', 'woo-smart-search' ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-custom-css"><?php esc_html_e( 'Custom CSS', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<textarea id="wss-custom-css" name="custom_css" rows="6" class="large-text code"><?php echo esc_textarea( $settings['custom_css'] ?? '' ); ?></textarea>
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

<div class="wss-preview-section">
	<h3><?php esc_html_e( 'Preview', 'woo-smart-search' ); ?></h3>
	<div id="wss-admin-preview" class="wss-admin-preview">
		<div class="wss-search-wrapper" style="max-width:500px;">
			<div class="wss-search-input-container">
				<input type="text" class="wss-search-input" placeholder="<?php echo esc_attr( $settings['placeholder_text'] ?? __( 'Search products...', 'woo-smart-search' ) ); ?>" readonly />
			</div>
		</div>
	</div>
</div>
