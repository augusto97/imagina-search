<?php
/**
 * Product Synchronization.
 *
 * Handles full (bulk) and incremental product sync with the search engine.
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
		add_action( 'woocommerce_variation_set_stock', array( $this, 'on_variation_stock_change' ) );
		add_action( 'set_object_terms', array( $this, 'on_terms_change' ), 10, 4 );
		add_action( 'transition_post_status', array( $this, 'on_status_change' ), 10, 3 );

		// Bulk sync action.
		add_action( 'wss_bulk_sync_batch', array( $this, 'process_bulk_sync_batch' ), 10, 2 );
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
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      Terms.
	 * @param array  $tt_ids     Term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy.
	 */
	public function on_terms_change( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, array( 'product_cat', 'product_tag', 'product_brand', 'pwb-brand' ), true ) ) {
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
	 * Start a full bulk synchronization.
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

		// Create index if it doesn't exist.
		$engine->create_index( $index_name );

		// Configure index settings.
		$this->configure_index_settings( $engine, $index_name );

		// Count products.
		$args = $this->get_product_query_args();
		$args['return']         = 'ids';
		$args['posts_per_page'] = -1;

		$query       = new WC_Product_Query( $args );
		$product_ids = $query->get_products();
		$total       = count( $product_ids );

		if ( 0 === $total ) {
			return array(
				'success' => true,
				'message' => __( 'No products to sync.', 'woo-smart-search' ),
				'total'   => 0,
			);
		}

		$batch_size = (int) wss_get_option( 'batch_size', 100 );
		$batches    = array_chunk( $product_ids, $batch_size );

		// Store progress.
		set_transient(
			'wss_sync_progress',
			array(
				'total'     => $total,
				'processed' => 0,
				'batches'   => count( $batches ),
				'current'   => 0,
				'status'    => 'running',
				'started'   => time(),
				'errors'    => 0,
			),
			3600
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

		// Schedule batches via Action Scheduler.
		foreach ( $batches as $index => $batch ) {
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action(
					time() + ( $index * 5 ),
					'wss_bulk_sync_batch',
					array( $batch, $index ),
					'woo-smart-search'
				);
			} else {
				// Fallback: process immediately.
				$this->process_bulk_sync_batch( $batch, $index );
			}
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: 1: total products 2: number of batches */
				__( 'Sync started: %1$d products in %2$d batches', 'woo-smart-search' ),
				$total,
				count( $batches )
			),
			'total'   => $total,
			'batches' => count( $batches ),
		);
	}

	/**
	 * Process a single bulk sync batch.
	 *
	 * @param array $product_ids Product IDs in this batch.
	 * @param int   $batch_index Batch index.
	 */
	public function process_bulk_sync_batch( $product_ids, $batch_index ) {
		$engine = wss_get_engine();
		if ( ! $engine ) {
			return;
		}

		$index_name = wss_get_option( 'index_name', 'woo_products' );
		$documents  = array();
		$errors     = 0;

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				$errors++;
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
						/* translators: 1: batch index 2: error message */
						__( 'Batch %1$d failed: %2$s', 'woo-smart-search' ),
						$batch_index,
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
		$progress = get_transient( 'wss_sync_progress' );
		if ( $progress ) {
			$progress['processed'] += count( $product_ids );
			$progress['current']    = $batch_index + 1;
			$progress['errors']    += $errors;

			if ( $progress['processed'] >= $progress['total'] ) {
				$progress['status']   = 'completed';
				$progress['finished'] = time();

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

			set_transient( 'wss_sync_progress', $progress, 3600 );
		}
	}

	/**
	 * Configure the index settings on the search engine.
	 *
	 * @param WSS_Search_Engine $engine     Engine instance.
	 * @param string            $index_name Index name.
	 */
	public function configure_index_settings( $engine, $index_name ) {
		$searchable = apply_filters(
			'wss_searchable_attributes',
			array( 'name', 'sku', 'categories', 'tags', 'brand', 'description', 'attributes_text', 'variations_text' )
		);

		$filterable = apply_filters(
			'wss_filterable_attributes',
			array(
				'categories', 'category_ids', 'category_slugs', 'tags',
				'price', 'price_min', 'price_max',
				'stock_status', 'on_sale', 'featured', 'rating', 'brand', 'type',
			)
		);

		$sortable = array(
			'price', 'price_min', 'price_max',
			'date_created', 'date_modified',
			'name', 'rating', 'total_sales', 'menu_order',
		);

		$settings = apply_filters(
			'wss_index_settings',
			array(
				'searchableAttributes' => $searchable,
				'filterableAttributes' => $filterable,
				'sortableAttributes'   => $sortable,
			)
		);

		$engine->configure_index( $index_name, $settings );
		$engine->set_searchable_attributes( $index_name, $searchable );
		$engine->set_filterable_attributes( $index_name, $filterable );
		$engine->set_sortable_attributes( $index_name, $sortable );

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
	 * Get the WC_Product_Query arguments for syncing.
	 *
	 * @return array
	 */
	private function get_product_query_args(): array {
		$args = array(
			'status'  => 'publish',
			'limit'   => -1,
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
