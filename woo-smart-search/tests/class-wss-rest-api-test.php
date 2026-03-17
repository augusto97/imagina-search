<?php
/**
 * Tests for WSS_Rest_Api.
 *
 * @package WooSmartSearch
 */

/**
 * Class WSS_Rest_Api_Test
 */
class WSS_Rest_Api_Test extends WP_UnitTestCase {

	/**
	 * REST Server instance.
	 *
	 * @var WP_REST_Server
	 */
	private $server;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wp_rest_server;
		$this->server = $wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down after each test.
	 */
	public function tearDown(): void {
		parent::tearDown();

		global $wp_rest_server;
		$wp_rest_server = null;
	}

	/**
	 * Test that the search route is registered.
	 */
	public function test_search_route_registered() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wss/v1/search', $routes );
	}

	/**
	 * Test that a short query returns empty results.
	 */
	public function test_short_query_returns_empty() {
		$request = new WP_REST_Request( 'GET', '/wss/v1/search' );
		$request->set_param( 'q', 'a' );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEmpty( $data['hits'] );
		$this->assertEquals( 0, $data['total'] );
	}

	/**
	 * Test that query parameter is required.
	 */
	public function test_query_required() {
		$request  = new WP_REST_Request( 'GET', '/wss/v1/search' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test fallback search when no engine configured.
	 */
	public function test_fallback_search_without_engine() {
		// Ensure no engine is configured.
		update_option( 'wss_settings', array( 'api_key' => '' ) );
		WSS_Engine_Factory::reset();

		// Create a test product.
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Searchable Product' );
		$product->set_regular_price( '15.00' );
		$product->set_status( 'publish' );
		$product->save();

		$request = new WP_REST_Request( 'GET', '/wss/v1/search' );
		$request->set_param( 'q', 'Searchable' );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( isset( $data['fallback'] ) && $data['fallback'] );

		$product->delete( true );
	}
}
