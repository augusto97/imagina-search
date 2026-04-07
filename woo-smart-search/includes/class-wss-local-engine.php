<?php
/**
 * Local Search Engine (MySQL-based inverted index).
 *
 * Provides a zero-dependency search engine that stores an inverted index
 * in custom WordPress tables. Uses tokenization + TF-IDF scoring for
 * relevance-ranked full-text search without requiring an external service.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Local_Engine
 */
class WSS_Local_Engine implements WSS_Search_Engine {

	/**
	 * Singleton instance.
	 *
	 * @var WSS_Local_Engine|null
	 */
	private static $instance = null;

	/**
	 * Index settings cache.
	 *
	 * @var array
	 */
	private $index_settings = array();

	/**
	 * Stop words list.
	 *
	 * @var array
	 */
	private $stop_words = array();

	/**
	 * Synonyms map.
	 *
	 * @var array
	 */
	private $synonyms = array();

	/**
	 * In-memory result cache for the current request.
	 *
	 * @var array
	 */
	private $memory_cache = array();

	/**
	 * Default cache TTL in seconds (5 minutes).
	 */
	const CACHE_TTL = 300;

	/**
	 * Get the singleton instance.
	 *
	 * @return WSS_Local_Engine
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->load_settings();
		}
		return self::$instance;
	}

	/**
	 * Reset the singleton.
	 */
	public static function reset() {
		self::$instance = null;
	}

	/**
	 * Load index settings from database.
	 */
	private function load_settings() {
		$this->index_settings = get_option( 'wss_local_index_settings', array() );
		$this->stop_words     = get_option( 'wss_local_stop_words', array() );
		$this->synonyms       = get_option( 'wss_local_synonyms', array() );
	}

	/**
	 * Get the engine type.
	 *
	 * @return string
	 */
	public function get_engine_type(): string {
		return 'local';
	}

	/**
	 * Test the connection (always succeeds for local engine).
	 *
	 * @return array
	 */
	public function test_connection(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wss_index_documents';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! $exists ) {
			// Auto-create tables if missing (e.g. engine switched without reactivation).
			self::create_tables();

			// Re-check after creation.
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( ! $exists ) {
				return array(
					'success' => false,
					'message' => __( 'Could not create local search index tables. Check database permissions.', 'woo-smart-search' ),
					'version' => '',
				);
			}
		}

