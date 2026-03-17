<?php
/**
 * Admin panel.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Admin
 */
class WSS_Admin {

	/**
	 * Initialize admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'display_notices' ) );
		add_action( 'admin_init', array( $this, 'handle_redirect' ) );
	}

	/**
	 * Add menu page under WooCommerce.
	 */
	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Smart Search', 'woo-smart-search' ),
			__( 'Smart Search', 'woo-smart-search' ),
			'manage_woocommerce',
			'woo-smart-search',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_woo-smart-search' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wss-admin',
			WSS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WSS_VERSION
		);

		wp_enqueue_script(
			'wss-admin',
			WSS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WSS_VERSION,
			true
		);

		wp_localize_script(
			'wss-admin',
			'wssAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wss_admin_nonce' ),
				'i18n'    => array(
					'testingConnection'  => __( 'Testing connection...', 'woo-smart-search' ),
					'connectionSuccess'  => __( 'Connection successful!', 'woo-smart-search' ),
					'connectionFailed'   => __( 'Connection failed:', 'woo-smart-search' ),
					'syncStarted'        => __( 'Synchronization started.', 'woo-smart-search' ),
					'syncCompleted'      => __( 'Synchronization completed!', 'woo-smart-search' ),
					'syncFailed'         => __( 'Synchronization failed:', 'woo-smart-search' ),
					'confirmFullSync'    => __( 'This will re-index all products. Continue?', 'woo-smart-search' ),
					'confirmClearIndex'  => __( 'This will delete ALL indexed data. Are you sure?', 'woo-smart-search' ),
					'confirmClearLogs'   => __( 'Delete all logs?', 'woo-smart-search' ),
					'saving'             => __( 'Saving...', 'woo-smart-search' ),
					'saved'              => __( 'Settings saved.', 'woo-smart-search' ),
					'error'              => __( 'An error occurred.', 'woo-smart-search' ),
				),
			)
		);
	}

	/**
	 * Handle activation redirect.
	 */
	public function handle_redirect() {
		if ( get_transient( 'wss_activation_redirect' ) ) {
			delete_transient( 'wss_activation_redirect' );
			if ( ! isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_safe_redirect( admin_url( 'admin.php?page=woo-smart-search' ) );
				exit;
			}
		}
	}

	/**
	 * Display admin notices.
	 */
	public function display_notices() {
		$connection_error = get_transient( 'wss_connection_error' );
		if ( $connection_error ) {
			printf(
				'<div class="notice notice-error"><p>%s %s</p></div>',
				'<strong>' . esc_html__( 'Woo Smart Search:', 'woo-smart-search' ) . '</strong>',
				esc_html( $connection_error )
			);
		}
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'connection'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		include WSS_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
	}
}
