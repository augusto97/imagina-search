<?php
/**
 * Meilisearch connection class (singleton).
 *
 * Direct connection to Meilisearch without abstraction layers.
 * Uses wp_remote_* for HTTP communication.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Meilisearch
 */
class WSS_Meilisearch {

	/**
	 * Singleton instance.
	 *
	 * @var WSS_Meilisearch|null
	 */
	private static $instance = null;

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
	 * Get the singleton instance, auto-configured from plugin settings.
	 *
	 * @return WSS_Meilisearch|null Returns null if API key is not configured.
	 */
	public static function get_instance() {
		if ( null !== self::$instance ) {
			return self::$instance;
		}

		$api_key = wss_get_option( 'api_key', '' );
		if ( empty( $api_key ) ) {
			return null;
		}

		self::$instance = new self();
		self::$instance->connect( array(
			'host'     => wss_get_option( 'host', 'localhost' ),
			'port'     => wss_get_option( 'port', '' ),
			'protocol' => wss_get_option( 'protocol', 'http' ),
			'api_key'  => self::decrypt_key( $api_key ),
		) );

		return self::$instance;
	}

	/**
	 * Create a new instance with custom config (for testing connection).
	 *
	 * @param array $config Connection config.
	 * @return WSS_Meilisearch
	 */
	public static function create( array $config ) {
		$instance = new self();
		$instance->connect( $config );
		return $instance;
	}

	/**
	 * Reset the singleton (for config changes).
	 */
	public static function reset() {
		self::$instance = null;
	}

	/**
	 * Connect to Meilisearch.
	 *
	 * @param array $config Configuration array.
	 * @return bool
	 */
	public function connect( array $config ): bool {
		$protocol = isset( $config['protocol'] ) ? $config['protocol'] : 'http';
		$host     = isset( $config['host'] ) ? rtrim( $config['host'], '/' ) : 'localhost';
		$port     = isset( $config['port'] ) && '' !== $config['port'] ? $config['port'] : '';

		$host = preg_replace( '#^https?://#', '', $host );

		$this->base_url = $protocol . '://' . $host;
		if ( $port ) {
			$this->base_url .= ':' . $port;
		}
		$this->api_key   = isset( $config['api_key'] ) ? $config['api_key'] : '';
		$this->connected = true;

		return true;
	}

