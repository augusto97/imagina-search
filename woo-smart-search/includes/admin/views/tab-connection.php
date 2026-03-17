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
<div id="wss-connection-status-indicator" class="wss-connection-indicator" style="margin-bottom: 15px; padding: 10px 15px; background: #fff; border-left: 4px solid #ccc; display: flex; align-items: center; gap: 10px;">
	<span class="wss-status-dot" style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: #ccc;"></span>
	<span class="wss-status-text"><?php esc_html_e( 'Checking connection...', 'woo-smart-search' ); ?></span>
	<span class="wss-status-version" style="color: #666; font-style: italic;"></span>
</div>

<form id="wss-connection-form" class="wss-form">
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="wss-host"><?php esc_html_e( 'Host', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="text" id="wss-host" name="host" value="<?php echo esc_attr( $settings['host'] ?? 'localhost' ); ?>" class="regular-text" placeholder="localhost" />
				<p class="description"><?php esc_html_e( 'Meilisearch server hostname or IP address (without protocol).', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-port"><?php esc_html_e( 'Port', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="text" id="wss-port" name="port" value="<?php echo esc_attr( $settings['port'] ?? '7700' ); ?>" class="small-text" />
				<p class="description"><?php esc_html_e( 'Default Meilisearch port is 7700.', 'woo-smart-search' ); ?></p>
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
				<p class="description"><?php esc_html_e( 'Use HTTPS if your Meilisearch server has SSL enabled.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-api-key"><?php esc_html_e( 'API Key (Master)', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="password" id="wss-api-key" name="api_key" value="" class="regular-text" placeholder="<?php echo ! empty( $settings['api_key'] ) ? '********' : ''; ?>" />
				<p class="description"><?php esc_html_e( 'Master API key for admin operations (indexing, settings). Leave empty to keep current.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-search-api-key"><?php esc_html_e( 'Search API Key', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="text" id="wss-search-api-key" name="search_api_key" value="<?php echo esc_attr( $settings['search_api_key'] ?? '' ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'Read-only search key (optional). Not exposed to the frontend.', 'woo-smart-search' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wss-index-name"><?php esc_html_e( 'Index Name', 'woo-smart-search' ); ?></label>
			</th>
			<td>
				<input type="text" id="wss-index-name" name="index_name" value="<?php echo esc_attr( $settings['index_name'] ?? 'woo_products' ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'The Meilisearch index to store product data in.', 'woo-smart-search' ); ?></p>
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

<script type="text/javascript">
	jQuery( document ).ready( function( $ ) {
		function wssCheckConnectionStatus() {
			$.post( wssAdmin.ajaxUrl, {
				action: 'wss_get_connection_status',
				nonce: wssAdmin.nonce
			}, function( response ) {
				var $indicator = $( '#wss-connection-status-indicator' );
				var $dot       = $indicator.find( '.wss-status-dot' );
				var $text      = $indicator.find( '.wss-status-text' );
				var $version   = $indicator.find( '.wss-status-version' );

				if ( response.success && response.data ) {
					var data = response.data;

					if ( data.status === 'connected' ) {
						$dot.css( 'background', '#46b450' );
						$indicator.css( 'border-left-color', '#46b450' );
						$text.text( '<?php echo esc_js( __( 'Connected', 'woo-smart-search' ) ); ?>' );
						var info = '';
						if ( data.version ) {
							info += 'v' + data.version;
						}
						if ( typeof data.documents !== 'undefined' ) {
							info += ( info ? ', ' : '' ) + data.documents + ' <?php echo esc_js( __( 'documents', 'woo-smart-search' ) ); ?>';
						}
						$version.text( info );
					} else if ( data.status === 'not_configured' ) {
						$dot.css( 'background', '#ffb900' );
						$indicator.css( 'border-left-color', '#ffb900' );
						$text.text( '<?php echo esc_js( __( 'Not Configured', 'woo-smart-search' ) ); ?>' );
						$version.text( '' );
					} else {
						$dot.css( 'background', '#dc3232' );
						$indicator.css( 'border-left-color', '#dc3232' );
						$text.text( '<?php echo esc_js( __( 'Error', 'woo-smart-search' ) ); ?>' );
						$version.text( data.message || '' );
					}
				}
			} );
		}

		wssCheckConnectionStatus();
	} );
</script>
