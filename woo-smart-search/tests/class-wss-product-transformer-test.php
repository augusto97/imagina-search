<?php
/**
 * Tests for WSS_Product_Transformer.
 *
 * @package WooSmartSearch
 */

/**
 * Class WSS_Product_Transformer_Test
 */
class WSS_Product_Transformer_Test extends WP_UnitTestCase {

	/**
	 * Test that transform returns empty array for non-product.
	 */
	public function test_transform_returns_empty_for_invalid_product() {
		$result = WSS_Product_Transformer::transform( 'not a product' );
		$this->assertEmpty( $result );
	}

	/**
	 * Test that transform returns correct structure for simple product.
	 */
	public function test_transform_simple_product() {
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_slug( 'test-product' );
		$product->set_regular_price( '29.99' );
		$product->set_sku( 'TEST-001' );
		$product->set_short_description( 'Short description' );
		$product->set_description( 'Full description of the product' );
		$product->set_stock_status( 'instock' );
		$product->set_catalog_visibility( 'visible' );
		$product->save();

		$document = WSS_Product_Transformer::transform( $product );

		$this->assertIsArray( $document );
		$this->assertEquals( $product->get_id(), $document['id'] );
		$this->assertEquals( 'Test Product', $document['name'] );
		$this->assertEquals( 'test-product', $document['slug'] );
		$this->assertEquals( 'TEST-001', $document['sku'] );
		$this->assertEquals( 29.99, $document['price'] );
		$this->assertEquals( 29.99, $document['regular_price'] );
		$this->assertEquals( 'instock', $document['stock_status'] );
		$this->assertEquals( 'simple', $document['type'] );
		$this->assertFalse( $document['on_sale'] );
		$this->assertIsArray( $document['categories'] );
		$this->assertIsArray( $document['tags'] );
		$this->assertIsArray( $document['custom_fields'] );

		$product->delete( true );
	}

	/**
	 * Test that sale products are correctly detected.
	 */
	public function test_transform_sale_product() {
		$product = new WC_Product_Simple();
		$product->set_name( 'Sale Product' );
		$product->set_regular_price( '50.00' );
		$product->set_sale_price( '25.00' );
		$product->save();

		$document = WSS_Product_Transformer::transform( $product );

		$this->assertTrue( $document['on_sale'] );
		$this->assertEquals( 25.00, $document['price'] );
		$this->assertEquals( 50.00, $document['regular_price'] );
		$this->assertEquals( 25.00, $document['sale_price'] );

		$product->delete( true );
	}

	/**
	 * Test that description is truncated.
	 */
	public function test_transform_truncates_description() {
		$product = new WC_Product_Simple();
		$product->set_name( 'Product' );
		$product->set_regular_price( '10' );
		$product->set_short_description( str_repeat( 'A', 600 ) );
		$product->save();

		$document = WSS_Product_Transformer::transform( $product );

		$this->assertEquals( 500, mb_strlen( $document['description'] ) );

		$product->delete( true );
	}

	/**
	 * Test the wss_product_document filter.
	 */
	public function test_product_document_filter() {
		add_filter(
			'wss_product_document',
			function ( $doc, $product ) {
				$doc['custom_key'] = 'custom_value';
				return $doc;
			},
			10,
			2
		);

		$product = new WC_Product_Simple();
		$product->set_name( 'Filter Test' );
		$product->set_regular_price( '10' );
		$product->save();

		$document = WSS_Product_Transformer::transform( $product );

		$this->assertEquals( 'custom_value', $document['custom_key'] );

		$product->delete( true );
		remove_all_filters( 'wss_product_document' );
	}
}
