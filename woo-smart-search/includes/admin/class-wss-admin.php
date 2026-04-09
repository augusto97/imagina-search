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
	 * Add menu page.
	 *
	 * Under WooCommerce if active, otherwise as a top-level Settings submenu.
	 */
	public function add_menu_page() {
		if ( wss_is_woocommerce_active() ) {
			add_submenu_page(
				'woocommerce',
				__( 'Smart Search', 'woo-smart-search' ),
				__( 'Smart Search', 'woo-smart-search' ),
				'manage_woocommerce',
				'woo-smart-search',
				array( $this, 'render_settings_page' )
			);
		} else {
			add_options_page(
				__( 'Smart Search', 'woo-smart-search' ),
				__( 'Smart Search', 'woo-smart-search' ),
				'manage_options',
				'woo-smart-search',
				array( $this, 'render_settings_page' )
			);
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_woo-smart-search' !== $hook && 'settings_page_woo-smart-search' !== $hook ) {
			return;
		}

		// Vue admin app (built with Vite).
		$app_js  = WSS_PLUGIN_URL . 'assets/admin-app/js/wss-admin.js';
		$app_css = WSS_PLUGIN_URL . 'assets/admin-app/css/wss-admin.css';

		$has_vue_app = file_exists( WSS_PLUGIN_DIR . 'assets/admin-app/js/wss-admin.js' );

		if ( $has_vue_app ) {
			wp_enqueue_style( 'wss-admin-app', $app_css, array(), WSS_VERSION );
			wp_enqueue_script( 'wss-admin-app', $app_js, array(), WSS_VERSION, true );

			// Pass settings and dynamic data to the Vue app.
			$settings = get_option( 'wss_settings', array() );

			// Gather dynamic data for dropdowns.
			$post_types_data = array();
			$public_types    = get_post_types( array( 'public' => true ), 'objects' );
			$excluded        = array( 'attachment', 'product' );
			foreach ( $public_types as $pt ) {
				if ( in_array( $pt->name, $excluded, true ) ) {
					continue;
				}
				$post_types_data[] = array(
					'value' => $pt->name,
					'label' => $pt->labels->name,
				);
			}

			// WordPress custom fields.
			$wp_custom_fields = array();
			if ( class_exists( 'WSS_Post_Sync' ) ) {
				$configured_pts = WSS_Post_Sync::get_configured_post_types();
				if ( ! empty( $configured_pts ) ) {
					global $wpdb;
					$placeholders = implode( ',', array_fill( 0, count( $configured_pts ), '%s' ) );
					$wp_custom_fields = $wpdb->get_col( $wpdb->prepare(
						"SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm
						 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
						 WHERE p.post_type IN ({$placeholders}) AND pm.meta_key NOT LIKE '\\_%%'
						 ORDER BY pm.meta_key LIMIT 100",
						...$configured_pts
					) );
				}
			}

			// Product categories.
			$product_categories = array();
			if ( wss_is_ecommerce_mode() || 'mixed' === wss_get_content_source() ) {
				$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
				if ( ! is_wp_error( $cats ) ) {
					foreach ( $cats as $cat ) {
						$product_categories[] = array( 'id' => $cat->term_id, 'name' => $cat->name );
					}
				}
			}

			// Product meta keys.
			$product_meta_keys = array();
			if ( wss_is_ecommerce_mode() || 'mixed' === wss_get_content_source() ) {
				global $wpdb;
				$product_meta_keys = $wpdb->get_col(
					"SELECT DISTINCT meta_key FROM {$wpdb->postmeta} pm
					 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					 WHERE p.post_type = 'product' AND pm.meta_key NOT LIKE '\\_%%'
					 ORDER BY meta_key LIMIT 100"
				);
			}

			// WordPress taxonomies for exclusion.
			$wp_taxonomies = array();
			$is_ecom       = wss_is_ecommerce_mode();
			if ( ! $is_ecom || 'mixed' === wss_get_content_source() ) {
				$configured_pts  = class_exists( 'WSS_Post_Sync' ) ? WSS_Post_Sync::get_configured_post_types() : array( 'post' );
				$skip_taxonomies = array( 'product_cat', 'product_tag', 'product_type', 'product_visibility', 'product_shipping_class', 'post_format' );
				$seen            = array();
				foreach ( $configured_pts as $pt ) {
					$taxonomies = get_object_taxonomies( $pt, 'objects' );
					foreach ( $taxonomies as $tax_slug => $tax_obj ) {
						if ( ! $tax_obj->public || in_array( $tax_slug, $skip_taxonomies, true ) || isset( $seen[ $tax_slug ] ) ) {
							continue;
						}
						$seen[ $tax_slug ] = true;
						$terms = get_terms( array( 'taxonomy' => $tax_slug, 'hide_empty' => false ) );
						if ( is_wp_error( $terms ) || empty( $terms ) ) {
							continue;
						}
						$term_data = array();
						foreach ( $terms as $term ) {
							$term_data[] = array( 'id' => $term->term_id, 'name' => $term->name );
						}
						$wp_taxonomies[ $tax_slug ] = array(
							'label' => $tax_obj->labels->name,
							'terms' => $term_data,
						);
					}
				}
			}

			// Pages for results page selector.
			$pages_data = array();
			$all_pages  = get_pages( array( 'post_status' => 'publish,draft,private', 'sort_column' => 'menu_order' ) );
			foreach ( $all_pages as $page ) {
				$pages_data[] = array( 'id' => $page->ID, 'title' => $page->post_title );
			}

			// Published content count.
			$published = 0;
			if ( wss_is_ecommerce_mode() ) {
				$pc        = wp_count_posts( 'product' );
				$published = isset( $pc->publish ) ? (int) $pc->publish : 0;
			} else {
				$configured_pts = class_exists( 'WSS_Post_Sync' ) ? WSS_Post_Sync::get_configured_post_types() : array( 'post' );
				foreach ( $configured_pts as $pt ) {
					$pc         = wp_count_posts( $pt );
					$published += isset( $pc->publish ) ? (int) $pc->publish : 0;
				}
			}

			$last_sync = wss_get_option( 'last_sync', 0 );

			wp_localize_script(
				'wss-admin-app',
				'wssAdmin',
				array(
					'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
					'nonce'              => wp_create_nonce( 'wss_admin_nonce' ),
					'version'            => WSS_VERSION,
					'settings'           => $settings,
					'postTypes'          => $post_types_data,
					'wpCustomFields'     => $wp_custom_fields,
					'productCategories'  => $product_categories,
					'productMetaKeys'    => $product_meta_keys,
					'wpTaxonomies'       => $wp_taxonomies,
					'pages'              => $pages_data,
					'published'          => $published,
					'lastSync'           => $last_sync ? date_i18n( 'Y-m-d H:i', $last_sync ) : '',
				)
			);
		} else {
			// Fallback: legacy admin panel.
			wp_enqueue_style( 'wss-admin', WSS_PLUGIN_URL . 'assets/css/admin.css', array(), WSS_VERSION );
			wp_enqueue_script( 'wss-admin', WSS_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), WSS_VERSION, true );
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
						'confirmFullSync'    => __( 'This will re-index all content. Continue?', 'woo-smart-search' ),
						'confirmClearIndex'  => __( 'This will delete ALL indexed data. Are you sure?', 'woo-smart-search' ),
						'confirmClearLogs'   => __( 'Delete all logs?', 'woo-smart-search' ),
						'saving'             => __( 'Saving...', 'woo-smart-search' ),
						'saved'              => __( 'Settings saved.', 'woo-smart-search' ),
						'error'              => __( 'An error occurred.', 'woo-smart-search' ),
						'connected'          => __( 'Connected', 'woo-smart-search' ),
						'disconnected'       => __( 'Disconnected', 'woo-smart-search' ),
						'loadingAnalytics'   => __( 'Loading analytics...', 'woo-smart-search' ),
					),
				)
			);
		}
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
	 *
	 * If the Vue admin app build exists, renders a minimal mount point.
	 * Otherwise falls back to the legacy PHP-rendered settings page.
	 */
	public function render_settings_page() {
		$required_cap = wss_is_woocommerce_active() ? 'manage_woocommerce' : 'manage_options';
		if ( ! current_user_can( $required_cap ) ) {
			return;
		}

		$has_vue_app = file_exists( WSS_PLUGIN_DIR . 'assets/admin-app/js/wss-admin.js' );

		if ( $has_vue_app ) {
			echo '<div class="wrap"><div id="wss-admin-root"></div></div>';
		} else {
			// Legacy fallback.
			$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'connection'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			include WSS_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
		}
	}
}
