<?php
/**
 * Engine Factory.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Engine_Factory
 *
 * Factory pattern to instantiate the selected search engine.
 */
class WSS_Engine_Factory {

	/**
	 * Cached engine instance.
	 *
	 * @var WSS_Search_Engine|null
	 */
	private static $instance = null;

	/**
	 * Get or create the search engine instance.
	 *
	 * @return WSS_Search_Engine|null
	 */
	public static function get_instance() {
		if ( null !== self::$instance ) {
			return self::$instance;
		}

		$engine_type = wss_get_option( 'engine', 'meilisearch' );
		$api_key     = wss_get_option( 'api_key', '' );

		if ( empty( $api_key ) ) {
			return null;
		}

		$config = array(
			'host'     => wss_get_option( 'host', 'localhost' ),
			'port'     => wss_get_option( 'port', 'meilisearch' === $engine_type ? '7700' : '8108' ),
			'protocol' => wss_get_option( 'protocol', 'http' ),
			'api_key'  => self::decrypt_key( $api_key ),
		);

		self::$instance = self::create( $engine_type, $config );

		return self::$instance;
	}

	/**
	 * Create a new search engine instance.
	 *
	 * @param string $type   Engine type (meilisearch or typesense).
	 * @param array  $config Connection config.
	 * @return WSS_Search_Engine|null
	 */
	public static function create( string $type, array $config ) {
		switch ( $type ) {
			case 'meilisearch':
				$engine = new WSS_Meilisearch();
				break;
			case 'typesense':
				$engine = new WSS_Typesense();
				break;
			default:
				return null;
		}

		$engine->connect( $config );

		return $engine;
	}

	/**
	 * Reset the cached instance (useful for testing or config changes).
	 */
	public static function reset() {
		self::$instance = null;
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

		// Fallback: simple obfuscation.
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
}
