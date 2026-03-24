<?php
/**
 * Product Synchronization.
 *
 * Handles full (bulk) and incremental product sync with Meilisearch.
 * Uses paginated WP_Query and Action Scheduler chain pattern for bulk sync.
 * Never loads all products into memory at once.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Product_Sync
 */
class WSS_Product_Sync {

	/**
	 * Initialize sync hooks.
	 */
	public function init() {
		// Incremental sync hooks.
		add_action( 'woocommerce_update_product', array( $this, 'schedule_product_update' ), 10, 1 );
		add_action( 'woocommerce_new_product', array( $this, 'schedule_product_update' ), 10, 1 );
		add_action( 'save_post_product', array( $this, 'on_save_post_product' ), 20, 3 );
		add_action( 'before_delete_post', array( $this, 'schedule_product_delete' ) );
		add_action( 'wp_trash_post', array( $this, 'schedule_product_delete' ) );
		add_action( 'untrash_post', array( $this, 'schedule_product_update' ) );
		add_action( 'woocommerce_product_set_stock', array( $this, 'on_stock_change' ) );
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'on_stock_status_change' ), 10, 3 );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'on_variation_stock_change' ) );
		add_action( 'set_object_terms', array( $this, 'on_terms_change' ), 10, 4 );
		add_action( 'transition_post_status', array( $this, 'on_status_change' ), 10, 3 );

		// ACF / custom meta hooks.
		add_action( 'updated_post_meta', array( $this, 'on_meta_change' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'on_meta_change' ), 10, 4 );

		// Bulk sync action (chain pattern).
		add_action( 'wss_bulk_sync_batch', array( $this, 'process_bulk_sync_batch' ), 10, 1 );
	}

	/**
	 * Schedule a product update in the sync queue.
	 *
	 * @param int $product_id Product ID.
	 */
	public function schedule_product_update( $product_id ) {
		$product_id = absint( $product_id );

		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			return;
		}

		WSS_Sync_Queue::add( $product_id, 'update' );
	}

	/**
	 * Handle save_post_product hook.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 */
	public function on_save_post_product( $post_id, $post, $update ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$this->schedule_product_update( $post_id );
	}

	/**
	 * Schedule a product deletion from the index.
	 *
	 * @param int $post_id Post ID.
	 */
	public function schedule_product_delete( $post_id ) {
		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}

		WSS_Sync_Queue::add( absint( $post_id ), 'delete' );
	}

	/**
	 * Handle stock changes.
	 *
	 * @param WC_Product $product Product object.
	 */
	public function on_stock_change( $product ) {
		$this->schedule_product_update( $product->get_id() );
	}

	/**
	 * Handle stock status changes.
	 *
	 * @param int        $product_id   Product ID.
	 * @param string     $stock_status New stock status.
	 * @param WC_Product $product      Product object.
	 */
	public function on_stock_status_change( $product_id, $stock_status, $product ) {
		$this->schedule_product_update( absint( $product_id ) );
	}

	/**
	 * Handle variation stock changes.
	 *
	 * @param WC_Product $variation Variation object.
	 */
	public function on_variation_stock_change( $variation ) {
		$parent_id = $variation->get_parent_id();

		if ( $parent_id ) {
			$this->schedule_product_update( $parent_id );
		}
	}

	/**
	 * Handle taxonomy term changes.
	 *
	 * @param int    $object_id Object ID.
	 * @param array  $terms     Terms.
	 * @param array  $tt_ids    Term taxonomy IDs.
	 * @param string $taxonomy  Taxonomy.
	 */
	public function on_terms_change( $object_id, $terms, $tt_ids, $taxonomy ) {
		$tracked_taxonomies = array( 'product_cat', 'product_tag', 'product_brand', 'pwb-brand' );

		if ( in_array( $taxonomy, $tracked_taxonomies, true ) ) {
			if ( 'product' === get_post_type( $object_id ) ) {
				$this->schedule_product_update( $object_id );
			}
		}
	}

	/**
	 * Handle post status transitions.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post object.
	 */
	public function on_status_change( $new_status, $old_status, $post ) {
		if ( 'product' !== $post->post_type ) {
			return;
		}

		if ( 'publish' === $new_status ) {
			$this->schedule_product_update( $post->ID );
		} elseif ( 'publish' === $old_status && 'publish' !== $new_status ) {
			$this->schedule_product_delete( $post->ID );
		}
	}

	/**
	 * Handle post meta changes for ACF and custom fields.
	 *
	 * Only triggers a sync if the changed meta key is in the configured
	 * custom_fields list and the post is a product.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function on_meta_change( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( 'product' !== get_post_type( $object_id ) ) {
			return;
		}

		// Only react to configured custom fields.
		$configured_fields = wss_get_option( 'custom_fields', array() );

		if ( empty( $configured_fields ) || ! is_array( $configured_fields ) ) {
			return;
		}

		// Check direct match or ACF internal key (prefixed with underscore).
		$is_tracked = in_array( $meta_key, $configured_fields, true );

		if ( ! $is_tracked ) {
			// ACF stores a reference key as _fieldname.
			$unprefixed = ltrim( $meta_key, '_' );
			$is_tracked = in_array( $unprefixed, $configured_fields, true );
		}

		if ( $is_tracked ) {
			$this->schedule_product_update( $object_id );
		}
	}

	/**
	 * Start a full bulk synchronization.
	 *
	 * Uses a paginated approach: counts total products first, then schedules
	 * the first batch. Each batch schedules the next one (chain pattern).
	 *
	 * @return array Status info.
	 */
	public function start_full_sync(): array {
		$engine = wss_get_engine();

		if ( ! $engine ) {
			return array(
				'success' => false,
				'message' => __( 'Search engine not configured.', 'woo-smart-search' ),
			);
		}

		$index_name = wss_get_option( 'index_name', 'woo_products' );

		// Create index if it does not exist.
		$engine->create_index( $index_name );

		// Configure index settings.
		$this->configure_index_settings( $engine, $index_name );

		// Count total products without loading them all into memory.
		$count_args                  = $this->get_product_query_args();
		$count_args['return']        = 'ids';
		$count_args['posts_per_page'] = 1;
		$count_args['page']          = 1;

		$count_query = new WC_Product_Query( $count_args );
		$count_query->get_products();

		// WC_Product_Query uses WP_Query internally; access total from the query.
		$total = 0;
		if ( isset( $count_query->query_vars['paginate'] ) || true ) {
			// Use a paginated query to get total.
			$paginated_args             = $this->get_product_query_args();
			$paginated_args['return']   = 'ids';
			$paginated_args['paginate'] = true;
			$paginated_args['limit']    = 1;
			$paginated_args['page']     = 1;

			$paginated_query = new WC_Product_Query( $paginated_args );
			$result          = $paginated_query->get_products();

			if ( is_object( $result ) && isset( $result->total ) ) {
				$total = (int) $result->total;
			}
		}

		if ( 0 === $total ) {
			return array(
				'success' => true,
				'message' => __( 'No products to sync.', 'woo-smart-search' ),
				'total'   => 0,
			);
		}

		$batch_size    = (int) wss_get_option( 'batch_size', 100 );
		$total_batches = (int) ceil( $total / $batch_size );

		// Store progress via update_option for persistent tracking.
		update_option(
			'wss_sync_progress',
			array(
				'total'     => $total,
				'processed' => 0,
				'batches'   => $total_batches,
				'current'   => 0,
				'status'    => 'running',
				'started'   => time(),
				'errors'    => 0,
			),
			false
		);

		do_action( 'wss_before_full_sync' );

		wss_log(
			sprintf(
				/* translators: %d: total number of products */
				__( 'Starting full sync of %d products', 'woo-smart-search' ),
				$total
			),
			'info'
		);

		// Schedule the first batch. Each batch will schedule the next (chain pattern).
		$batch_args = array(
			'page' => 1,
		);

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time(),
				'wss_bulk_sync_batch',
				array( $batch_args ),
				'woo-smart-search'
			);
		} else {
			// Fallback: process the first batch immediately.
			$this->process_bulk_sync_batch( $batch_args );
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: 1: total products 2: number of batches */
				__( 'Sync started: %1$d products in %2$d batches', 'woo-smart-search' ),
				$total,
				$total_batches
			),
			'total'   => $total,
			'batches' => $total_batches,
		);
	}

	/**
	 * Process a single bulk sync batch and schedule the next one.
	 *
	 * Uses paginated WP_Query via wc_get_products with a page parameter
	 * to fetch only one batch of products at a time. After processing,
	 * schedules the next batch via Action Scheduler (chain pattern).
	 *
	 * @param array $batch_args Batch arguments containing 'page'.
	 */
	public function process_bulk_sync_batch( $batch_args ) {
		$engine = wss_get_engine();

		if ( ! $engine ) {
			self::mark_sync_failed( __( 'Search engine not available during batch processing.', 'woo-smart-search' ) );
			return;
		}

		$page       = isset( $batch_args['page'] ) ? (int) $batch_args['page'] : 1;
		$batch_size = (int) wss_get_option( 'batch_size', 100 );
		$index_name = wss_get_option( 'index_name', 'woo_products' );

		// Fetch one page of product IDs.
		$query_args           = $this->get_product_query_args();
		$query_args['return'] = 'ids';
		$query_args['limit']  = $batch_size;
		$query_args['page']   = $page;

		$product_ids = wc_get_products( $query_args );

		if ( empty( $product_ids ) ) {
			// No more products; mark sync as completed.
			self::complete_sync();
			return;
		}

		$documents = array();
		$errors    = 0;

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				++$errors;
				continue;
			}

			$should_index = apply_filters( 'wss_should_index_product', true, $product );

			if ( ! $should_index ) {
				continue;
			}

			$doc = WSS_Product_Transformer::transform( $product );

			if ( ! empty( $doc ) ) {
				$documents[] = $doc;
			}
		}

		if ( ! empty( $documents ) ) {
			$result = $engine->index_documents( $index_name, $documents );

			if ( empty( $result['success'] ) ) {
				wss_log(
					sprintf(
						/* translators: 1: page number 2: error message */
						__( 'Batch page %1$d failed: %2$s', 'woo-smart-search' ),
						$page,
						isset( $result['message'] ) ? $result['message'] : 'Unknown error'
					),
					'error'
				);
				$errors += count( $documents );
			} else {
				foreach ( $documents as $doc ) {
					do_action( 'wss_product_indexed', $doc['id'], $doc );
				}
			}
		}

		// Update progress.
		$progress = get_option( 'wss_sync_progress', array() );

		if ( ! empty( $progress ) ) {
			$progress['processed'] += count( $product_ids );
			$progress['current']    = $page;
			$progress['errors']    += $errors;

			update_option( 'wss_sync_progress', $progress, false );
		}

		// Schedule next batch (chain pattern).
		$next_page  = $page + 1;
		$next_args  = array( 'page' => $next_page );

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time() + 5,
				'wss_bulk_sync_batch',
				array( $next_args ),
				'woo-smart-search'
			);
		} else {
			// Fallback: process next batch immediately.
			$this->process_bulk_sync_batch( $next_args );
		}
	}

	/**
	 * Mark sync as completed and fire completion hooks.
	 */
	private static function complete_sync() {
		$progress = get_option( 'wss_sync_progress', array() );

		if ( empty( $progress ) ) {
			return;
		}

		$progress['status']   = 'completed';
		$progress['finished'] = time();

		update_option( 'wss_sync_progress', $progress, false );

		do_action( 'wss_after_full_sync', $progress['processed'] );

		wss_log(
			sprintf(
				/* translators: 1: processed count 2: error count */
				__( 'Full sync completed: %1$d products processed, %2$d errors', 'woo-smart-search' ),
				$progress['processed'],
				$progress['errors']
			),
			$progress['errors'] > 0 ? 'warning' : 'info'
		);

		wss_update_option( 'last_sync', time() );
	}

	/**
	 * Mark sync as failed.
	 *
	 * @param string $message Error message.
	 */
	private static function mark_sync_failed( string $message ) {
		$progress = get_option( 'wss_sync_progress', array() );

		if ( ! empty( $progress ) ) {
			$progress['status']   = 'failed';
			$progress['finished'] = time();

			update_option( 'wss_sync_progress', $progress, false );
		}

		wss_log( $message, 'error' );
	}

	/**
	 * Configure the index settings on Meilisearch.
	 *
	 * @param WSS_Meilisearch $engine     Meilisearch instance.
	 * @param string          $index_name Index name.
	 */
	public function configure_index_settings( $engine, $index_name ) {
		$searchable = apply_filters(
			'wss_searchable_attributes',
			array( 'name', 'sku', 'all_skus', 'categories', 'tags', 'brand', 'description', 'attributes_text', 'variations_text' )
		);

		$filterable = apply_filters(
			'wss_filterable_attributes',
			array(
				'categories',
				'category_ids',
				'category_slugs',
				'tags',
				'price',
				'price_min',
				'price_max',
				'stock_status',
				'on_sale',
				'featured',
				'rating',
				'brand',
				'type',
			)
		);

		// Dynamically add product attributes as filterable (attributes.Color, attributes.Size, etc.).
		$attribute_taxonomies = wc_get_attribute_taxonomies();
		$attribute_names      = array();
		if ( ! empty( $attribute_taxonomies ) ) {
			foreach ( $attribute_taxonomies as $tax ) {
				$label             = $tax->attribute_label ? $tax->attribute_label : $tax->attribute_name;
				$filterable[]      = 'attributes.' . $label;
				$attribute_names[] = $label;
			}
		}
		// Store the attribute names for frontend facet requests.
		update_option( 'wss_product_attribute_names', $attribute_names, true );

		$sortable = array(
			'price',
			'price_min',
			'price_max',
			'date_created',
			'date_modified',
			'name',
			'rating',
			'total_sales',
			'menu_order',
		);

		// Displayed attributes — only expose safe fields to frontend direct search.
		// Sensitive fields (stock_quantity, total_sales, custom_fields, etc.) are excluded.
		$displayed = apply_filters(
			'wss_displayed_attributes',
			array(
				'id', 'name', 'slug', 'description', 'sku', 'permalink',
				'image', 'gallery',
				'price', 'regular_price', 'sale_price', 'price_min', 'price_max',
				'on_sale', 'currency',
				'stock_status',
				'categories', 'category_slugs',
				'tags', 'brand',
				'attributes',
				'rating', 'review_count',
				'type',
			)
		);

		$settings = apply_filters(
			'wss_index_settings',
			array(
				'searchableAttributes'  => $searchable,
				'filterableAttributes'  => $filterable,
				'sortableAttributes'    => $sortable,
				'displayedAttributes'   => $displayed,
			)
		);

		$engine->configure_index( $index_name, $settings );
		$engine->set_searchable_attributes( $index_name, $searchable );
		$engine->set_filterable_attributes( $index_name, $filterable );
		$engine->set_sortable_attributes( $index_name, $sortable );
		$engine->set_displayed_attributes( $index_name, $displayed );

		// Configure synonyms if set.
		$synonyms = wss_get_option( 'synonyms', '' );

		if ( ! empty( $synonyms ) ) {
			$synonyms_array = json_decode( $synonyms, true );

			if ( is_array( $synonyms_array ) ) {
				$engine->set_synonyms( $index_name, $synonyms_array );
			}
		}

		// Configure stop words if set.
		$stop_words = wss_get_option( 'stop_words', '' );

		if ( ! empty( $stop_words ) ) {
			$stop_words_array = array_map( 'trim', explode( ',', $stop_words ) );
			$engine->set_stop_words( $index_name, $stop_words_array );
		}
	}

	/**
	 * Update only the filterable attributes on Meilisearch.
	 *
	 * This can be called without a full re-sync to register new
	 * product attributes as filterable facets.
	 *
	 * @return bool True on success.
	 */
	public static function update_filterable_attributes(): bool {
		$engine = wss_get_engine();
		if ( ! $engine ) {
			return false;
		}

		$index_name = wss_get_option( 'index_name', 'woo_products' );

		$filterable = apply_filters(
			'wss_filterable_attributes',
			array(
				'categories',
				'category_ids',
				'category_slugs',
				'tags',
				'price',
				'price_min',
				'price_max',
				'stock_status',
				'on_sale',
				'featured',
				'rating',
				'brand',
				'type',
			)
		);

		// Add product attributes.
		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			$taxonomies = wc_get_attribute_taxonomies();
			if ( ! empty( $taxonomies ) ) {
				foreach ( $taxonomies as $tax ) {
					$label        = $tax->attribute_label ? $tax->attribute_label : $tax->attribute_name;
					$filterable[] = 'attributes.' . $label;
				}
			}
		}

		$result = $engine->set_filterable_attributes( $index_name, $filterable );

		// Also ensure displayed attributes are set for safe direct frontend search.
		$displayed = apply_filters(
			'wss_displayed_attributes',
			array(
				'id', 'name', 'slug', 'description', 'sku', 'permalink',
				'image', 'gallery',
				'price', 'regular_price', 'sale_price', 'price_min', 'price_max',
				'on_sale', 'currency',
				'stock_status',
				'categories', 'category_slugs',
				'tags', 'brand',
				'attributes',
				'rating', 'review_count',
				'type',
			)
		);
		$engine->set_displayed_attributes( $index_name, $displayed );

		return $result;
	}

	/**
	 * Ensure Meilisearch filterable attributes include product attributes.
	 *
	 * Runs once per plugin version to keep the index settings in sync
	 * without requiring a full re-sync.
	 */
	public static function maybe_update_filterable_attributes() {
		$version_key = 'wss_filterable_attrs_version';
		if ( get_option( $version_key, '' ) === WSS_VERSION ) {
			return;
		}

		if ( self::update_filterable_attributes() ) {
			update_option( $version_key, WSS_VERSION, true );
		}
	}

	/**
	 * Get the WC_Product_Query arguments for syncing.
	 *
	 * @return array
	 */
	private function get_product_query_args(): array {
		$args = array(
			'status'  => 'publish',
			'orderby' => 'ID',
			'order'   => 'ASC',
		);

		// Visibility filter.
		if ( 'yes' !== wss_get_option( 'index_hidden', 'no' ) ) {
			$args['visibility'] = 'visible';
		}

		// Stock filter.
		if ( 'yes' !== wss_get_option( 'index_out_of_stock', 'yes' ) ) {
			$args['stock_status'] = 'instock';
		}

		// Category exclusion.
		$exclude_cats = wss_get_option( 'exclude_categories', array() );

		if ( ! empty( $exclude_cats ) ) {
			$args['category'] = array();
			$all_cats         = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'fields'     => 'slugs',
					'hide_empty' => false,
					'exclude'    => $exclude_cats,
				)
			);

			if ( ! is_wp_error( $all_cats ) ) {
				$args['category'] = $all_cats;
			}
		}

		return apply_filters( 'wss_product_query_args', $args );
	}

	/**
	 * Sync a single product immediately.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function sync_single_product( int $product_id ): bool {
		$engine = wss_get_engine();

		if ( ! $engine ) {
			return false;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return false;
		}

		$index_name = wss_get_option( 'index_name', 'woo_products' );

		// Check if product should be indexed.
		$should_index = 'publish' === get_post_status( $product_id );
		$should_index = apply_filters( 'wss_should_index_product', $should_index, $product );

		if ( ! $should_index ) {
			$engine->delete_document( $index_name, (string) $product_id );
			do_action( 'wss_product_removed', $product_id );
			return true;
		}

		$document = WSS_Product_Transformer::transform( $product );
		$result   = $engine->index_documents( $index_name, array( $document ) );

		if ( ! empty( $result['success'] ) ) {
			do_action( 'wss_product_indexed', $product_id, $document );
			return true;
		}

		do_action( 'wss_sync_error', $result, $product_id );
		return false;
	}

	/**
	 * Delete a single product from the index.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function delete_single_product( int $product_id ): bool {
		$engine = wss_get_engine();

		if ( ! $engine ) {
			return false;
		}

		$index_name = wss_get_option( 'index_name', 'woo_products' );
		$result     = $engine->delete_document( $index_name, (string) $product_id );

		if ( $result ) {
			do_action( 'wss_product_removed', $product_id );
		}

		return $result;
	}
}
