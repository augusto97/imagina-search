<?php
/**
 * Log viewer tab template.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wss-logs-section">
	<div class="wss-logs-toolbar">
		<select id="wss-log-type-filter">
			<option value=""><?php esc_html_e( 'All types', 'woo-smart-search' ); ?></option>
			<option value="info"><?php esc_html_e( 'Info', 'woo-smart-search' ); ?></option>
			<option value="warning"><?php esc_html_e( 'Warning', 'woo-smart-search' ); ?></option>
			<option value="error"><?php esc_html_e( 'Error', 'woo-smart-search' ); ?></option>
		</select>

		<button type="button" id="wss-refresh-logs" class="button button-secondary">
			<?php esc_html_e( 'Refresh', 'woo-smart-search' ); ?>
		</button>
		<button type="button" id="wss-export-logs" class="button button-secondary">
			<?php esc_html_e( 'Export CSV', 'woo-smart-search' ); ?>
		</button>
		<button type="button" id="wss-clear-logs" class="button button-secondary">
			<?php esc_html_e( 'Clear Logs', 'woo-smart-search' ); ?>
		</button>
	</div>

	<table class="wp-list-table widefat fixed striped" id="wss-logs-table">
		<thead>
			<tr>
				<th class="wss-col-type"><?php esc_html_e( 'Type', 'woo-smart-search' ); ?></th>
				<th class="wss-col-message"><?php esc_html_e( 'Message', 'woo-smart-search' ); ?></th>
				<th class="wss-col-date"><?php esc_html_e( 'Date', 'woo-smart-search' ); ?></th>
			</tr>
		</thead>
		<tbody id="wss-logs-body">
			<tr>
				<td colspan="3"><?php esc_html_e( 'Loading...', 'woo-smart-search' ); ?></td>
			</tr>
		</tbody>
	</table>

	<div id="wss-logs-pagination" class="tablenav bottom">
		<div class="tablenav-pages"></div>
	</div>
</div>
