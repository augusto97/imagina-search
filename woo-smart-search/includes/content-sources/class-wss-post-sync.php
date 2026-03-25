<?php
/**
 * Post Synchronization.
 *
 * Handles full (bulk) and incremental sync of WordPress posts/pages/CPTs
 * with Meilisearch. Mirrors WSS_Product_Sync but for generic WordPress content.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Post_Sync
 */
class WSS_Post_Sync {

	/**
	 * Initialize sync hooks for configured post types.
	 */
	public function init() {
		$post_types = self::get_configured_post_types();

		if ( empty( $post_types ) ) {
			return;
		}

		// Generic WordPress hooks for all configured post types.
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );
		add_action( 'before_delete_post', array( $this, 'on_delete_post' ) );
		add_action( 'wp_trash_post', array( $this, 'on_delete_post' ) );
		add_action( 'untrash_post', array( $this, 'on_untrash_post' ) );
		add_action( 'transition_post_status', array( $this, 'on_status_change' ), 10, 3 );
		add_action( 'set_object_terms', array( $this, 'on_terms_change' ), 10, 4 );

		// ACF / custom meta hooks.
		add_action( 'updated_post_meta', array( $this, 'on_meta_change' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'on_meta_change' ), 10, 4 );

		// Bulk sync action.
		add_action( 'wss_bulk_post_sync_batch', array( $this, 'process_bulk_sync_batch' ), 10, 1 );
	}

	/**
	 * Get configured post types for indexing.
	 *
	 * @return array
	 */
	public static function get_configured_post_types(): array {
		$types = wss_get_option( 'wp_post_types', array( 'post' ) );
		if ( empty( $types ) || ! is_array( $types ) ) {
			return array( 'post' );
		}
		return $types;
	}

	/**
	 * Check if a post type is configured for indexing.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private function is_tracked_post_type( string $post_type ): bool {
		return in_array( $post_type, self::get_configured_post_types(), true );
	}

	/**
	 * Handle save_post hook.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 */
	public function on_save_post( $post_id, $post, $update ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! $this->is_tracked_post_type( $post->post_type ) ) {
			return;
		}

		$this->schedule_post_update( $post_id );
	}

	/**
	 * Handle post deletion/trash.
	 *
	 * @param int $post_id Post ID.
	 */
	public function on_delete_post( $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type || ! $this->is_tracked_post_type( $post_type ) ) {
			return;
		}

		WSS_Sync_Queue::add( absint( $post_id ), 'delete' );
	}

	/**
	 * Handle post untrash.
	 *
	 * @param int $post_id Post ID.
	 */
	public function on_untrash_post( $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type || ! $this->is_tracked_post_type( $post_type ) ) {
			return;
		}

		$this->schedule_post_update( $post_id );
	}

	/**
	 * Handle post status transitions.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post object.
	 */
	public function on_status_change( $new_status, $old_status, $post ) {
		if ( ! $this->is_tracked_post_type( $post->post_type ) ) {
			return;
		}

		if ( 'publish' === $new_status ) {
			$this->schedule_post_update( $post->ID );
		} elseif ( 'publish' === $old_status && 'publish' !== $new_status ) {
			WSS_Sync_Queue::add( absint( $post->ID ), 'delete' );
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
		$post_type = get_post_type( $object_id );
		if ( ! $post_type || ! $this->is_tracked_post_type( $post_type ) ) {
			return;
		}

		// Check if this taxonomy belongs to any tracked post type.
		$tax_obj = get_taxonomy( $taxonomy );
		if ( $tax_obj ) {
			$tracked = self::get_configured_post_types();
			$overlap = array_intersect( $tax_obj->object_type, $tracked );
			if ( ! empty( $overlap ) ) {
				$this->schedule_post_update( $object_id );
			}
		}
	}

	/**
	 * Handle post meta changes for ACF and custom fields.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function on_meta_change( $meta_id, $object_id, $meta_key, $meta_value ) {
		$post_type = get_post_type( $object_id );
		if ( ! $post_type || ! $this->is_tracked_post_type( $post_type ) ) {
			return;
		}

		$configured_fields = wss_get_option( 'wp_custom_fields', array() );

		if ( empty( $configured_fields ) || ! is_array( $configured_fields ) ) {
			return;
		}

		$is_tracked = in_array( $meta_key, $configured_fields, true );

		if ( ! $is_tracked ) {
			$unprefixed = ltrim( $meta_key, '_' );
			$is_tracked = in_array( $unprefixed, $configured_fields, true );
		}

		if ( $is_tracked ) {
			$this->schedule_post_update( $object_id );
		}
	}

	/**
	 * Schedule a post update in the sync queue.
	 *
	 * @param int $post_id Post ID.
	 */
	private function schedule_post_update( int $post_id ) {
		WSS_Sync_Queue::add( $post_id, 'update' );
	}

	/**
	 * Start a full bulk synchronization of WordPress content.
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

		$index_name = wss_get_option( 'index_name', 'woo_products', true );

		// Create index if it does not exist.
		$created = $engine->create_index( $index_name );
		if ( ! $created ) {
			wss_log( sprintf( 'Failed to create index "%s". Post sync aborted.', $index_name ), 'error' );
			return array(
				'success' => false,
				'message' => __( 'Failed to create Meilisearch index. Check connection settings.', 'woo-smart-search' ),
			);
		}

		// Configure index settings (skip if mixed mode already configured combined settings).
		if ( ! get_option( 'wss_skip_index_configure' ) ) {
			$this->configure_index_settings( $engine, $index_name );
		}

		// Count total posts.
		$post_types = self::get_configured_post_types();
		$count_query = new WP_Query( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		) );
		$total = (int) $count_query->found_posts;

		if ( 0 === $total ) {
			return array(
				'success' => true,
				'message' => __( 'No content to sync.', 'woo-smart-search' ),
				'total'   => 0,
			);
		}

		$batch_size    = (int) wss_get_option( 'batch_size', 100 );
		$total_batches = (int) ceil( $total / $batch_size );

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
				/* translators: %d: total number of posts */
				__( 'Starting full content sync of %d items', 'woo-smart-search' ),
				$total
			),
			'info'
		);

		$batch_args = array( 'page' => 1 );

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time(),
				'wss_bulk_post_sync_batch',
				array( $batch_args ),
				'woo-smart-search'
			);
		} else {
			$this->process_bulk_sync_batch( $batch_args );
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: 1: total items 2: number of batches */
				__( 'Sync started: %1$d items in %2$d batches', 'woo-smart-search' ),
				$total,
				$total_batches
			),
			'total'   => $total,
			'batches' => $total_batches,
		);
	}

	/**
	 * Process a single bulk sync batch.
	 *
	 * @param array $batch_args Batch arguments containing 'page'.
	 */
	public function process_bulk_sync_batch( $batch_args ) {
		$engine = wss_get_engine();

		if ( ! $engine ) {
			$this->mark_sync_failed( __( 'Search engine not available during batch processing.', 'woo-smart-search' ) );
			return;
		}

		$page       = isset( $batch_args['page'] ) ? (int) $batch_args['page'] : 1;
		$batch_size = (int) wss_get_option( 'batch_size', 100 );
		$index_name = wss_get_option( 'index_name', 'woo_products' );
		$post_types = self::get_configured_post_types();

		$query = new WP_Query( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );

		$post_ids = $query->posts;

		if ( empty( $post_ids ) ) {
			$this->complete_sync();
			return;
		}

		$documents = array();
		$errors    = 0;

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post ) {
				++$errors;
				continue;
			}

			$should_index = apply_filters( 'wss_should_index_post', true, $post );

			if ( ! $should_index ) {
				continue;
			}

			$doc = WSS_Post_Transformer::transform( $post );

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
						__( 'Content batch page %1$d failed: %2$s', 'woo-smart-search' ),
						$page,
						isset( $result['message'] ) ? $result['message'] : 'Unknown error'
					),
					'error'
				);
				$errors += count( $documents );
			}
		}

		// Update progress.
		$progress = get_option( 'wss_sync_progress', array() );

		if ( ! empty( $progress ) ) {
			$progress['processed'] += count( $post_ids );
			$progress['current']    = $page;
			$progress['errors']    += $errors;

			update_option( 'wss_sync_progress', $progress, false );
		}

		// Schedule next batch.
		$next_page = $page + 1;
		$next_args = array( 'page' => $next_page );

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time() + 5,
				'wss_bulk_post_sync_batch',
				array( $next_args ),
				'woo-smart-search'
			);
		} else {
			$this->process_bulk_sync_batch( $next_args );
		}
	}

	/**
	 * Configure the index settings on Meilisearch for WordPress content.
	 *
	 * @param WSS_Meilisearch $engine     Meilisearch instance.
	 * @param string          $index_name Index name.
	 */
	public function configure_index_settings( $engine, $index_name ) {
		$searchable = apply_filters(
			'wss_wp_searchable_attributes',
			array( 'name', 'description', 'full_description', 'categories', 'tags', 'taxonomies_text', 'author' )
		);

		$filterable = apply_filters(
			'wss_wp_filterable_attributes',
			array(
				'categories',
				'category_ids',
				'category_slugs',
				'tags',
				'post_type',
				'author',
				'content_source',
			)
		);

		$sortable = array(
			'date_created',
			'date_modified',
			'name',
			'menu_order',
			'comment_count',
		);

		$displayed = apply_filters(
			'wss_wp_displayed_attributes',
			array(
				'id', 'name', 'slug', 'description', 'permalink',
				'image', 'post_type',
				'categories', 'category_slugs',
				'tags', 'taxonomies',
				'author', 'date_created',
				'comment_count', 'content_source',
			)
		);

		$engine->configure_index( $index_name, array(
			'searchableAttributes'  => $searchable,
			'filterableAttributes'  => $filterable,
			'sortableAttributes'    => $sortable,
			'displayedAttributes'   => $displayed,
		) );

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
	 * Sync a single post immediately.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function sync_single_post( int $post_id ): bool {
		$engine = wss_get_engine();

		if ( ! $engine ) {
			return false;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return false;
		}

		$index_name = wss_get_option( 'index_name', 'woo_products' );

		$should_index = 'publish' === get_post_status( $post_id );
		$should_index = apply_filters( 'wss_should_index_post', $should_index, $post );

		if ( ! $should_index ) {
			$engine->delete_document( $index_name, (string) $post_id );
			return true;
		}

		$document = WSS_Post_Transformer::transform( $post );
		$result   = $engine->index_documents( $index_name, array( $document ) );

		return ! empty( $result['success'] );
	}

	/**
	 * Delete a single post from the index.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function delete_single_post( int $post_id ): bool {
		$engine = wss_get_engine();

		if ( ! $engine ) {
			return false;
		}

		$index_name = wss_get_option( 'index_name', 'woo_products' );
		return $engine->delete_document( $index_name, (string) $post_id );
	}

	/**
	 * Mark sync as completed.
	 */
	private function complete_sync() {
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
				__( 'Full content sync completed: %1$d items processed, %2$d errors', 'woo-smart-search' ),
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
	private function mark_sync_failed( string $message ) {
		$progress = get_option( 'wss_sync_progress', array() );

		if ( ! empty( $progress ) ) {
			$progress['status']   = 'failed';
			$progress['finished'] = time();

			update_option( 'wss_sync_progress', $progress, false );
		}

		wss_log( $message, 'error' );
	}
}
