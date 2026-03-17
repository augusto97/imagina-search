<?php
/**
 * Connection tab template.
 *
 * @package WooSmartSearch
 * @var array $settings Plugin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form id="wss-connection-form" class="wss-form">
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="wss-engine"><?php esc_html_e( 'Search Engine', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<select id="wss-engine" name="engine">
					<option value="meilisearch" <?php selected( $settings['engine'] ?? 'meilisearch', 'meilisearch' ); ?>>Meilisearch</option>
					<option value="typesense" <?php selected( $settings['engine'] ?? '', 'typesense' ); ?>>Typesense</option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-host"><?php esc_html_e( 'Host', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="text" id="wss-host" name="host" value="<?php echo esc_attr( $settings['host'] ?? 'localhost' ); ?>" class="regular-text" placeholder="localhost" />
				<p class="description"><?php esc_html_e( 'Server hostname or IP address (without protocol).', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-port"><?php esc_html_e( 'Port', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="text" id="wss-port" name="port" value="<?php echo esc_attr( $settings['port'] ?? '7700' ); ?>" class="small-text" />
				<p class="description"><?php esc_html_e( 'Meilisearch default: 7700, Typesense default: 8108', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-protocol"><?php esc_html_e( 'Protocol', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<select id="wss-protocol" name="protocol">
					<option value="http" <?php selected( $settings['protocol'] ?? 'http', 'http' ); ?>>HTTP</option>
					<option value="https" <?php selected( $settings['protocol'] ?? '', 'https' ); ?>>HTTPS</option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-api-key"><?php esc_html_e( 'API Key (Master)', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="password" id="wss-api-key" name="api_key" value="" class="regular-text" placeholder="<?php echo ! empty( $settings['api_key'] ) ? '********' : ''; ?>" />
				<p class="description"><?php esc_html_e( 'Master API key for admin operations. Leave empty to keep current.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-search-api-key"><?php esc_html_e( 'Search API Key', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="text" id="wss-search-api-key" name="search_api_key" value="<?php echo esc_attr( $settings['search_api_key'] ?? '' ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'Read-only key (optional, not exposed to frontend).', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-index-name"><?php esc_html_e( 'Index Name', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="text" id="wss-index-name" name="index_name" value="<?php echo esc_attr( $settings['index_name'] ?? 'woo_products' ); ?>" class="regular-text" />
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="button" id="wss-test-connection" class="button button-secondary">
			<?php esc_html_e( 'Test Connection', 'woo-smart-search' ); ?>
		</button>
		<button type="submit" class="button button-primary wss-save-settings">
			<?php esc_html_e( 'Save Settings', 'woo-smart-search' ); ?>
		</button>
		<span class="wss-status-message"></span>
	</p>

	<div id="wss-connection-result" class="wss-notice" style="display:none;"></div>
</form>
