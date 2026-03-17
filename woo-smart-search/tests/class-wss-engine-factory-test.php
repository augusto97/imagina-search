<?php
/**
 * Tests for WSS_Engine_Factory.
 *
 * @package WooSmartSearch
 */

/**
 * Class WSS_Engine_Factory_Test
 */
class WSS_Engine_Factory_Test extends WP_UnitTestCase {

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		WSS_Engine_Factory::reset();
	}

	/**
	 * Test creating a Meilisearch engine.
	 */
	public function test_create_meilisearch_engine() {
		$engine = WSS_Engine_Factory::create(
			'meilisearch',
			array(
				'host'     => 'localhost',
				'port'     => '7700',
				'protocol' => 'http',
				'api_key'  => 'test-key',
			)
		);

		$this->assertInstanceOf( WSS_Search_Engine::class, $engine );
		$this->assertInstanceOf( WSS_Meilisearch::class, $engine );
	}

	/**
	 * Test creating a Typesense engine.
	 */
	public function test_create_typesense_engine() {
		$engine = WSS_Engine_Factory::create(
			'typesense',
			array(
				'host'     => 'localhost',
				'port'     => '8108',
				'protocol' => 'http',
				'api_key'  => 'test-key',
			)
		);

		$this->assertInstanceOf( WSS_Search_Engine::class, $engine );
		$this->assertInstanceOf( WSS_Typesense::class, $engine );
	}

	/**
	 * Test creating with an invalid engine type.
	 */
	public function test_create_invalid_engine_returns_null() {
		$engine = WSS_Engine_Factory::create( 'invalid_engine', array() );
		$this->assertNull( $engine );
	}

	/**
	 * Test key encryption and decryption.
	 */
	public function test_encrypt_decrypt_key() {
		$original  = 'my-secret-api-key-123';
		$encrypted = WSS_Engine_Factory::encrypt_key( $original );

		$this->assertNotEquals( $original, $encrypted );

		$decrypted = WSS_Engine_Factory::decrypt_key( $encrypted );
		$this->assertEquals( $original, $decrypted );
	}

	/**
	 * Test empty key encryption.
	 */
	public function test_encrypt_empty_key() {
		$encrypted = WSS_Engine_Factory::encrypt_key( '' );
		$this->assertEquals( '', $encrypted );

		$decrypted = WSS_Engine_Factory::decrypt_key( '' );
		$this->assertEquals( '', $decrypted );
	}

	/**
	 * Test get_instance returns null when no API key is configured.
	 */
	public function test_get_instance_returns_null_without_api_key() {
		update_option( 'wss_settings', array( 'api_key' => '' ) );
		WSS_Engine_Factory::reset();

		$engine = WSS_Engine_Factory::get_instance();
		$this->assertNull( $engine );
	}
}
