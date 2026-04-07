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
		// One-time fix: restore defaults for display settings that were incorrectly reset.
		$this->maybe_fix_display_settings();

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

		// Initialize content source based on mode.
		$content_source = wss_get_content_source();

		if ( 'mixed' === $content_source ) {
			// Mixed mode: initialize both syncs.
			if ( wss_is_woocommerce_active() ) {
				$sync = new WSS_Product_Sync();
				$sync->init();
				add_action( 'admin_init', array( 'WSS_Product_Sync', 'maybe_update_filterable_attributes' ) );
			}
			$post_sync = new WSS_Post_Sync();
			$post_sync->init();
		} elseif ( wss_is_ecommerce_mode() ) {
			// WooCommerce product sync.
			$sync = new WSS_Product_Sync();
			$sync->init();

			// Ensure Meilisearch filterable attributes include product attributes.
			add_action( 'admin_init', array( 'WSS_Product_Sync', 'maybe_update_filterable_attributes' ) );
		} else {
			// WordPress content sync (posts, pages, CPTs).
			$post_sync = new WSS_Post_Sync();
			$post_sync->init();
		}

		// Initialize sync queue (shared by both modes).
		$queue = new WSS_Sync_Queue();
		$queue->init();

		// Initialize search analytics.
		$analytics = new WSS_Search_Analytics();
		$analytics->init();

		// Schedule health check every 5 minutes.
		$this->schedule_health_check();

		// Schedule periodic re-indexation.
		$this->schedule_periodic_reindex();

		// Auto-fallback filter for when Meilisearch is down.
		add_filter( 'wss_use_native_search', array( $this, 'maybe_fallback_to_native' ) );

		// Dashboard widget for connection status.
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );

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
	 * Schedule periodic re-indexation to catch changes that bypassed hooks.
	 *
	 * Runs every 6 hours by default. Catches: direct DB updates, REST API changes
	 * from external apps, bulk edit plugins, scheduled sales, CSV imports.
	 */
	private function schedule_periodic_reindex() {
		$interval = (int) apply_filters( 'wss_reindex_interval', 6 * HOUR_IN_SECONDS );

		if ( $interval <= 0 ) {
			return; // Disabled via filter.
		}

		if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( 'wss_periodic_reindex' ) ) {
			as_schedule_recurring_action( time() + $interval, $interval, 'wss_periodic_reindex', array(), 'woo-smart-search' );
		}
	}

	/**
	 * Run health check on the Meilisearch connection.
	 *
	 * Sends email notification on failure and sets transients for fallback.
	 */
	public function run_health_check() {
		// Local engine: always healthy — skip remote health checks.
		if ( wss_is_local_engine() ) {
			delete_transient( 'wss_connection_error' );
			update_option( 'wss_meilisearch_available', true );
			return;
		}

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
	 * Register the dashboard widget.
	 */
	public function add_dashboard_widget() {
		$required_cap = wss_is_woocommerce_active() ? 'manage_woocommerce' : 'manage_options';
		if ( ! current_user_can( $required_cap ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'wss_dashboard_widget',
			__( 'Woo Smart Search', 'woo-smart-search' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render the dashboard widget content.
	 */
	public function render_dashboard_widget() {
		$error     = get_transient( 'wss_connection_error' );
		$available = get_option( 'wss_meilisearch_available', true );
		$engine    = WSS_Meilisearch::get_instance();

		// Connection status.
		if ( $error || ! $available ) {
			$status_color = '#dc3232';
			$status_label = __( 'Down', 'woo-smart-search' );
			$status_msg   = $error ? $error : __( 'Meilisearch is unreachable.', 'woo-smart-search' );
		} elseif ( ! $engine ) {
			$status_color = '#ffb900';
			$status_label = __( 'Not Configured', 'woo-smart-search' );
			$status_msg   = __( 'Please configure your Meilisearch connection.', 'woo-smart-search' );
		} else {
			$status_color = '#46b450';
			$status_label = __( 'Connected', 'woo-smart-search' );
			$status_msg   = '';
		}

		// Document count.
		$doc_count = 0;
		$last_sync = '';
		if ( $engine && ! $error ) {
			$index_name = wss_get_option( 'index_name', 'woo_products' );
			$stats      = $engine->get_index_stats( $index_name );
			$doc_count  = isset( $stats['numberOfDocuments'] ) ? (int) $stats['numberOfDocuments'] : 0;

			$sync_ts = wss_get_option( 'last_sync', 0 );
			if ( $sync_ts ) {
				$last_sync = human_time_diff( $sync_ts ) . ' ' . __( 'ago', 'woo-smart-search' );
			}
		}

		// Search stats (today).
		$searches_today = 0;
		if ( class_exists( 'WSS_Search_Analytics' ) ) {
			$analytics = new WSS_Search_Analytics();
			$volume    = $analytics->get_search_volume( 'today' );
			foreach ( $volume as $row ) {
				$searches_today += (int) $row->count;
			}
		}
		?>
		<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
			<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?php echo esc_attr( $status_color ); ?>;"></span>
			<strong><?php echo esc_html( $status_label ); ?></strong>
			<?php if ( $status_msg ) : ?>
				<span style="color:#666;font-size:12px;">&mdash; <?php echo esc_html( $status_msg ); ?></span>
			<?php endif; ?>
		</div>

		<table style="width:100%;border-collapse:collapse;font-size:13px;">
			<tr>
				<td style="padding:4px 0;color:#666;"><?php esc_html_e( 'Indexed products', 'woo-smart-search' ); ?></td>
				<td style="padding:4px 0;text-align:right;font-weight:600;"><?php echo esc_html( number_format_i18n( $doc_count ) ); ?></td>
			</tr>
			<?php if ( $last_sync ) : ?>
			<tr>
				<td style="padding:4px 0;color:#666;"><?php esc_html_e( 'Last sync', 'woo-smart-search' ); ?></td>
				<td style="padding:4px 0;text-align:right;"><?php echo esc_html( $last_sync ); ?></td>
			</tr>
			<?php endif; ?>
			<tr>
				<td style="padding:4px 0;color:#666;"><?php esc_html_e( 'Searches today', 'woo-smart-search' ); ?></td>
				<td style="padding:4px 0;text-align:right;font-weight:600;"><?php echo esc_html( number_format_i18n( $searches_today ) ); ?></td>
			</tr>
		</table>

		<p style="margin:12px 0 0;text-align:right;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-smart-search' ) ); ?>" class="button button-small">
				<?php esc_html_e( 'Settings', 'woo-smart-search' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-smart-search&tab=analytics' ) ); ?>" class="button button-small">
				<?php esc_html_e( 'Analytics', 'woo-smart-search' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * One-time migration to restore display settings that were
	 * incorrectly reset to 'no' by the cross-tab save bug.
	 */
	private function maybe_fix_display_settings() {
		if ( get_option( 'wss_display_fix_v1' ) ) {
			return;
		}

		$settings = get_option( 'wss_settings', array() );

		// If show_image was set to 'no' but the user never explicitly disabled it,
		// restore defaults. We detect this by checking if ALL display bools are 'no'
		// (which would only happen from the cross-tab save bug).
		$display_fields = array( 'show_image', 'show_price', 'show_category', 'show_stock' );
		$all_no         = true;
		foreach ( $display_fields as $field ) {
			if ( isset( $settings[ $field ] ) && 'yes' === $settings[ $field ] ) {
				$all_no = false;
				break;
			}
		}

		if ( $all_no && ! empty( $settings ) ) {
			$defaults = array(
				'show_image'       => 'yes',
				'show_price'       => 'yes',
				'show_category'    => 'yes',
				'show_stock'       => 'yes',
				'show_sale_badge'  => 'yes',
				'enable_analytics' => 'yes',
			);
			foreach ( $defaults as $key => $value ) {
				if ( ! isset( $settings[ $key ] ) || 'no' === $settings[ $key ] ) {
					$settings[ $key ] = $value;
				}
			}
			update_option( 'wss_settings', $settings );
		}

		update_option( 'wss_display_fix_v1', true );
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
