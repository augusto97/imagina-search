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
	 * Whether Meilisearch is currently available.
	 *
	 * @var bool|null
	 */
	private $meilisearch_available = null;

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

		// Initialize search analytics.
		$analytics = new WSS_Search_Analytics();
		$analytics->init();

		// Schedule health check every 5 minutes.
		$this->schedule_health_check();

		// Auto-fallback filter for when Meilisearch is down.
		add_filter( 'wss_use_native_search', array( $this, 'maybe_fallback_to_native' ) );

		// Admin bar connection status indicator.
		add_action( 'admin_bar_menu', array( $this, 'add_connection_status_to_admin_bar' ), 100 );

		// Add settings link to plugins page.
		add_filter( 'plugin_action_links_' . WSS_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Schedule periodic health check every 5 minutes via Action Scheduler.
	 */
	private function schedule_health_check() {
		if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( 'wss_health_check' ) ) {
			as_schedule_recurring_action( time() + 300, 300, 'wss_health_check', array(), 'woo-smart-search' );
		}
		add_action( 'wss_health_check', array( $this, 'run_health_check' ) );
	}

	/**
	 * Run health check on the Meilisearch connection.
	 *
	 * Sends email notification on failure and sets transients for fallback.
	 */
	public function run_health_check() {
		$engine = WSS_Meilisearch::get_instance();
		if ( ! $engine ) {
			$this->handle_health_check_failure( __( 'Meilisearch is not configured.', 'woo-smart-search' ) );
			return;
		}

		$result = $engine->test_connection();
		if ( ! $result['success'] ) {
			$this->handle_health_check_failure( $result['message'] );
		} else {
			// Connection restored — clear error state.
			$was_down = get_transient( 'wss_connection_error' );
			delete_transient( 'wss_connection_error' );
			update_option( 'wss_meilisearch_available', true );

			if ( $was_down ) {
				wss_log( __( 'Meilisearch connection restored.', 'woo-smart-search' ), 'info' );

				// Notify admin that connection is back.
				$this->send_health_notification(
					__( 'Meilisearch Connection Restored', 'woo-smart-search' ),
					__( 'The Meilisearch connection has been restored. Native WooCommerce search fallback has been deactivated.', 'woo-smart-search' )
				);
			}
		}
	}

	/**
	 * Handle a health check failure.
	 *
	 * @param string $message Error message.
	 */
	private function handle_health_check_failure( $message ) {
		wss_log(
			sprintf(
				/* translators: %s: error message */
				__( 'Health check failed: %s', 'woo-smart-search' ),
				$message
			),
			'error'
		);

		// Set error transient and mark unavailable.
		set_transient( 'wss_connection_error', $message, 600 );
		update_option( 'wss_meilisearch_available', false );

		// Send email notification (throttled to once per hour).
		$last_email = get_transient( 'wss_health_email_sent' );
		if ( ! $last_email ) {
			$this->send_health_notification(
				__( 'Meilisearch Connection Down', 'woo-smart-search' ),
				sprintf(
					/* translators: 1: site name 2: error message */
					__( "Meilisearch is unreachable on %1\$s.\n\nError: %2\$s\n\nThe plugin has automatically fallen back to native WooCommerce search until the connection is restored.", 'woo-smart-search' ),
					get_bloginfo( 'name' ),
					$message
				)
			);
			set_transient( 'wss_health_email_sent', true, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Send a health check email notification to the site admin.
	 *
	 * @param string $subject Email subject.
	 * @param string $body    Email body.
	 */
	private function send_health_notification( $subject, $body ) {
		$admin_email = get_option( 'admin_email' );
		if ( ! $admin_email ) {
			return;
		}

		wp_mail(
			$admin_email,
			'[' . get_bloginfo( 'name' ) . '] ' . $subject,
			$body
		);
	}

	/**
	 * Determine whether to fallback to native WooCommerce search.
	 *
	 * @param bool $use_native Current value.
	 * @return bool
	 */
	public function maybe_fallback_to_native( $use_native ) {
		if ( $use_native ) {
			return true;
		}

		$available = get_option( 'wss_meilisearch_available', true );
		if ( ! $available ) {
			return true;
		}

		// Also check for recent connection error transient.
		$error = get_transient( 'wss_connection_error' );
		if ( $error ) {
			return true;
		}

		return false;
	}

	/**
	 * Add connection status indicator to the WordPress admin bar.
	 *
	 * @param WP_Admin_Bar $admin_bar The admin bar instance.
	 */
	public function add_connection_status_to_admin_bar( $admin_bar ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$error     = get_transient( 'wss_connection_error' );
		$available = get_option( 'wss_meilisearch_available', true );

		if ( $error || ! $available ) {
			$status_icon  = '<span style="color:#dc3232;">&#9679;</span>';
			$status_label = __( 'Meilisearch: Down', 'woo-smart-search' );
		} else {
			$engine = WSS_Meilisearch::get_instance();
			if ( ! $engine ) {
				$status_icon  = '<span style="color:#ffb900;">&#9679;</span>';
				$status_label = __( 'Meilisearch: Not Configured', 'woo-smart-search' );
			} else {
				$status_icon  = '<span style="color:#46b450;">&#9679;</span>';
				$status_label = __( 'Meilisearch: Connected', 'woo-smart-search' );
			}
		}

		$admin_bar->add_node(
			array(
				'id'    => 'wss-connection-status',
				'title' => $status_icon . ' ' . $status_label,
				'href'  => admin_url( 'admin.php?page=woo-smart-search&tab=connection' ),
				'meta'  => array(
					'title' => $status_label,
				),
			)
		);
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
