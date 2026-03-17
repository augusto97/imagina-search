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
	'search'     => __( 'Search', 'woo-smart-search' ),
	'appearance' => __( 'Appearance', 'woo-smart-search' ),
	'logs'       => __( 'Logs', 'woo-smart-search' ),
);

$settings = get_option( 'wss_settings', array() );
?>
<div class="wrap wss-admin-wrap">
	<h1><?php esc_html_e( 'Woo Smart Search', 'woo-smart-search' ); ?></h1>

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
