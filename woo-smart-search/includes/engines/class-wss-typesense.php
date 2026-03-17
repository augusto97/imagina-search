<?php
/**
 * Typesense adapter.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Typesense
 *
 * Implements WSS_Search_Engine for Typesense.
 * Uses wp_remote_* functions for HTTP communication.
 */
class WSS_Typesense implements WSS_Search_Engine {

	/**
	 * Base URL.
	 *
	 * @var string
	 */
	private $base_url = '';

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key = '';

	/**
	 * Whether the engine is connected.
	 *
	 * @var bool
	 */
	private $connected = false;

	/**
	 * Connect to Typesense.
	 *
	 * @param array $config Configuration.
	 * @return bool
	 */
	public function connect( array $config ): bool {
		$protocol = isset( $config['protocol'] ) ? $config['protocol'] : 'http';
		$host     = isset( $config['host'] ) ? rtrim( $config['host'], '/' ) : 'localhost';
		$port     = isset( $config['port'] ) ? $config['port'] : '8108';

		$host = preg_replace( '#^https?://#', '', $host );

		$this->base_url  = $protocol . '://' . $host . ':' . $port;
		$this->api_key   = isset( $config['api_key'] ) ? $config['api_key'] : '';
		$this->connected = true;

		return true;
	}

	/**
	 * Test the connection.
	 *
	 * @return array
	 */
	public function test_connection(): array {
		$response = $this->request( 'GET', '/health' );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
				'version' => '',
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Typesense returned HTTP %d', 'woo-smart-search' ),
					$code
				),
				'version' => '',
			);
		}

		// Get version info from debug endpoint.
		$debug_response = $this->request( 'GET', '/debug' );
		$version        = '';
		if ( ! is_wp_error( $debug_response ) ) {
			$debug_body = json_decode( wp_remote_retrieve_body( $debug_response ), true );
			$version    = isset( $debug_body['version'] ) ? $debug_body['version'] : '';
		}

		return array(
			'success' => true,
			'message' => __( 'Connected successfully to Typesense', 'woo-smart-search' ),
			'version' => $version,
		);
	}

	/**
	 * Create an index (collection in Typesense).
	 *
	 * @param string $index_name Index name.
	 * @param array  $settings   Index settings.
	 * @return bool
	 */
	public function create_index( string $index_name, array $settings = array() ): bool {
		$schema = $this->get_collection_schema( $index_name );
		$schema = array_merge( $schema, $settings );

		$response = $this->request( 'POST', '/collections', $schema );

		if ( is_wp_error( $response ) ) {
			wss_log( 'Failed to create Typesense collection: ' . $response->get_error_message(), 'error' );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// 409 means collection already exists.
		if ( 409 === $code ) {
			return true;
		}

		return in_array( $code, array( 200, 201 ), true );
	}

	/**
	 * Delete an index.
	 *
	 * @param string $index_name Index name.
	 * @return bool
	 */
	public function delete_index( string $index_name ): bool {
		$response = $this->request( 'DELETE', '/collections/' . $index_name );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return in_array( wp_remote_retrieve_response_code( $response ), array( 200, 204 ), true );
	}

	/**
	 * Get index statistics.
	 *
	 * @param string $index_name Index name.
	 * @return array
	 */
	public function get_index_stats( string $index_name ): array {
		$response = $this->request( 'GET', '/collections/' . $index_name );

		if ( is_wp_error( $response ) ) {
			return array(
				'numberOfDocuments' => 0,
				'isIndexing'        => false,
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return array(
			'numberOfDocuments' => isset( $body['num_documents'] ) ? (int) $body['num_documents'] : 0,
			'isIndexing'        => false,
		);
	}

	/**
	 * Configure index. In Typesense, some settings are set at collection creation.
	 *
	 * @param string $index_name Index name.
	 * @param array  $settings   Settings.
	 * @return bool
	 */
	public function configure_index( string $index_name, array $settings ): bool {
		// Typesense configures at collection creation.
		// For updates, we may need to recreate.
		return true;
	}

	/**
	 * Index documents.
	 *
	 * @param string $index_name Index name.
	 * @param array  $documents  Documents.
	 * @return array
	 */
	public function index_documents( string $index_name, array $documents ): array {
		$jsonl = '';
		foreach ( $documents as $doc ) {
			$jsonl .= wp_json_encode( $doc ) . "\n";
		}

		$response = $this->request(
			'POST',
			'/collections/' . $index_name . '/documents/import?action=upsert',
			null,
			$jsonl,
			'text/plain'
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Documents indexed successfully', 'woo-smart-search' ),
		);
	}

	/**
	 * Update documents.
	 *
	 * @param string $index_name Index name.
	 * @param array  $documents  Documents.
	 * @return array
	 */
	public function update_documents( string $index_name, array $documents ): array {
		return $this->index_documents( $index_name, $documents );
	}

	/**
	 * Delete a document.
	 *
	 * @param string $index_name  Index name.
	 * @param string $document_id Document ID.
	 * @return bool
	 */
	public function delete_document( string $index_name, string $document_id ): bool {
		$response = $this->request( 'DELETE', '/collections/' . $index_name . '/documents/' . $document_id );
		return ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 200, 204 ), true );
	}

	/**
	 * Delete all documents.
	 *
	 * @param string $index_name Index name.
	 * @return bool
	 */
	public function delete_all_documents( string $index_name ): bool {
		// In Typesense, delete collection and recreate.
		if ( $this->delete_index( $index_name ) ) {
			return $this->create_index( $index_name );
		}
		return false;
	}

	/**
	 * Search documents.
	 *
	 * @param string $index_name Index name.
	 * @param string $query      Search query.
	 * @param array  $options    Search options.
	 * @return array
	 */
	public function search( string $index_name, string $query, array $options = array() ): array {
		$params = array(
			'q'        => $query,
			'query_by' => 'name,sku,categories,tags,brand,description,attributes_text,variations_text',
		);

		if ( isset( $options['limit'] ) ) {
			$params['per_page'] = (int) $options['limit'];
		}

		if ( isset( $options['offset'] ) && isset( $options['limit'] ) ) {
			$params['page'] = (int) floor( $options['offset'] / $options['limit'] ) + 1;
		}

		if ( isset( $options['filters'] ) && ! empty( $options['filters'] ) ) {
			$params['filter_by'] = $options['filters'];
		}

		if ( isset( $options['facets'] ) && ! empty( $options['facets'] ) ) {
			$params['facet_by'] = implode( ',', $options['facets'] );
		}

		if ( isset( $options['sort'] ) && ! empty( $options['sort'] ) ) {
			$sort = is_array( $options['sort'] ) ? $options['sort'][0] : $options['sort'];
			$params['sort_by'] = str_replace( array( ':asc', ':desc' ), array( ':asc', ':desc' ), $sort );
		}

		if ( isset( $options['highlight_fields'] ) && ! empty( $options['highlight_fields'] ) ) {
			$params['highlight_fields']      = implode( ',', $options['highlight_fields'] );
			$params['highlight_start_tag']   = '<mark>';
			$params['highlight_end_tag']     = '</mark>';
		}

		$query_string = http_build_query( $params );
		$response     = $this->request( 'GET', '/collections/' . $index_name . '/documents/search?' . $query_string );

		if ( is_wp_error( $response ) ) {
			return array(
				'hits'               => array(),
				'query'              => $query,
				'estimatedTotalHits' => 0,
				'error'              => $response->get_error_message(),
			);
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		$hits   = array();

		if ( isset( $result['hits'] ) ) {
			foreach ( $result['hits'] as $hit ) {
				$doc = isset( $hit['document'] ) ? $hit['document'] : $hit;
				if ( isset( $hit['highlights'] ) || isset( $hit['highlight'] ) ) {
					$highlight    = isset( $hit['highlight'] ) ? $hit['highlight'] : array();
					$doc['_formatted'] = array();
					foreach ( $highlight as $field => $hl ) {
						$doc['_formatted'][ $field ] = isset( $hl['snippet'] ) ? $hl['snippet'] : ( isset( $hl['value'] ) ? $hl['value'] : '' );
					}
				}
				$hits[] = $doc;
			}
		}

		$facet_distribution = array();
		if ( isset( $result['facet_counts'] ) ) {
			foreach ( $result['facet_counts'] as $facet ) {
				$field_name = $facet['field_name'];
				$facet_distribution[ $field_name ] = array();
				foreach ( $facet['counts'] as $count ) {
					$facet_distribution[ $field_name ][ $count['value'] ] = $count['count'];
				}
			}
		}

		return array(
			'hits'               => $hits,
			'query'              => $query,
			'estimatedTotalHits' => isset( $result['found'] ) ? (int) $result['found'] : 0,
			'facetDistribution'  => $facet_distribution,
			'processingTimeMs'   => isset( $result['search_time_ms'] ) ? (int) $result['search_time_ms'] : 0,
		);
	}

	/**
	 * Set searchable attributes. Configured at collection level in Typesense.
	 *
	 * @param string $index_name Index name.
	 * @param array  $attributes Attributes.
	 * @return bool
	 */
	public function set_searchable_attributes( string $index_name, array $attributes ): bool {
		return true;
	}

	/**
	 * Set filterable attributes.
	 *
	 * @param string $index_name Index name.
	 * @param array  $attributes Attributes.
	 * @return bool
	 */
	public function set_filterable_attributes( string $index_name, array $attributes ): bool {
		return true;
	}

	/**
	 * Set sortable attributes.
	 *
	 * @param string $index_name Index name.
	 * @param array  $attributes Attributes.
	 * @return bool
	 */
	public function set_sortable_attributes( string $index_name, array $attributes ): bool {
		return true;
	}

	/**
	 * Set synonyms.
	 *
	 * @param string $index_name Index name.
	 * @param array  $synonyms   Synonyms.
	 * @return bool
	 */
	public function set_synonyms( string $index_name, array $synonyms ): bool {
		$i = 0;
		foreach ( $synonyms as $key => $values ) {
			$body = array(
				'synonyms' => array_merge( array( $key ), $values ),
			);
			$this->request( 'PUT', '/collections/' . $index_name . '/synonyms/synonym-' . $i, $body );
			$i++;
		}
		return true;
	}

	/**
	 * Set stop words. Typesense doesn't support stop words natively in the same way.
	 *
	 * @param string $index_name Index name.
	 * @param array  $stop_words Stop words.
	 * @return bool
	 */
	public function set_stop_words( string $index_name, array $stop_words ): bool {
		return true;
	}

	/**
	 * Get the collection schema for Typesense.
	 *
	 * @param string $name Collection name.
	 * @return array
	 */
	private function get_collection_schema( string $name ): array {
		return array(
			'name'                  => $name,
			'enable_nested_fields'  => true,
			'fields'                => array(
				array( 'name' => 'name', 'type' => 'string' ),
				array( 'name' => 'slug', 'type' => 'string', 'index' => false ),
				array( 'name' => 'description', 'type' => 'string' ),
				array( 'name' => 'full_description', 'type' => 'string', 'index' => false, 'optional' => true ),
				array( 'name' => 'sku', 'type' => 'string', 'optional' => true ),
				array( 'name' => 'permalink', 'type' => 'string', 'index' => false ),
				array( 'name' => 'image', 'type' => 'string', 'index' => false, 'optional' => true ),
				array( 'name' => 'price', 'type' => 'float', 'facet' => true ),
				array( 'name' => 'regular_price', 'type' => 'float', 'optional' => true ),
				array( 'name' => 'sale_price', 'type' => 'float', 'optional' => true ),
				array( 'name' => 'on_sale', 'type' => 'bool', 'facet' => true ),
				array( 'name' => 'stock_status', 'type' => 'string', 'facet' => true ),
				array( 'name' => 'categories', 'type' => 'string[]', 'facet' => true ),
				array( 'name' => 'category_ids', 'type' => 'int32[]', 'facet' => true, 'optional' => true ),
				array( 'name' => 'tags', 'type' => 'string[]', 'facet' => true, 'optional' => true ),
				array( 'name' => 'attributes_text', 'type' => 'string', 'optional' => true ),
				array( 'name' => 'brand', 'type' => 'string', 'facet' => true, 'optional' => true ),
				array( 'name' => 'rating', 'type' => 'float', 'facet' => true, 'optional' => true ),
				array( 'name' => 'review_count', 'type' => 'int32', 'optional' => true ),
				array( 'name' => 'type', 'type' => 'string', 'facet' => true, 'optional' => true ),
				array( 'name' => 'featured', 'type' => 'bool', 'facet' => true, 'optional' => true ),
				array( 'name' => 'date_created', 'type' => 'int64', 'optional' => true ),
				array( 'name' => 'date_modified', 'type' => 'int64', 'optional' => true ),
				array( 'name' => 'total_sales', 'type' => 'int32', 'optional' => true ),
				array( 'name' => 'menu_order', 'type' => 'int32', 'optional' => true ),
				array( 'name' => 'variations_text', 'type' => 'string', 'optional' => true ),
				array( 'name' => 'price_min', 'type' => 'float', 'optional' => true ),
				array( 'name' => 'price_max', 'type' => 'float', 'optional' => true ),
				array( 'name' => 'currency', 'type' => 'string', 'index' => false, 'optional' => true ),
			),
			'default_sorting_field' => 'total_sales',
		);
	}

	/**
	 * Make an HTTP request to Typesense.
	 *
	 * @param string      $method       HTTP method.
	 * @param string      $path         API path.
	 * @param array|null  $body         Request body (JSON).
	 * @param string|null $raw_body     Raw body string.
	 * @param string      $content_type Content type.
	 * @return array|WP_Error
	 */
	private function request( string $method, string $path, $body = null, $raw_body = null, $content_type = 'application/json' ) {
		$args = array(
			'method'  => $method,
			'headers' => array(
				'Content-Type'         => $content_type,
				'X-TYPESENSE-API-KEY'  => $this->api_key,
			),
			'timeout' => 30,
		);

		if ( null !== $raw_body ) {
			$args['body'] = $raw_body;
		} elseif ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		return wp_remote_request( $this->base_url . $path, $args );
	}
}