		return array(
			'success' => true,
			'message' => __( 'Local search engine is ready.', 'woo-smart-search' ),
			'version' => 'Local v' . WSS_VERSION,
		);
	}

	/**
	 * Create the index (tables already exist from activation, this is a no-op).
	 *
	 * @param string $index_name Index name.
	 * @return bool
	 */
	public function create_index( string $index_name ): bool {
		self::create_tables();
		return true;
	}

	/**
	 * Delete an index (clear all data for the given index).
	 *
	 * @param string $index_name Index name.
	 * @return bool
	 */
	public function delete_index( string $index_name ): bool {
		return $this->delete_all_documents( $index_name );
	}

	/**
	 * Get index statistics.
	 *
	 * @param string $index_name Index name.
	 * @return array
	 */
	public function get_index_stats( string $index_name ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wss_index_documents';
		$count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE index_name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$index_name
			)
		);

		return array(
			'numberOfDocuments' => $count,
			'isIndexing'        => false,
		);
	}

	/**
	 * Configure index settings.
	 *
	 * @param string $index_name Index name.
	 * @param array  $settings   Settings.
	 * @return bool
	 */
	public function configure_index( string $index_name, array $settings ): bool {
		$this->index_settings[ $index_name ] = $settings;
		update_option( 'wss_local_index_settings', $this->index_settings );
		return true;
	}

	/**
	 * Set filterable attributes for an index.
	 *
	 * @param string $index_name Index name.
	 * @param array  $attributes Filterable attribute names.
	 * @return bool
	 */
	public function set_filterable_attributes( string $index_name, array $attributes ): bool {
		if ( ! isset( $this->index_settings[ $index_name ] ) ) {
			$this->index_settings[ $index_name ] = array();
		}
		$this->index_settings[ $index_name ]['filterableAttributes'] = $attributes;
		update_option( 'wss_local_index_settings', $this->index_settings );
		return true;
	}

	/**
	 * Set sortable attributes for an index.
	 *
	 * @param string $index_name Index name.
	 * @param array  $attributes Sortable attribute names.
	 * @return bool
	 */
	public function set_sortable_attributes( string $index_name, array $attributes ): bool {
		if ( ! isset( $this->index_settings[ $index_name ] ) ) {
			$this->index_settings[ $index_name ] = array();
		}
		$this->index_settings[ $index_name ]['sortableAttributes'] = $attributes;
		update_option( 'wss_local_index_settings', $this->index_settings );
		return true;
	}

	/**
	 * Set displayed attributes for an index.
	 *
	 * @param string $index_name Index name.
	 * @param array  $attributes Displayed attribute names.
	 * @return bool
	 */
	public function set_displayed_attributes( string $index_name, array $attributes ): bool {
		if ( ! isset( $this->index_settings[ $index_name ] ) ) {
			$this->index_settings[ $index_name ] = array();
		}
		$this->index_settings[ $index_name ]['displayedAttributes'] = $attributes;
		update_option( 'wss_local_index_settings', $this->index_settings );
		return true;
	}

	/**
	 * Index documents into the local inverted index.
	 *
	 * @param string $index_name Index name.
	 * @param array  $documents  Documents to index.
	 * @return array
	 */
	public function index_documents( string $index_name, array $documents ): array {
		global $wpdb;

		$docs_table     = $wpdb->prefix . 'wss_index_documents';
		$terms_table    = $wpdb->prefix . 'wss_index_terms';
		$postings_table = $wpdb->prefix . 'wss_index_postings';

		// Invalidate search cache when documents change.
		$this->invalidate_cache( $index_name );

		$settings    = isset( $this->index_settings[ $index_name ] ) ? $this->index_settings[ $index_name ] : array();
		$searchable  = isset( $settings['searchableAttributes'] ) ? $settings['searchableAttributes'] : array( 'name', 'description' );
		$indexed     = 0;

		foreach ( $documents as $doc ) {
			if ( ! isset( $doc['id'] ) ) {
				continue;
			}

			$doc_id = (int) $doc['id'];

			// Upsert the document JSON.
			$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT id FROM {$docs_table} WHERE index_name = %s AND doc_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$index_name,
					$doc_id
				)
			);

			$doc_json = wp_json_encode( $doc, JSON_UNESCAPED_UNICODE );

			if ( $existing ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$docs_table,
					array(
						'doc_data'   => $doc_json,
						'indexed_at' => current_time( 'mysql' ),
					),
					array( 'id' => $existing ),
					array( '%s', '%s' ),
					array( '%d' )
				);
				// Clear old postings for re-indexing.
				$wpdb->delete( $postings_table, array( 'doc_id' => $doc_id, 'index_name' => $index_name ), array( '%d', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			} else {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$docs_table,
					array(
						'index_name' => $index_name,
						'doc_id'     => $doc_id,
						'doc_data'   => $doc_json,
						'indexed_at' => current_time( 'mysql' ),
					),
					array( '%s', '%d', '%s', '%s' )
				);
			}

			// Build inverted index from searchable fields.
			$all_tokens = array();
			foreach ( $searchable as $field ) {
				$value = $this->extract_field_value( $doc, $field );
				if ( empty( $value ) ) {
					continue;
				}
				$tokens = $this->tokenize( $value );
				foreach ( $tokens as $token ) {
					if ( ! isset( $all_tokens[ $token ] ) ) {
						$all_tokens[ $token ] = 0;
					}
					++$all_tokens[ $token ];
				}
			}

			// Also index all string/array values for facet filtering.
			$filterable = isset( $settings['filterableAttributes'] ) ? $settings['filterableAttributes'] : array();
			foreach ( $filterable as $field ) {
				$value = $this->extract_field_value( $doc, $field );
				if ( ! empty( $value ) ) {
					$tokens = $this->tokenize( $value );
					foreach ( $tokens as $token ) {
						if ( ! isset( $all_tokens[ $token ] ) ) {
							$all_tokens[ $token ] = 0;
						}
						++$all_tokens[ $token ];
					}
				}
			}

			// Expand synonyms.
			$synonym_tokens = array();
			foreach ( array_keys( $all_tokens ) as $token ) {
				if ( isset( $this->synonyms[ $token ] ) ) {
					foreach ( $this->synonyms[ $token ] as $syn ) {
						$syn_lower = mb_strtolower( $syn );
						if ( ! isset( $all_tokens[ $syn_lower ] ) && ! isset( $synonym_tokens[ $syn_lower ] ) ) {
							$synonym_tokens[ $syn_lower ] = 1;
						}
					}
				}
			}
			$all_tokens = array_merge( $all_tokens, $synonym_tokens );

			if ( empty( $all_tokens ) ) {
				continue;
			}

			// Total tokens for TF calculation.
			$total_tokens = array_sum( $all_tokens );

			// Insert terms and postings.
			foreach ( $all_tokens as $token => $count ) {
				$tf = $count / max( $total_tokens, 1 );

				// Get or create term.
				$term_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"SELECT id FROM {$terms_table} WHERE term = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$token
					)
				);

				if ( ! $term_id ) {
					$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$terms_table,
						array( 'term' => $token ),
						array( '%s' )
					);
					$term_id = $wpdb->insert_id;
				}

				if ( $term_id ) {
					$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$postings_table,
						array(
							'index_name' => $index_name,
							'term_id'    => $term_id,
							'doc_id'     => $doc_id,
							'tf'         => $tf,
						),
						array( '%s', '%d', '%d', '%f' )
					);
				}
			}

			++$indexed;
		}

		return array(
			'success' => true,
			'indexed' => $indexed,
		);
	}

	/**
	 * Delete a single document.
	 *
	 * @param string $index_name  Index name.
	 * @param string $document_id Document ID.
	 * @return bool
	 */
	public function delete_document( string $index_name, string $document_id ): bool {
		global $wpdb;

		$doc_id = (int) $document_id;
		$wpdb->delete( $wpdb->prefix . 'wss_index_postings', array( 'doc_id' => $doc_id, 'index_name' => $index_name ), array( '%d', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'wss_index_documents', array( 'doc_id' => $doc_id, 'index_name' => $index_name ), array( '%d', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$this->invalidate_cache( $index_name );

		return true;
	}

	/**
	 * Delete all documents from an index.
	 *
	 * @param string $index_name Index name.
	 * @return bool
	 */
	public function delete_all_documents( string $index_name ): bool {
		global $wpdb;

		$wpdb->delete( $wpdb->prefix . 'wss_index_postings', array( 'index_name' => $index_name ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'wss_index_documents', array( 'index_name' => $index_name ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$this->invalidate_cache( $index_name );

		return true;
	}

	/**
	 * Search documents using the inverted index with TF-IDF scoring.
	 *
	 * Results are cached in a dedicated MySQL table for ultra-fast repeated queries.
	 *
	 * @param string $index_name Index name.
	 * @param string $query      Search query.
	 * @param array  $options    Search options.
	 * @return array
	 */
	public function search( string $index_name, string $query, array $options = array() ): array {
		global $wpdb;

		$start_time = microtime( true );

		// Build a cache key from the query + options that affect results.
		$cache_key = $this->build_cache_key( $index_name, $query, $options );

		// Check in-memory cache first (same request).
		if ( isset( $this->memory_cache[ $cache_key ] ) ) {
			$cached = $this->memory_cache[ $cache_key ];
			$cached['processingTimeMs'] = 0;
			$cached['_cacheHit']        = true;
			return $cached;
		}

		// Check persistent cache (MySQL table).
		$cached = $this->get_cached_result( $cache_key );
		if ( null !== $cached ) {
			$this->memory_cache[ $cache_key ] = $cached;
			$elapsed = ( microtime( true ) - $start_time ) * 1000;
			$cached['processingTimeMs'] = (int) $elapsed;
			$cached['_cacheHit']        = true;
			return $cached;
		}

		$limit  = isset( $options['limit'] ) ? (int) $options['limit'] : 12;
		$offset = isset( $options['offset'] ) ? (int) $options['offset'] : 0;

		$docs_table     = $wpdb->prefix . 'wss_index_documents';
		$terms_table    = $wpdb->prefix . 'wss_index_terms';
		$postings_table = $wpdb->prefix . 'wss_index_postings';

		// Tokenize the query.
		$query_tokens = $this->tokenize( $query );

		// Expand query with synonyms.
		$expanded_tokens = $query_tokens;
		foreach ( $query_tokens as $token ) {
			if ( isset( $this->synonyms[ $token ] ) ) {
				foreach ( $this->synonyms[ $token ] as $syn ) {
					$expanded_tokens[] = mb_strtolower( $syn );
				}
			}
		}
		$expanded_tokens = array_unique( $expanded_tokens );

		if ( empty( $expanded_tokens ) ) {
			return $this->empty_result( $query );
		}

		// Get total documents for IDF calculation.
		$total_docs = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$docs_table} WHERE index_name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$index_name
			)
		);

		if ( 0 === $total_docs ) {
			return $this->empty_result( $query );
		}

		// Find matching term IDs — use LIKE for prefix matching (basic typo tolerance).
		$term_conditions = array();
		$term_params     = array();
		foreach ( $expanded_tokens as $token ) {
			$term_conditions[] = 't.term = %s';
			$term_params[]     = $token;
			// Prefix match for partial typing.
			if ( mb_strlen( $token ) >= 3 ) {
				$term_conditions[] = 't.term LIKE %s';
				$term_params[]     = $wpdb->esc_like( $token ) . '%';
			}
		}

		$term_where = implode( ' OR ', $term_conditions );

		// Calculate TF-IDF scores.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$sql = $wpdb->prepare(
			"SELECT p.doc_id,
				SUM( p.tf * LOG( ( %d + 1 ) / ( term_doc_count.cnt + 1 ) ) ) AS score
			FROM {$postings_table} p
			INNER JOIN {$terms_table} t ON t.id = p.term_id
			INNER JOIN (
				SELECT term_id, COUNT( DISTINCT doc_id ) AS cnt
				FROM {$postings_table}
				WHERE index_name = %s
				GROUP BY term_id
			) AS term_doc_count ON term_doc_count.term_id = p.term_id
			WHERE p.index_name = %s AND ( {$term_where} )
			GROUP BY p.doc_id
			ORDER BY score DESC",
			array_merge( array( $total_docs, $index_name, $index_name ), $term_params )
		);
		// phpcs:enable

		$all_scored = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $all_scored ) ) {
			return $this->empty_result( $query, $start_time );
		}

		// Apply filters if specified.
		$filter_str = isset( $options['filters'] ) ? $options['filters'] : '';
		$scored_ids = wp_list_pluck( $all_scored, 'doc_id' );

		if ( ! empty( $filter_str ) ) {
			$scored_ids = $this->apply_filters( $index_name, $scored_ids, $filter_str );

			// Re-map scores for filtered IDs.
			$score_map = array();
			foreach ( $all_scored as $row ) {
				$score_map[ $row->doc_id ] = $row->score;
			}
			$filtered_scored = array();
			foreach ( $scored_ids as $did ) {
				$filtered_scored[] = (object) array(
					'doc_id' => $did,
					'score'  => isset( $score_map[ $did ] ) ? $score_map[ $did ] : 0,
				);
			}
			usort( $filtered_scored, function ( $a, $b ) {
				return $b->score <=> $a->score;
			} );
			$all_scored = $filtered_scored;
			$scored_ids = wp_list_pluck( $all_scored, 'doc_id' );
		}

		$total_hits = count( $scored_ids );

		// Apply offset/limit.
		$page_ids = array_slice( $scored_ids, $offset, $limit );

		if ( empty( $page_ids ) ) {
			return $this->empty_result( $query, $start_time, $total_hits );
		}

		// Fetch documents.
		$placeholders = implode( ',', array_fill( 0, count( $page_ids ), '%d' ) );
		$docs = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT doc_id, doc_data FROM {$docs_table} WHERE index_name = %s AND doc_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $index_name ), $page_ids )
			)
		);

		// Build hits in score order.
		$doc_map = array();
		foreach ( $docs as $row ) {
			$doc_map[ $row->doc_id ] = json_decode( $row->doc_data, true );
		}

		$hits = array();
		$highlight_fields = isset( $options['highlight_fields'] ) ? $options['highlight_fields'] : array( 'name' );
		foreach ( $page_ids as $did ) {
			if ( isset( $doc_map[ $did ] ) ) {
				$hit = $doc_map[ $did ];

				// Add highlighting.
				$hit['_formatted'] = array();
				foreach ( $highlight_fields as $hf ) {
					if ( isset( $hit[ $hf ] ) && is_string( $hit[ $hf ] ) ) {
						$hit['_formatted'][ $hf ] = $this->highlight_text( $hit[ $hf ], $query_tokens );
					}
				}

				$hits[] = $hit;
			}
		}

		// Calculate facets if requested.
		$facet_distribution = array();
		if ( ! empty( $options['facets'] ) ) {
			$facet_distribution = $this->calculate_facets( $index_name, $scored_ids, (array) $options['facets'] );
		}

		$elapsed = ( microtime( true ) - $start_time ) * 1000;

		$result = array(
			'hits'               => $hits,
			'query'              => $query,
			'estimatedTotalHits' => $total_hits,
			'facetDistribution'  => $facet_distribution,
			'processingTimeMs'   => (int) $elapsed,
		);

		// Store in both caches.
		$this->memory_cache[ $cache_key ] = $result;
		$this->store_cached_result( $cache_key, $result );

		return $result;
	}

	/**
	 * Set synonyms.
	 *
	 * @param string $index_name Index name.
	 * @param array  $synonyms   Synonyms.
	 * @return bool
	 */
	public function set_synonyms( string $index_name, array $synonyms ): bool {
		$this->synonyms = $synonyms;
		update_option( 'wss_local_synonyms', $synonyms );
		return true;
	}

	/**
	 * Set stop words.
	 *
	 * @param string $index_name Index name.
	 * @param array  $stop_words Stop words.
	 * @return bool
	 */
	public function set_stop_words( string $index_name, array $stop_words ): bool {
		$this->stop_words = array_map( 'mb_strtolower', $stop_words );
		update_option( 'wss_local_stop_words', $this->stop_words );
		return true;
	}

	/**
	 * Get the base URL for the local search endpoint.
	 *
	 * @return string
	 */
	public function get_base_url(): string {
		return plugins_url( 'search-endpoint.php', dirname( __FILE__ ) . '/woo-smart-search.php' );
	}

	/**
	 * Get the local search endpoint URL.
	 *
	 * @return string
	 */
	public function get_search_endpoint_url(): string {
		return plugins_url( 'search-endpoint.php', dirname( __FILE__ ) . '/woo-smart-search.php' );
	}

	// ---- Internal helpers ----

	/**
	 * Tokenize a string into normalized terms.
	 *
	 * @param string $text Text to tokenize.
	 * @return array Array of lowercase tokens.
	 */
	private function tokenize( $text ): array {
		if ( is_array( $text ) ) {
			$parts = array();
			foreach ( $text as $item ) {
				if ( is_string( $item ) ) {
					$parts = array_merge( $parts, $this->tokenize( $item ) );
				}
			}
			return $parts;
		}

		if ( ! is_string( $text ) || '' === $text ) {
			return array();
		}

		// Lowercase.
		$text = mb_strtolower( $text );

		// Remove HTML tags.
		$text = wp_strip_all_tags( $text );

		// Replace non-alphanumeric with spaces (preserve hyphens inside words).
		$text = preg_replace( '/[^\p{L}\p{N}\-]/u', ' ', $text );

		// Split by whitespace.
		$tokens = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );

		// Remove stop words and very short tokens.
		$result = array();
		foreach ( $tokens as $token ) {
			$token = trim( $token, '-' );
			if ( mb_strlen( $token ) < 2 ) {
				continue;
			}
			if ( in_array( $token, $this->stop_words, true ) ) {
				continue;
			}
			$result[] = $token;
		}

		return $result;
	}

	/**
	 * Extract a field value from a document (supports nested dot notation).
	 *
	 * @param array  $doc   Document array.
	 * @param string $field Field name (e.g., 'name', 'attributes.Color').
	 * @return string|array
	 */
	private function extract_field_value( array $doc, string $field ) {
		if ( isset( $doc[ $field ] ) ) {
			return $doc[ $field ];
		}

		// Support dot notation (attributes.Color).
		$parts   = explode( '.', $field );
		$current = $doc;
		foreach ( $parts as $part ) {
			if ( is_array( $current ) && isset( $current[ $part ] ) ) {
				$current = $current[ $part ];
			} else {
				return '';
			}
		}

		return $current;
	}

	/**
	 * Apply Meilisearch-style filter string to a set of document IDs.
	 *
	 * Supports: field = "value", field != "value", field > N, field < N,
	 *           field >= N, field <= N, AND, OR.
	 *
	 * @param string $index_name Index name.
	 * @param array  $doc_ids    Document IDs to filter.
	 * @param string $filter_str Filter string.
	 * @return array Filtered document IDs.
	 */
	private function apply_filters( string $index_name, array $doc_ids, string $filter_str ): array {
		global $wpdb;

		if ( empty( $doc_ids ) || empty( $filter_str ) ) {
			return $doc_ids;
		}

		$docs_table   = $wpdb->prefix . 'wss_index_documents';
		$placeholders = implode( ',', array_fill( 0, count( $doc_ids ), '%d' ) );

		// Fetch document data for filtering.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT doc_id, doc_data FROM {$docs_table} WHERE index_name = %s AND doc_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $index_name ), $doc_ids )
			)
		);

		$result = array();
		foreach ( $rows as $row ) {
			$doc = json_decode( $row->doc_data, true );
			if ( $this->evaluate_filter( $doc, $filter_str ) ) {
				$result[] = (int) $row->doc_id;
			}
		}

		return $result;
	}

	/**
	 * Evaluate a filter expression against a document.
	 *
	 * @param array  $doc    Document data.
	 * @param string $filter Filter expression.
	 * @return bool
	 */
	private function evaluate_filter( array $doc, string $filter ): bool {
		// Split by AND (top level).
		$and_parts = preg_split( '/\s+AND\s+/i', $filter );

		foreach ( $and_parts as $and_part ) {
			$and_part = trim( $and_part );

			// Handle OR groups wrapped in parentheses.
			if ( preg_match( '/^\((.+)\)$/', $and_part, $m ) ) {
				$or_parts = preg_split( '/\s+OR\s+/i', $m[1] );
				$or_match = false;
				foreach ( $or_parts as $or_part ) {
					if ( $this->evaluate_single_condition( $doc, trim( $or_part ) ) ) {
						$or_match = true;
						break;
					}
				}
				if ( ! $or_match ) {
					return false;
				}
			} else {
				if ( ! $this->evaluate_single_condition( $doc, $and_part ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Evaluate a single filter condition (e.g., 'categories = "Electronics"').
	 *
	 * @param array  $doc       Document data.
	 * @param string $condition Single condition string.
	 * @return bool
	 */
	private function evaluate_single_condition( array $doc, string $condition ): bool {
		// Match: field operator "value" or field operator number.
		if ( ! preg_match( '/^(.+?)\s*(>=|<=|!=|=|>|<)\s*"?([^"]*)"?\s*$/', $condition, $m ) ) {
			return true; // Skip unparseable conditions.
		}

		$field    = trim( $m[1] );
		$operator = $m[2];
		$value    = $m[3];
		$doc_val  = $this->extract_field_value( $doc, $field );

		// Array field: check if any element matches.
		if ( is_array( $doc_val ) ) {
			switch ( $operator ) {
				case '=':
					return in_array( $value, $doc_val, false ); // Loose comparison for numeric strings.
				case '!=':
					return ! in_array( $value, $doc_val, false );
				default:
					return false;
			}
		}

		// Numeric comparisons.
		if ( in_array( $operator, array( '>=', '<=', '>', '<' ), true ) ) {
			$num_doc = (float) $doc_val;
			$num_val = (float) $value;
			switch ( $operator ) {
				case '>=': return $num_doc >= $num_val;
				case '<=': return $num_doc <= $num_val;
				case '>':  return $num_doc > $num_val;
				case '<':  return $num_doc < $num_val;
			}
		}

		// String comparison.
		switch ( $operator ) {
			case '=':  return (string) $doc_val === $value;
			case '!=': return (string) $doc_val !== $value;
		}

		return true;
	}

	/**
	 * Calculate facet distributions for a set of document IDs.
	 *
	 * @param string $index_name Index name.
	 * @param array  $doc_ids    Document IDs.
	 * @param array  $facets     Facet field names.
	 * @return array Facet distribution map.
	 */
	private function calculate_facets( string $index_name, array $doc_ids, array $facets ): array {
		global $wpdb;

		if ( empty( $doc_ids ) || empty( $facets ) ) {
			return array();
		}

		$docs_table   = $wpdb->prefix . 'wss_index_documents';
		$placeholders = implode( ',', array_fill( 0, count( $doc_ids ), '%d' ) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT doc_data FROM {$docs_table} WHERE index_name = %s AND doc_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $index_name ), $doc_ids )
			)
		);

		$distribution = array();
		foreach ( $facets as $facet ) {
			$distribution[ $facet ] = array();
		}

		foreach ( $rows as $row ) {
			$doc = json_decode( $row->doc_data, true );
			foreach ( $facets as $facet ) {
				$value = $this->extract_field_value( $doc, $facet );
				if ( is_array( $value ) ) {
					foreach ( $value as $v ) {
						$v = (string) $v;
						if ( '' === $v ) {
							continue;
						}
						if ( ! isset( $distribution[ $facet ][ $v ] ) ) {
							$distribution[ $facet ][ $v ] = 0;
						}
						++$distribution[ $facet ][ $v ];
					}
				} elseif ( '' !== (string) $value && null !== $value ) {
					$sv = (string) $value;
					if ( ! isset( $distribution[ $facet ][ $sv ] ) ) {
						$distribution[ $facet ][ $sv ] = 0;
					}
					++$distribution[ $facet ][ $sv ];
				}
			}
		}

		// Remove empty facets.
		foreach ( $distribution as $key => $values ) {
			if ( empty( $values ) ) {
				unset( $distribution[ $key ] );
			}
		}

		return $distribution;
	}

	/**
	 * Highlight query terms in text.
	 *
	 * @param string $text   Text to highlight.
	 * @param array  $tokens Query tokens.
	 * @return string
	 */
	private function highlight_text( string $text, array $tokens ): string {
		if ( empty( $tokens ) ) {
			return $text;
		}

		$patterns = array();
		foreach ( $tokens as $token ) {
			$patterns[] = preg_quote( $token, '/' );
		}
		$regex = '/(' . implode( '|', $patterns ) . ')/iu';

		return preg_replace( $regex, '<mark>$1</mark>', $text );
	}

	/**
	 * Return an empty result set.
	 *
	 * @param string     $query      The query.
	 * @param float|null $start_time Start time for timing.
	 * @param int        $total      Total count.
	 * @return array
	 */
	private function empty_result( string $query, $start_time = null, int $total = 0 ): array {
		$elapsed = $start_time ? ( microtime( true ) - $start_time ) * 1000 : 0;
		return array(
			'hits'               => array(),
			'query'              => $query,
			'estimatedTotalHits' => $total,
			'facetDistribution'  => array(),
			'processingTimeMs'   => (int) $elapsed,
		);
	}

	// ---- Cache helpers ----

	/**
	 * Build a deterministic cache key from query + options.
	 *
	 * @param string $index_name Index name.
	 * @param string $query      Search query.
	 * @param array  $options    Search options.
	 * @return string MD5 hash key.
	 */
	private function build_cache_key( string $index_name, string $query, array $options ): string {
		$key_parts = array(
			'idx'     => $index_name,
			'q'       => mb_strtolower( trim( $query ) ),
			'limit'   => $options['limit'] ?? 12,
			'offset'  => $options['offset'] ?? 0,
			'filters' => $options['filters'] ?? '',
			'sort'    => $options['sort'] ?? '',
			'facets'  => $options['facets'] ?? array(),
		);
		return md5( wp_json_encode( $key_parts ) );
	}

	/**
	 * Get a cached search result from the persistent cache table.
	 *
	 * @param string $cache_key Cache key.
	 * @return array|null Cached result or null if not found/expired.
	 */
	private function get_cached_result( string $cache_key ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wss_search_cache';

		// Check if table exists (avoid errors before activation).
		if ( ! $this->cache_table_exists() ) {
			return null;
		}

		$ttl = (int) apply_filters( 'wss_search_cache_ttl', self::CACHE_TTL );

		$row = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT result_data FROM {$table} WHERE cache_key = %s AND created_at >= DATE_SUB( NOW(), INTERVAL %d SECOND ) LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cache_key,
				$ttl
			)
		);

		if ( null === $row ) {
			return null;
		}

		$result = json_decode( $row, true );
		return is_array( $result ) ? $result : null;
	}

	/**
	 * Store a search result in the persistent cache.
	 *
	 * @param string $cache_key Cache key.
	 * @param array  $result    Search result to cache.
	 */
	private function store_cached_result( string $cache_key, array $result ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wss_search_cache';

		if ( ! $this->cache_table_exists() ) {
			return;
		}

		// Remove timing data from cached result (will be recalculated on read).
		$to_cache = $result;
		unset( $to_cache['processingTimeMs'], $to_cache['_cacheHit'] );

		// Use REPLACE to upsert (handles duplicate keys).
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"REPLACE INTO {$table} (cache_key, result_data, created_at) VALUES (%s, %s, NOW())", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cache_key,
				wp_json_encode( $to_cache, JSON_UNESCAPED_UNICODE )
			)
		);
	}

	/**
	 * Invalidate all cached results for an index.
	 *
	 * Called when documents are indexed, updated, or deleted.
	 *
	 * @param string $index_name Index name (unused, clears all for simplicity).
	 */
	public function invalidate_cache( string $index_name = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wss_search_cache';

		if ( ! $this->cache_table_exists() ) {
			return;
		}

		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->memory_cache = array();
	}

	/**
	 * Purge expired entries from the cache table.
	 *
	 * Can be called periodically to keep the table small.
	 */
	public function purge_expired_cache() {
		global $wpdb;

		$table = $wpdb->prefix . 'wss_search_cache';

		if ( ! $this->cache_table_exists() ) {
			return;
		}

		$ttl = (int) apply_filters( 'wss_search_cache_ttl', self::CACHE_TTL );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < DATE_SUB( NOW(), INTERVAL %d SECOND )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$ttl
			)
		);
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array
	 */
	public function get_cache_stats(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wss_search_cache';

		if ( ! $this->cache_table_exists() ) {
			return array( 'entries' => 0, 'size' => '0 KB' );
		}

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$size  = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ROUND( SUM( LENGTH( result_data ) ) / 1024, 1 ) FROM {$table} WHERE 1 = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				1
			)
		);

		return array(
			'entries' => $count,
			'size'    => $size . ' KB',
		);
	}

	/**
	 * Check if the cache table exists.
	 *
	 * @return bool
	 */
	private function cache_table_exists(): bool {
		global $wpdb;
		static $exists = null;

		if ( null === $exists ) {
			$table  = $wpdb->prefix . 'wss_search_cache';
			$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		return $exists;
	}

	/**
	 * Create the local index database tables.
	 *
	 * Called during plugin activation and when creating an index.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Use direct CREATE TABLE IF NOT EXISTS — more reliable than dbDelta
		// in contexts where wp-admin/includes/upgrade.php may not be available.
		$tables = array(
			"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wss_index_documents (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				index_name varchar(100) NOT NULL DEFAULT 'woo_products',
				doc_id bigint(20) NOT NULL,
				doc_data longtext NOT NULL,
				indexed_at datetime NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY idx_index_doc (index_name, doc_id),
				KEY idx_index_name (index_name)
			) {$charset_collate}",
			"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wss_index_terms (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				term varchar(191) NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY idx_term (term)
			) {$charset_collate}",
			"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wss_index_postings (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				index_name varchar(100) NOT NULL DEFAULT 'woo_products',
				term_id bigint(20) NOT NULL,
				doc_id bigint(20) NOT NULL,
				tf float NOT NULL DEFAULT 0,
				PRIMARY KEY (id),
				KEY idx_term_index (term_id, index_name),
				KEY idx_doc_index (doc_id, index_name),
				KEY idx_index_name (index_name)
			) {$charset_collate}",
			"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wss_search_cache (
				cache_key char(32) NOT NULL,
				result_data longtext NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY (cache_key),
				KEY idx_created_at (created_at)
			) {$charset_collate}",
		);

		foreach ( $tables as $sql ) {
			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		}
	}
}
