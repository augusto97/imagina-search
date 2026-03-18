<?php
/**
 * Main settings page template.
 *
 * @package WooSmartSearch
 * @var string $active_tab The active tab slug.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tabs = array(
	'connection' => __( 'Connection', 'woo-smart-search' ),
	'indexing'   => __( 'Indexing', 'woo-smart-search' ),
	'appearance' => __( 'Widget', 'woo-smart-search' ),
	'search'     => __( 'Results Page', 'woo-smart-search' ),
	'analytics'  => __( 'Analytics', 'woo-smart-search' ),
	'logs'       => __( 'Logs', 'woo-smart-search' ),
);

$settings = get_option( 'wss_settings', array() );

// Determine connection status for the status bar.
$connection_error = get_transient( 'wss_connection_error' );
$sync_progress    = get_transient( 'wss_sync_progress' );
$is_syncing       = ( $sync_progress && isset( $sync_progress['status'] ) && 'running' === $sync_progress['status'] );

if ( $connection_error ) {
	$status_class = 'wss-status-error';
	$status_color = '#dc3232';
	$status_dot   = '#dc3232';
	$status_text  = sprintf(
		/* translators: %s: error message */
		__( 'Error: %s - fallback active', 'woo-smart-search' ),
		$connection_error
	);
} elseif ( $is_syncing ) {
	$status_class = 'wss-status-syncing';
	$status_color = '#ffb900';
	$status_dot   = '#ffb900';
	$status_text  = __( 'Syncing...', 'woo-smart-search' );
} else {
	$status_class = 'wss-status-connected';
	$status_color = '#46b450';
	$status_dot   = '#46b450';
	$status_text  = __( 'Connected', 'woo-smart-search' );
}
?>
<div class="wrap wss-admin-wrap">
	<h1><?php esc_html_e( 'Woo Smart Search', 'woo-smart-search' ); ?></h1>

	<!-- Connection Status Bar -->
	<div id="wss-global-status-bar" class="<?php echo esc_attr( $status_class ); ?>" style="margin: 10px 0 20px; padding: 8px 15px; background: #fff; border-left: 4px solid <?php echo esc_attr( $status_color ); ?>; display: flex; align-items: center; gap: 8px;">
		<span class="wss-status-dot" style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?php echo esc_attr( $status_dot ); ?>;"></span>
		<span class="wss-status-label" style="font-weight: 600;"><?php echo esc_html( $status_text ); ?></span>
		<span class="wss-status-details" style="color: #666; font-style: italic;"></span>
	</div>

	<nav class="nav-tab-wrapper wss-tabs">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-smart-search&tab=' . $tab_key ) ); ?>"
			   class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>"
			   data-tab="<?php echo esc_attr( $tab_key ); ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="wss-tab-content">
		<?php
		switch ( $active_tab ) {
			case 'indexing':
				include WSS_PLUGIN_DIR . 'includes/admin/views/sync-status.php';
				break;
			case 'search':
				include __DIR__ . '/tab-search.php';
				break;
			case 'appearance':
				include __DIR__ . '/tab-appearance.php';
				break;
			case 'analytics':
				include __DIR__ . '/tab-analytics.php';
				break;
			case 'logs':
				include WSS_PLUGIN_DIR . 'includes/admin/views/log-viewer.php';
				break;
			default:
				include __DIR__ . '/tab-connection.php';
				break;
		}
		?>
	</div>
</div>

<script type="text/javascript">
	jQuery( document ).ready( function( $ ) {
		// Update the global status bar with live data.
		$.post( wssAdmin.ajaxUrl, {
			action: 'wss_get_connection_status',
			nonce: wssAdmin.nonce
		}, function( response ) {
			if ( ! response.success || ! response.data ) {
				return;
			}

			var $bar     = $( '#wss-global-status-bar' );
			var $dot     = $bar.find( '.wss-status-dot' );
			var $label   = $bar.find( '.wss-status-label' );
			var $details = $bar.find( '.wss-status-details' );
			var data     = response.data;

			if ( data.status === 'connected' ) {
				$dot.css( 'background', '#46b450' );
				$bar.css( 'border-left-color', '#46b450' );
				var info = '';
				if ( data.version ) {
					info = 'v' + data.version;
				}
				if ( typeof data.documents !== 'undefined' ) {
					info += ( info ? ', ' : '' ) + data.documents + ' docs';
				}
				$label.text( '<?php echo esc_js( __( 'Connected', 'woo-smart-search' ) ); ?>' );
				$details.text( info ? '(' + info + ')' : '' );
			} else if ( data.status === 'not_configured' ) {
				$dot.css( 'background', '#ffb900' );
				$bar.css( 'border-left-color', '#ffb900' );
				$label.text( '<?php echo esc_js( __( 'Not Configured', 'woo-smart-search' ) ); ?>' );
				$details.text( '' );
			} else {
				$dot.css( 'background', '#dc3232' );
				$bar.css( 'border-left-color', '#dc3232' );
				$label.text( '<?php echo esc_js( __( 'Error - fallback active', 'woo-smart-search' ) ); ?>' );
				$details.text( data.message || '' );
			}
		} );
	} );
</script>