	/**
	 * Test the connection.
	 *
	 * Uses /health (no auth required) to verify connectivity, then
	 * tries /version (requires admin key) for version info.
	 *
	 * @return array { success: bool, message: string, version: string }
	 */
	public function test_connection(): array {
		// Step 1: Check /health — no auth required, works with any key.
		$health = $this->request( 'GET', '/health' );

		if ( is_wp_error( $health ) ) {
			return array(
				'success' => false,
				'message' => $health->get_error_message(),
				'version' => '',
			);
		}

		$health_code = wp_remote_retrieve_response_code( $health );
		if ( 200 !== $health_code ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Meilisearch returned HTTP %d', 'woo-smart-search' ),
					$health_code
				),
				'version' => '',
			);
		}

		// Step 2: Try /version for version info (may fail with restricted keys).
		$version_str = '';
		$response    = $this->request( 'GET', '/version' );
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$body        = json_decode( wp_remote_retrieve_body( $response ), true );
			$version_str = isset( $body['pkgVersion'] ) ? $body['pkgVersion'] : '';
		}

		return array(
			'success' => true,
			'message' => __( 'Connected successfully to Meilisearch', 'woo-smart-search' ),
			'version' => $version_str,
		);
	}

	/**
	 * Create an index.
	 *
	 * @param string $index_name Index name.
	 * @return bool
	 */
	public function create_index( string $index_name ): bool {
		$body     = array(
			'uid'        => $index_name,
			'primaryKey' => 'id',
		);
		$response = $this->request( 'POST', '/indexes', $body );
		if ( is_wp_error( $response ) ) {
			wss_log( 'Failed to create index: ' . $response->get_error_message(), 'error' );
			return false;
		}
		return in_array( wp_remote_retrieve_response_code( $response ), array( 200, 201, 202 ), true );
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
		return in_array( wp_remote_retrieve_response_code( $response ), array( 200, 202, 204 ), true );
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
			return array( 'numberOfDocuments' => 0, 'isIndexing' => false );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return array(
			'numberOfDocuments' => isset( $body['numberOfDocuments'] ) ? (int) $body['numberOfDocuments'] : 0,
			'isIndexing'        => isset( $body['isIndexing'] ) ? (bool) $body['isIndexing'] : false,
		);
	}

	/**
	 * Configure index settings (searchable, filterable, sortable attributes).
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
		return in_array( wp_remote_retrieve_response_code( $response ), array( 200, 202 ), true );
	}

	/**
	 * Add or replace documents in the index.
	 *
	 * @param string $index_name Index name.
	 * @param array  $documents  Documents array.
	 * @return array
	 */
	public function index_documents( string $index_name, array $documents ): array {
		$response = $this->request( 'POST', '/indexes/' . $index_name . '/documents', $documents );
		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return array( 'success' => true, 'taskUid' => isset( $body['taskUid'] ) ? $body['taskUid'] : null );
	}

	/**
	 * Delete a single document.
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
		return in_array( wp_remote_retrieve_response_code( $response ), array( 200, 202 ), true );
	}

	/**
	 * Delete all documents from an index.
	 *
	 * @param string $index_name Index name.
	 * @return bool
	 */
	public function delete_all_documents( string $index_name ): bool {
		$response = $this->request( 'DELETE', '/indexes/' . $index_name . '/documents' );
		if ( is_wp_error( $response ) ) {
			return false;
		}
		return in_array( wp_remote_retrieve_response_code( $response ), array( 200, 202 ), true );
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
		if ( ! empty( $options['filters'] ) ) {
			$body['filter'] = $options['filters'];
		}
		if ( ! empty( $options['facets'] ) ) {
			$body['facets'] = $options['facets'];
		}
		if ( ! empty( $options['sort'] ) ) {
			$body['sort'] = (array) $options['sort'];
		}
		if ( ! empty( $options['highlight_fields'] ) ) {
			$body['attributesToHighlight'] = $options['highlight_fields'];
			$body['highlightPreTag']       = '<mark>';
			$body['highlightPostTag']      = '</mark>';
		}

		$response = $this->request( 'POST', '/indexes/' . $index_name . '/search', $body );

		if ( is_wp_error( $response ) ) {
			return array(
				'hits'               => array(),
				'query'              => $query,
				'estimatedTotalHits' => 0,
				'error'              => $response->get_error_message(),
			);
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		return array(
			'hits'               => isset( $result['hits'] ) ? $result['hits'] : array(),
			'query'              => $query,
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
	 * Encrypt an API key for storage.
	 *
	 * @param string $key The plain API key.
	 * @return string
	 */
	public static function encrypt_key( string $key ): string {
		if ( empty( $key ) ) {
			return '';
		}
		$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'wss-default-salt';
		$iv   = substr( hash( 'sha256', $salt ), 0, 16 );
		if ( function_exists( 'openssl_encrypt' ) ) {
			$encrypted = openssl_encrypt( $key, 'AES-256-CBC', $salt, 0, $iv );
			return base64_encode( $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		return base64_encode( $key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt an API key from storage.
	 *
	 * @param string $encrypted The encrypted API key.
	 * @return string
	 */
	public static function decrypt_key( string $encrypted ): string {
		if ( empty( $encrypted ) ) {
			return '';
		}
		$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'wss-default-salt';
		$iv   = substr( hash( 'sha256', $salt ), 0, 16 );
		if ( function_exists( 'openssl_encrypt' ) ) {
			$decoded   = base64_decode( $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$decrypted = openssl_decrypt( $decoded, 'AES-256-CBC', $salt, 0, $iv );
			return false !== $decrypted ? $decrypted : $encrypted;
		}
		return base64_decode( $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
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
