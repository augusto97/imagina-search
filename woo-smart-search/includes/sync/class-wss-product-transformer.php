<?php
/**
 * Product Transformer.
 *
 * Transforms a WooCommerce product into a document for the search engine.
 * Supports simple, variable, grouped, and external product types.
 * Includes ACF/Custom Fields integration.
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
	 * For variable products, the parent product is indexed with aggregated
	 * variation data including price range, all variation SKUs, all variation
	 * attributes as searchable text, and a variations count.
	 *
	 * @param WC_Product $product The WooCommerce product.
	 * @return array The document array.
	 */
	public static function transform( $product ): array {
		if ( ! $product instanceof WC_Product ) {
			return array();
		}

		$product_id = $product->get_id();

		// Image.
		$image_url = self::get_product_image( $product );

		// Gallery.
		$gallery_urls = self::get_gallery_images( $product );

		// Categories.
		$categories     = array();
		$category_ids   = array();
		$category_slugs = array();
		self::extract_terms( $product_id, 'product_cat', $categories, $category_ids, $category_slugs );

		// Tags.
		$tags      = array();
		$tag_ids   = array();
		$tag_slugs = array();
		self::extract_terms( $product_id, 'product_tag', $tags, $tag_ids, $tag_slugs );

		// Attributes.
		$attributes      = array();
		$attributes_text = '';
		self::extract_attributes( $product, $attributes, $attributes_text );

		// Brand.
		$brand = self::get_brand( $product_id );

		// Pricing and variation data.
		$price_min       = 0.0;
		$price_max       = 0.0;
		$variation_skus  = array();
		$variations_text = '';
		$variations_count = 0;

		if ( $product->is_type( 'variable' ) ) {
			self::extract_variable_data(
				$product,
				$price_min,
				$price_max,
				$variation_skus,
				$variations_text,
				$variations_count
			);
		} else {
			$raw_price = $product->get_price( 'edit' );
			$price_min = '' !== $raw_price && null !== $raw_price ? (float) $raw_price : 0.0;
			$price_max = $price_min;
		}

		// Sale and regular prices.
		$sale_price    = $product->get_sale_price( 'edit' );
		$regular_price = $product->get_regular_price( 'edit' );

		// Short and full descriptions.
		$short_description = wp_strip_all_tags( (string) $product->get_short_description() );
		$full_description  = wp_strip_all_tags( (string) $product->get_description() );

		// SKU (parent).
		$parent_sku = $product->get_sku();

		// Combine all SKUs for searchability.
		$all_skus = array();
		if ( ! empty( $parent_sku ) ) {
			$all_skus[] = $parent_sku;
		}
		$all_skus = array_merge( $all_skus, $variation_skus );
		$all_skus = array_unique( array_filter( $all_skus ) );

		// Custom fields (ACF-aware).
		$custom_fields = self::get_custom_fields( $product_id );

		// Permalink - guard against edge cases.
		$permalink = get_permalink( $product_id );
		if ( ! $permalink ) {
			$permalink = '';
		}

		$document = array(
			'id'               => (int) $product_id,
			'name'             => html_entity_decode( $product->get_name(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'slug'             => $product->get_slug(),
			'description'      => self::truncate_text( $short_description, 500 ),
			'full_description' => self::truncate_text( $full_description, 2000 ),
			'sku'              => ! empty( $parent_sku ) ? $parent_sku : '',
			'all_skus'         => $all_skus,
			'permalink'        => $permalink,
			'image'            => $image_url,
			'gallery'          => $gallery_urls,
			'price'            => $price_min,
			'regular_price'    => '' !== $regular_price && null !== $regular_price ? (float) $regular_price : 0.0,
			'sale_price'       => '' !== $sale_price && null !== $sale_price ? (float) $sale_price : 0.0,
			'price_min'        => $price_min,
			'price_max'        => $price_max,
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
			'date_created'     => $product->get_date_created()
				? (int) $product->get_date_created()->getTimestamp()
				: 0,
			'date_modified'    => $product->get_date_modified()
				? (int) $product->get_date_modified()->getTimestamp()
				: 0,
			'total_sales'      => (int) $product->get_total_sales(),
			'menu_order'       => (int) $product->get_menu_order(),
			'weight'           => $product->get_weight() ? $product->get_weight() : '',
			'dimensions'       => array(
				'length' => $product->get_length() ? $product->get_length() : '',
				'width'  => $product->get_width() ? $product->get_width() : '',
				'height' => $product->get_height() ? $product->get_height() : '',
			),
			'variations_text'  => $variations_text,
			'variations_count' => $variations_count,
			'variation_skus'   => $variation_skus,
			'custom_fields'    => $custom_fields,
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
	 * Get the primary product image URL.
	 *
	 * @param WC_Product $product The product.
	 * @return string
	 */
	private static function get_product_image( $product ): string {
		$image_id = $product->get_image_id();

		if ( ! $image_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );

		return $url ? $url : '';
	}

	/**
	 * Get gallery image URLs.
	 *
	 * @param WC_Product $product The product.
	 * @return array
	 */
	private static function get_gallery_images( $product ): array {
		$gallery_ids  = $product->get_gallery_image_ids();
		$gallery_urls = array();

		if ( empty( $gallery_ids ) ) {
			return $gallery_urls;
		}

		foreach ( $gallery_ids as $gid ) {
			$url = wp_get_attachment_image_url( $gid, 'woocommerce_thumbnail' );
			if ( $url ) {
				$gallery_urls[] = $url;
			}
		}

		return $gallery_urls;
	}

	/**
	 * Extract taxonomy terms for a product.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $taxonomy   Taxonomy name.
	 * @param array  $names      Populated with term names.
	 * @param array  $ids        Populated with term IDs.
	 * @param array  $slugs      Populated with term slugs.
	 */
	private static function extract_terms( int $product_id, string $taxonomy, array &$names, array &$ids, array &$slugs ) {
		$terms = get_the_terms( $product_id, $taxonomy );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			// Decode HTML entities so Meilisearch stores clean values
			// (WordPress stores "Chairs &amp; Sofas" but we need "Chairs & Sofas").
			$names[] = html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$ids[]   = (int) $term->term_id;
			$slugs[] = $term->slug;
		}
	}

	/**
	 * Extract product attributes and build searchable text.
	 *
	 * @param WC_Product $product         The product.
	 * @param array      $attributes      Populated with attribute data.
	 * @param string     $attributes_text Populated with searchable text.
	 */
	private static function extract_attributes( $product, array &$attributes, string &$attributes_text ) {
		$product_attrs = $product->get_attributes();

		foreach ( $product_attrs as $attr_key => $attr ) {
			if ( ! $attr instanceof WC_Product_Attribute ) {
				continue;
			}

			$attr_name = wc_attribute_label( $attr->get_name() );
			$values    = $attr->is_taxonomy()
				? wc_get_product_terms( $product->get_id(), $attr->get_name(), array( 'fields' => 'names' ) )
				: $attr->get_options();

			if ( empty( $values ) || is_wp_error( $values ) ) {
				$values = array();
			}

			// Decode HTML entities in attribute values (WordPress stores &amp; etc.).
			$values = array_map( function ( $v ) {
				return html_entity_decode( (string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}, $values );

			$attributes[ $attr_name ] = $values;
			$attributes_text         .= $attr_name . ': ' . implode( ', ', $values ) . '. ';
		}
	}

	/**
	 * Get the brand name for a product.
	 *
	 * Checks popular brand plugin taxonomies.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private static function get_brand( int $product_id ): string {
		$brand_taxonomies = array( 'product_brand', 'pwb-brand' );

		foreach ( $brand_taxonomies as $taxonomy ) {
			$brand_terms = get_the_terms( $product_id, $taxonomy );

			if ( ! empty( $brand_terms ) && ! is_wp_error( $brand_terms ) ) {
				return html_entity_decode( $brand_terms[0]->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}

		return '';
	}

	/**
	 * Extract aggregated data from a variable product's variations.
	 *
	 * Collects price range (min/max), all variation SKUs, variation attribute
	 * values as searchable text, and the total variations count.
	 *
	 * @param WC_Product_Variable $product          The variable product.
	 * @param float               $price_min        Populated with minimum price.
	 * @param float               $price_max        Populated with maximum price.
	 * @param array               $variation_skus   Populated with variation SKUs.
	 * @param string              $variations_text  Populated with searchable variation text.
	 * @param int                 $variations_count Populated with number of variations.
	 */
	private static function extract_variable_data(
		$product,
		float &$price_min,
		float &$price_max,
		array &$variation_skus,
		string &$variations_text,
		int &$variations_count
	) {
		// Get variation prices efficiently (WooCommerce caches these).
		$prices = $product->get_variation_prices( true );

		if ( ! empty( $prices['price'] ) ) {
			$price_values = array_filter( $prices['price'], function ( $p ) {
				return '' !== $p && null !== $p;
			} );

			if ( ! empty( $price_values ) ) {
				$price_min = (float) min( $price_values );
				$price_max = (float) max( $price_values );
			}
		}

		// Fall back to parent price if no variation prices available.
		if ( 0.0 === $price_min && 0.0 === $price_max ) {
			$parent_price = $product->get_price( 'edit' );
			if ( '' !== $parent_price && null !== $parent_price ) {
				$price_min = (float) $parent_price;
				$price_max = $price_min;
			}
		}

		// Get children (variation IDs) without loading full variation objects.
		$variation_ids    = $product->get_children();
		$variations_count = count( $variation_ids );

		$var_texts = array();

		foreach ( $variation_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation || ! $variation instanceof WC_Product_Variation ) {
				continue;
			}

			// Collect SKU.
			$var_sku = $variation->get_sku();
			if ( ! empty( $var_sku ) ) {
				$variation_skus[] = $var_sku;
			}

			// Collect attribute values as searchable text.
			$var_attributes = $variation->get_attributes();
			$attr_parts     = array();

			foreach ( $var_attributes as $attr_key => $attr_value ) {
				if ( empty( $attr_value ) ) {
					continue;
				}

				// Resolve taxonomy term name if applicable.
				if ( taxonomy_exists( $attr_key ) ) {
					$term = get_term_by( 'slug', $attr_value, $attr_key );
					if ( $term && ! is_wp_error( $term ) ) {
						$attr_parts[] = $term->name;
						continue;
					}
				}

				$attr_parts[] = $attr_value;
			}

			if ( ! empty( $attr_parts ) ) {
				$var_texts[] = implode( ' ', $attr_parts );
			}
		}

		$variation_skus  = array_unique( array_filter( $variation_skus ) );
		$variations_text = implode( ', ', $var_texts );
	}

	/**
	 * Get custom fields for a product with ACF support.
	 *
	 * Loops through configured custom_fields from plugin settings. If ACF
	 * is active, uses get_field_object() for richer data extraction.
	 * All custom field keys are prefixed with cf_ in the output.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	private static function get_custom_fields( int $product_id ): array {
		$configured_fields = wss_get_option( 'custom_fields', array() );

		if ( empty( $configured_fields ) || ! is_array( $configured_fields ) ) {
			return array();
		}

		$acf_active = function_exists( 'get_field_object' );
		$fields     = array();

		foreach ( $configured_fields as $field_key ) {
			if ( empty( $field_key ) || ! is_string( $field_key ) ) {
				continue;
			}

			$prefixed_key = 'cf_' . $field_key;

			if ( $acf_active ) {
				$field_object = get_field_object( $field_key, $product_id );

				if ( $field_object && ! empty( $field_object['value'] ) ) {
					$value = $field_object['value'];

					// Handle specific ACF field types.
					if ( is_array( $value ) ) {
						// Arrays of objects (e.g., relationship, taxonomy fields).
						$flattened = self::flatten_acf_value( $value );
						if ( ! empty( $flattened ) ) {
							$fields[ $prefixed_key ] = $flattened;
						}
					} else {
						$fields[ $prefixed_key ] = (string) $value;
					}

					continue;
				}
			}

			// Fallback to standard post meta.
			$value = get_post_meta( $product_id, $field_key, true );

			if ( '' === $value || null === $value ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$fields[ $prefixed_key ] = array_map( 'strval', array_filter( $value ) );
			} else {
				$fields[ $prefixed_key ] = (string) $value;
			}
		}

		return $fields;
	}

	/**
	 * Flatten an ACF array value into a searchable format.
	 *
	 * Handles arrays of WP_Post objects, WP_Term objects, or scalar values.
	 *
	 * @param array $value The ACF field value.
	 * @return array|string
	 */
	private static function flatten_acf_value( array $value ) {
		$result = array();

		foreach ( $value as $item ) {
			if ( $item instanceof WP_Post ) {
				$result[] = $item->post_title;
			} elseif ( $item instanceof WP_Term ) {
				$result[] = $item->name;
			} elseif ( is_scalar( $item ) ) {
				$result[] = (string) $item;
			}
		}

		return array_filter( $result );
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
