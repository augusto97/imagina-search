<?php
/**
 * The loader class registers all hooks with WordPress.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Loader
 */
class WSS_Loader {

	/**
	 * Run the loader to initialize all plugin components.
	 */
	public function run() {
		// Initialize admin.
		if ( is_admin() ) {
			$admin = new WSS_Admin();
			$admin->init();

			$admin_ajax = new WSS_Admin_Ajax();
			$admin_ajax->init();
		}

		// Initialize frontend.
		$frontend = new WSS_Frontend();
		$frontend->init();

		// Initialize shortcode.
		$shortcode = new WSS_Shortcode();
		$shortcode->init();

		// Initialize REST API.
		$rest_api = new WSS_Rest_Api();
		$rest_api->init();

		// Initialize product sync.
		$sync = new WSS_Product_Sync();
		$sync->init();

		// Initialize sync queue.
		$queue = new WSS_Sync_Queue();
		$queue->init();

		// Schedule health check.
		$this->schedule_health_check();

		// Add settings link to plugins page.
		add_filter( 'plugin_action_links_' . WSS_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Schedule periodic health check.
	 */
	private function schedule_health_check() {
		if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( 'wss_health_check' ) ) {
			as_schedule_recurring_action( time() + 3600, 3600, 'wss_health_check', array(), 'woo-smart-search' );
		}
		add_action( 'wss_health_check', array( $this, 'run_health_check' ) );
	}

	/**
	 * Run health check on the search engine connection.
	 */
	public function run_health_check() {
		$engine = wss_get_engine();
		if ( ! $engine ) {
			return;
		}

		$result = $engine->test_connection();
		if ( ! $result['success'] ) {
			wss_log(
				sprintf(
					/* translators: %s: error message */
					__( 'Health check failed: %s', 'woo-smart-search' ),
					$result['message']
				),
				'error'
			);

			// Set admin notice transient.
			set_transient( 'wss_connection_error', $result['message'], 3600 );
		} else {
			delete_transient( 'wss_connection_error' );
		}
	}

	/**
	 * Add settings link to plugins page.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=woo-smart-search' ),
			__( 'Settings', 'woo-smart-search' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
}
