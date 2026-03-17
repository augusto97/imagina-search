<?php
/**
 * Product Transformer.
 *
 * Transforms a WooCommerce product into a document for the search engine.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Product_Transformer
 */
class WSS_Product_Transformer {

	/**
	 * Transform a WC_Product into a search document.
	 *
	 * @param WC_Product $product The WooCommerce product.
	 * @return array The document array.
	 */
	public static function transform( $product ): array {
		if ( ! $product instanceof WC_Product ) {
			return array();
		}

		$image_id  = $product->get_image_id();
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';

		$gallery_ids  = $product->get_gallery_image_ids();
		$gallery_urls = array();
		foreach ( $gallery_ids as $gid ) {
			$url = wp_get_attachment_image_url( $gid, 'woocommerce_thumbnail' );
			if ( $url ) {
				$gallery_urls[] = $url;
			}
		}

		$categories    = array();
		$category_ids  = array();
		$category_slugs = array();
		$terms = get_the_terms( $product->get_id(), 'product_cat' );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$categories[]    = $term->name;
				$category_ids[]  = (int) $term->term_id;
				$category_slugs[] = $term->slug;
			}
		}

		$tags      = array();
		$tag_terms = get_the_terms( $product->get_id(), 'product_tag' );
		if ( ! empty( $tag_terms ) && ! is_wp_error( $tag_terms ) ) {
			foreach ( $tag_terms as $term ) {
				$tags[] = $term->name;
			}
		}

		// Get attributes.
		$attributes      = array();
		$attributes_text = '';
		$product_attrs   = $product->get_attributes();
		foreach ( $product_attrs as $attr_key => $attr ) {
			if ( $attr instanceof WC_Product_Attribute ) {
				$attr_name = wc_attribute_label( $attr->get_name() );
				$values    = $attr->is_taxonomy()
					? wc_get_product_terms( $product->get_id(), $attr->get_name(), array( 'fields' => 'names' ) )
					: $attr->get_options();
				$attributes[ $attr_name ] = $values;
				$attributes_text .= $attr_name . ': ' . implode( ', ', $values ) . '. ';
			}
		}

		// Brand (check for popular brand plugins/taxonomies).
		$brand = '';
		$brand_terms = get_the_terms( $product->get_id(), 'product_brand' );
		if ( empty( $brand_terms ) || is_wp_error( $brand_terms ) ) {
			$brand_terms = get_the_terms( $product->get_id(), 'pwb-brand' );
		}
		if ( ! empty( $brand_terms ) && ! is_wp_error( $brand_terms ) ) {
			$brand = $brand_terms[0]->name;
		}

		// Handle variable products.
		$variations_text = '';
		$price_min       = (float) $product->get_price();
		$price_max       = (float) $product->get_price();

		if ( $product->is_type( 'variable' ) ) {
			$prices    = $product->get_variation_prices( true );
			$price_min = ! empty( $prices['price'] ) ? (float) min( $prices['price'] ) : $price_min;
			$price_max = ! empty( $prices['price'] ) ? (float) max( $prices['price'] ) : $price_max;

			$variations = $product->get_available_variations();
			$var_texts  = array();
			foreach ( $variations as $variation ) {
				$var_attrs = array();
				foreach ( $variation['attributes'] as $var_attr_key => $var_attr_val ) {
					if ( ! empty( $var_attr_val ) ) {
						$var_attrs[] = $var_attr_val;
					}
				}
				if ( ! empty( $var_attrs ) ) {
					$var_texts[] = implode( ' ', $var_attrs );
				}
			}
			$variations_text = implode( ', ', $var_texts );
		}

		$sale_price    = $product->get_sale_price();
		$regular_price = $product->get_regular_price();

		$document = array(
			'id'               => (int) $product->get_id(),
			'name'             => $product->get_name(),
			'slug'             => $product->get_slug(),
			'description'      => self::truncate_text( wp_strip_all_tags( $product->get_short_description() ), 500 ),
			'full_description' => self::truncate_text( wp_strip_all_tags( $product->get_description() ), 2000 ),
			'sku'              => $product->get_sku() ? $product->get_sku() : '',
			'permalink'        => get_permalink( $product->get_id() ),
			'image'            => $image_url ? $image_url : '',
			'gallery'          => $gallery_urls,
			'price'            => (float) $product->get_price(),
			'regular_price'    => $regular_price ? (float) $regular_price : 0.0,
			'sale_price'       => $sale_price ? (float) $sale_price : 0.0,
			'on_sale'          => $product->is_on_sale(),
			'currency'         => get_woocommerce_currency(),
			'stock_status'     => $product->get_stock_status(),
			'stock_quantity'   => $product->get_stock_quantity(),
			'categories'       => $categories,
			'category_ids'     => $category_ids,
			'category_slugs'   => $category_slugs,
			'tags'             => $tags,
			'attributes'       => $attributes,
			'attributes_text'  => trim( $attributes_text ),
			'brand'            => $brand,
			'rating'           => (float) $product->get_average_rating(),
			'review_count'     => (int) $product->get_review_count(),
			'type'             => $product->get_type(),
			'visibility'       => $product->get_catalog_visibility(),
			'featured'         => $product->is_featured(),
			'date_created'     => $product->get_date_created() ? (int) $product->get_date_created()->getTimestamp() : 0,
			'date_modified'    => $product->get_date_modified() ? (int) $product->get_date_modified()->getTimestamp() : 0,
			'total_sales'      => (int) $product->get_total_sales(),
			'menu_order'       => (int) $product->get_menu_order(),
			'weight'           => $product->get_weight() ? $product->get_weight() : '',
			'dimensions'       => array(
				'length' => $product->get_length() ? $product->get_length() : '',
				'width'  => $product->get_width() ? $product->get_width() : '',
				'height' => $product->get_height() ? $product->get_height() : '',
			),
			'variations_text'  => $variations_text,
			'price_min'        => $price_min,
			'price_max'        => $price_max,
			'custom_fields'    => self::get_custom_fields( $product->get_id() ),
		);

		/**
		 * Filter the product document before indexing.
		 *
		 * @param array      $document The document array.
		 * @param WC_Product $product  The WooCommerce product.
		 */
		return apply_filters( 'wss_product_document', $document, $product );
	}

	/**
	 * Get custom fields for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	private static function get_custom_fields( int $product_id ): array {
		$configured_fields = wss_get_option( 'custom_fields', array() );
		if ( empty( $configured_fields ) ) {
			return array();
		}

		$fields = array();
		foreach ( $configured_fields as $field_key ) {
			$value = get_post_meta( $product_id, $field_key, true );
			if ( ! empty( $value ) ) {
				$fields[ $field_key ] = is_array( $value ) ? $value : (string) $value;
			}
		}

		return $fields;
	}

	/**
	 * Truncate text to a maximum length.
	 *
	 * @param string $text       The text to truncate.
	 * @param int    $max_length Maximum character length.
	 * @return string
	 */
	private static function truncate_text( string $text, int $max_length ): string {
		$text = trim( $text );
		if ( mb_strlen( $text ) <= $max_length ) {
			return $text;
		}
		return mb_substr( $text, 0, $max_length );
	}
}
