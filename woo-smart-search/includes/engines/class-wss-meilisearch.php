<?php
/**
 * Meilisearch adapter.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Meilisearch
 *
 * Implements WSS_Search_Engine for Meilisearch.
 * Uses wp_remote_* functions for HTTP communication (no SDK dependency).
 */
class WSS_Meilisearch implements WSS_Search_Engine {

	/**
	 * Base URL of the Meilisearch server.
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
	 * Connect to Meilisearch.
	 *
	 * @param array $config Configuration array.
	 * @return bool
	 */
	public function connect( array $config ): bool {
		$protocol = isset( $config['protocol'] ) ? $config['protocol'] : 'http';
		$host     = isset( $config['host'] ) ? rtrim( $config['host'], '/' ) : 'localhost';
		$port     = isset( $config['port'] ) ? $config['port'] : '7700';

		// Remove protocol from host if already included.
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
		$response = $this->request( 'GET', '/version' );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
				'version' => '',
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Meilisearch returned HTTP %d', 'woo-smart-search' ),
					$code
				),
				'version' => '',
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Connected successfully to Meilisearch', 'woo-smart-search' ),
			'version' => isset( $body['pkgVersion'] ) ? $body['pkgVersion'] : '',
		);
	}

	/**
	 * Create an index.
	 *
	 * @param string $index_name Index name.
	 * @param array  $settings   Index settings.
	 * @return bool
	 */
	public function create_index( string $index_name, array $settings = array() ): bool {
		$body = array(
			'uid'        => $index_name,
			'primaryKey' => 'id',
		);

		$response = $this->request( 'POST', '/indexes', $body );

		if ( is_wp_error( $response ) ) {
			wss_log( 'Failed to create index: ' . $response->get_error_message(), 'error' );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return in_array( $code, array( 200, 201, 202 ), true );
	}

	/**
	 * Delete an index.
	 *
	 * @param string $index_name Index name.
	 * @return bool
	 */
	public function delete_index( string $index_name ): bool {
		$response = $this->request( 'DELETE', '/indexes/' . $index_name );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return in_array( $code, array( 200, 202, 204 ), true );
	}

	/**
	 * Get index statistics.
	 *
	 * @param string $index_name Index name.
	 * @return array
	 */
	public function get_index_stats( string $index_name ): array {
		$response = $this->request( 'GET', '/indexes/' . $index_name . '/stats' );

		if ( is_wp_error( $response ) ) {
			return array(
				'numberOfDocuments' => 0,
				'isIndexing'        => false,
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return array(
			'numberOfDocuments' => isset( $body['numberOfDocuments'] ) ? (int) $body['numberOfDocuments'] : 0,
			'isIndexing'        => isset( $body['isIndexing'] ) ? (bool) $body['isIndexing'] : false,
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
		$response = $this->request( 'PATCH', '/indexes/' . $index_name . '/settings', $settings );

		if ( is_wp_error( $response ) ) {
			wss_log( 'Failed to configure index: ' . $response->get_error_message(), 'error' );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return in_array( $code, array( 200, 202 ), true );
	}

	/**
	 * Index documents.
	 *
	 * @param string $index_name Index name.
	 * @param array  $documents  Documents array.
	 * @return array
	 */
	public function index_documents( string $index_name, array $documents ): array {
		$response = $this->request( 'POST', '/indexes/' . $index_name . '/documents', $documents );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return array(
			'success' => true,
			'taskUid' => isset( $body['taskUid'] ) ? $body['taskUid'] : null,
		);
	}

	/**
	 * Update documents.
	 *
	 * @param string $index_name Index name.
	 * @param array  $documents  Documents array.
	 * @return array
	 */
	public function update_documents( string $index_name, array $documents ): array {
		$response = $this->request( 'PUT', '/indexes/' . $index_name . '/documents', $documents );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return array(
			'success' => true,
			'taskUid' => isset( $body['taskUid'] ) ? $body['taskUid'] : null,
		);
	}

	/**
	 * Delete a document.
	 *
	 * @param string $index_name  Index name.
	 * @param string $document_id Document ID.
	 * @return bool
	 */
	public function delete_document( string $index_name, string $document_id ): bool {
		$response = $this->request( 'DELETE', '/indexes/' . $index_name . '/documents/' . $document_id );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return in_array( $code, array( 200, 202 ), true );
	}

	/**
	 * Delete all documents.
	 *
	 * @param string $index_name Index name.
	 * @return bool
	 */
	public function delete_all_documents( string $index_name ): bool {
		$response = $this->request( 'DELETE', '/indexes/' . $index_name . '/documents' );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return in_array( $code, array( 200, 202 ), true );
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
		$body = array( 'q' => $query );

		if ( isset( $options['limit'] ) ) {
			$body['limit'] = (int) $options['limit'];
		}

		if ( isset( $options['offset'] ) ) {
			$body['offset'] = (int) $options['offset'];
		}

		if ( isset( $options['filters'] ) && ! empty( $options['filters'] ) ) {
			$body['filter'] = $options['filters'];
		}

		if ( isset( $options['facets'] ) && ! empty( $options['facets'] ) ) {
			$body['facets'] = $options['facets'];
		}

		if ( isset( $options['sort'] ) && ! empty( $options['sort'] ) ) {
			$body['sort'] = (array) $options['sort'];
		}

		if ( isset( $options['highlight_fields'] ) && ! empty( $options['highlight_fields'] ) ) {
			$body['attributesToHighlight'] = $options['highlight_fields'];
			$body['highlightPreTag']       = '<mark>';
			$body['highlightPostTag']      = '</mark>';
		}

		$response = $this->request( 'POST', '/indexes/' . $index_name . '/search', $body );

		if ( is_wp_error( $response ) ) {
			return array(
				'hits'             => array(),
				'query'            => $query,
				'estimatedTotalHits' => 0,
				'error'            => $response->get_error_message(),
			);
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		return array(
			'hits'             => isset( $result['hits'] ) ? $result['hits'] : array(),
			'query'            => $query,
			'estimatedTotalHits' => isset( $result['estimatedTotalHits'] ) ? (int) $result['estimatedTotalHits'] : 0,
			'facetDistribution'  => isset( $result['facetDistribution'] ) ? $result['facetDistribution'] : array(),
			'processingTimeMs'   => isset( $result['processingTimeMs'] ) ? (int) $result['processingTimeMs'] : 0,
		);
	}

	/**
	 * Set searchable attributes.
	 *
	 * @param string $index_name Index name.
	 * @param array  $attributes Attributes.
	 * @return bool
	 */
	public function set_searchable_attributes( string $index_name, array $attributes ): bool {
		$response = $this->request( 'PUT', '/indexes/' . $index_name . '/settings/searchable-attributes', $attributes );
		return ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 200, 202 ), true );
	}

	/**
	 * Set filterable attributes.
	 *
	 * @param string $index_name Index name.
	 * @param array  $attributes Attributes.
	 * @return bool
	 */
	public function set_filterable_attributes( string $index_name, array $attributes ): bool {
		$response = $this->request( 'PUT', '/indexes/' . $index_name . '/settings/filterable-attributes', $attributes );
		return ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 200, 202 ), true );
	}

	/**
	 * Set sortable attributes.
	 *
	 * @param string $index_name Index name.
	 * @param array  $attributes Attributes.
	 * @return bool
	 */
	public function set_sortable_attributes( string $index_name, array $attributes ): bool {
		$response = $this->request( 'PUT', '/indexes/' . $index_name . '/settings/sortable-attributes', $attributes );
		return ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 200, 202 ), true );
	}

	/**
	 * Set synonyms.
	 *
	 * @param string $index_name Index name.
	 * @param array  $synonyms   Synonyms.
	 * @return bool
	 */
	public function set_synonyms( string $index_name, array $synonyms ): bool {
		$response = $this->request( 'PUT', '/indexes/' . $index_name . '/settings/synonyms', $synonyms );
		return ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 200, 202 ), true );
	}

	/**
	 * Set stop words.
	 *
	 * @param string $index_name Index name.
	 * @param array  $stop_words Stop words.
	 * @return bool
	 */
	public function set_stop_words( string $index_name, array $stop_words ): bool {
		$response = $this->request( 'PUT', '/indexes/' . $index_name . '/settings/stop-words', $stop_words );
		return ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 200, 202 ), true );
	}

	/**
	 * Make an HTTP request to Meilisearch.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $path   API path.
	 * @param array|null $body   Request body.
	 * @return array|WP_Error
	 */
	private function request( string $method, string $path, $body = null ) {
		$args = array(
			'method'  => $method,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			),
			'timeout' => 30,
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		return wp_remote_request( $this->base_url . $path, $args );
	}
}
