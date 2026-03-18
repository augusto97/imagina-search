<?php
/**
 * Shortcode handler.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Shortcode
 */
class WSS_Shortcode {

	/**
	 * Initialize shortcode.
	 */
	public function init() {
		add_shortcode( 'woo_smart_search', array( $this, 'render' ) );
		add_shortcode( 'woo_smart_search_results', array( $this, 'render_results_page' ) );

		// Register WordPress widget.
		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		// Register Gutenberg block.
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'placeholder'        => '',
				'max_results'        => '',
				'show_image'         => '',
				'show_price'         => '',
				'show_category'      => '',
				'show_sku'           => '',
				'show_stock'         => '',
				'show_rating'        => '',
				'theme'              => '',
				'layout'             => '',
				'width'              => '100%',
				'categories'         => '',
				'exclude_categories' => '',
			),
			$atts,
			'woo_smart_search'
		);

		// Ensure assets are loaded.
		$frontend = new WSS_Frontend();
		$frontend->enqueue_assets();

		return $frontend->get_search_widget_html( $atts );
	}

	/**
	 * Render the search results page shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_results_page( $atts ) {
		$atts = shortcode_atts( array(), $atts, 'woo_smart_search_results' );

		// Ensure assets are loaded.
		$frontend = new WSS_Frontend();
		$frontend->enqueue_assets();
		$frontend->enqueue_results_page_assets( true );

		$query = get_search_query();
		if ( empty( $query ) && isset( $_GET['q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$query = sanitize_text_field( wp_unslash( $_GET['q'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$template = locate_template( 'woo-smart-search/results-page.php' );
		if ( ! $template ) {
			$template = WSS_PLUGIN_DIR . 'templates/results-page.php';
		}

		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * Register WordPress classic widget.
	 */
	public function register_widget() {
		register_widget( 'WSS_Search_Widget_WP' );
	}

	/**
	 * Register Gutenberg block.
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			'woo-smart-search/search-bar',
			array(
				'editor_script'   => 'wss-block-editor',
				'render_callback' => array( $this, 'render_block' ),
				'attributes'      => array(
					'placeholder'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'width'        => array(
						'type'    => 'string',
						'default' => '100%',
					),
					'layout'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'showImage'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'showPrice'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'showCategory' => array(
						'type'    => 'string',
						'default' => '',
					),
					'maxResults'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'theme'        => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		// Register block editor script.
		wp_register_script(
			'wss-block-editor',
			WSS_PLUGIN_URL . 'assets/js/block-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components' ),
			WSS_VERSION,
			true
		);
	}

	/**
	 * Render the Gutenberg block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		// Map camelCase block attributes to shortcode snake_case.
		$map = array(
			'showImage'    => 'show_image',
			'showPrice'    => 'show_price',
			'showCategory' => 'show_category',
			'maxResults'   => 'max_results',
		);
		foreach ( $map as $camel => $snake ) {
			if ( ! empty( $attributes[ $camel ] ) ) {
				$attributes[ $snake ] = $attributes[ $camel ];
			}
			unset( $attributes[ $camel ] );
		}
		return $this->render( $attributes );
	}
}

/**
 * WordPress Classic Widget for Smart Search.
 */
class WSS_Search_Widget_WP extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'wss_search_widget',
			__( 'Woo Smart Search', 'woo-smart-search' ),
			array(
				'description' => __( 'Smart product search bar with instant results.', 'woo-smart-search' ),
			)
		);
	}

	/**
	 * Front-end display.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $instance['title'] ) ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$frontend = new WSS_Frontend();
		$frontend->enqueue_assets();
		echo $frontend->get_search_widget_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			array(
				'placeholder' => $instance['placeholder'] ?? '',
				'width'       => '100%',
			)
		);

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Back-end widget form.
	 *
	 * @param array $instance Widget instance.
	 */
	public function form( $instance ) {
		$title       = $instance['title'] ?? __( 'Search Products', 'woo-smart-search' );
		$placeholder = $instance['placeholder'] ?? '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'woo-smart-search' ); ?>
			</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				   type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'placeholder' ) ); ?>">
				<?php esc_html_e( 'Placeholder:', 'woo-smart-search' ); ?>
			</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'placeholder' ) ); ?>"
				   name="<?php echo esc_attr( $this->get_field_name( 'placeholder' ) ); ?>"
				   type="text" value="<?php echo esc_attr( $placeholder ); ?>" />
		</p>
		<?php
	}

	/**
	 * Save widget settings.
	 *
	 * @param array $new_instance New instance values.
	 * @param array $old_instance Old instance values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance                = array();
		$instance['title']       = sanitize_text_field( $new_instance['title'] ?? '' );
		$instance['placeholder'] = sanitize_text_field( $new_instance['placeholder'] ?? '' );
		return $instance;
	}
}
