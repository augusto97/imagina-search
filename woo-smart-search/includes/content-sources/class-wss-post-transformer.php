<?php
/**
 * Post Transformer.
 *
 * Transforms a WordPress post (of any type) into a document for Meilisearch.
 * Used for non-WooCommerce content: posts, pages, and custom post types.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSS_Post_Transformer
 */
class WSS_Post_Transformer {

	/**
	 * Transform a WP_Post into a search document.
	 *
	 * @param WP_Post $post The WordPress post.
	 * @return array The document array.
	 */
	public static function transform( $post ): array {
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$post_id = $post->ID;

		// Image.
		$image_url = self::get_featured_image( $post_id );

		// Taxonomies.
		$categories     = array();
		$category_ids   = array();
		$category_slugs = array();
		$tags           = array();
		$tag_ids        = array();
		$tag_slugs      = array();

		// Get all taxonomies for this post type.
		$taxonomies          = get_object_taxonomies( $post->post_type, 'objects' );
		$all_terms_text      = '';
		$custom_taxonomies   = array();
		$custom_tax_by_slug  = array(); // tax_name => term_names for flat filtering keys.

		foreach ( $taxonomies as $tax_name => $tax_obj ) {
			$terms = get_the_terms( $post_id, $tax_name );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}

			$term_names = array();
			$term_ids   = array();
			$term_slugs = array();

			foreach ( $terms as $term ) {
				$decoded      = html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$term_names[] = $decoded;
				$term_ids[]   = (int) $term->term_id;
				$term_slugs[] = $term->slug;
			}

			// Map standard taxonomies.
			if ( 'category' === $tax_name || 'product_cat' === $tax_name ) {
				$categories     = $term_names;
				$category_ids   = $term_ids;
				$category_slugs = $term_slugs;
			} elseif ( 'post_tag' === $tax_name || 'product_tag' === $tax_name ) {
				$tags      = $term_names;
				$tag_ids   = $term_ids;
				$tag_slugs = $term_slugs;
			} else {
				// Store custom taxonomies for filtering.
				$custom_taxonomies[ $tax_obj->label ] = $term_names;
				$custom_tax_by_slug[ $tax_name ]      = $term_names;
			}

			$all_terms_text .= implode( ' ', $term_names ) . ' ';
		}

		// Content.
		$content     = wp_strip_all_tags( $post->post_content );
		$excerpt     = wp_strip_all_tags( $post->post_excerpt );
		$description = ! empty( $excerpt ) ? $excerpt : self::truncate_text( $content, 500 );

		// Author.
		$author_name = get_the_author_meta( 'display_name', $post->post_author );

		// Permalink.
		$permalink = get_permalink( $post_id );
		if ( ! $permalink ) {
			$permalink = '';
		}

		// Custom fields (ACF-aware).
		$custom_fields = self::get_custom_fields( $post_id );

		$document = array(
			'id'               => (int) $post_id,
			'name'             => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'slug'             => $post->post_name,
			'description'      => self::truncate_text( $description, 500 ),
			'full_description' => self::truncate_text( $content, 2000 ),
			'permalink'        => $permalink,
			'image'            => $image_url,
			'post_type'        => $post->post_type,
			'categories'       => $categories,
			'category_ids'     => $category_ids,
			'category_slugs'   => $category_slugs,
			'tags'             => $tags,
			'taxonomies'       => $custom_taxonomies,
			'taxonomies_text'  => trim( $all_terms_text ),
			'author'           => $author_name ? $author_name : '',
			'date_created'     => strtotime( $post->post_date_gmt ) ? (int) strtotime( $post->post_date_gmt ) : 0,
			'date_modified'    => strtotime( $post->post_modified_gmt ) ? (int) strtotime( $post->post_modified_gmt ) : 0,
			'menu_order'       => (int) $post->menu_order,
			'comment_count'    => (int) $post->comment_count,
			'custom_fields'    => $custom_fields,
			'content_source'   => 'wordpress',
		);

		// Flatten custom taxonomies to top-level keys for Meilisearch filtering.
		// e.g. tax_genre: ["Fiction", "Drama"] so they become filterable attributes.
		foreach ( $custom_tax_by_slug as $tax_name => $term_names ) {
			$document[ 'tax_' . $tax_name ] = $term_names;
		}

		// Flatten custom fields to top-level keys for Meilisearch filtering.
		// They already use cf_ prefix inside the custom_fields array.
		foreach ( $custom_fields as $cf_key => $cf_value ) {
			$document[ $cf_key ] = $cf_value;
		}

		/**
		 * Filter the post document before indexing.
		 *
		 * @param array   $document The document array.
		 * @param WP_Post $post     The WordPress post.
		 */
		return apply_filters( 'wss_post_document', $document, $post );
	}

	/**
	 * Get the featured image URL.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function get_featured_image( int $post_id ): string {
		$image_id = get_post_thumbnail_id( $post_id );

		if ( ! $image_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $image_id, 'medium' );

		return $url ? $url : '';
	}

	/**
	 * Get custom fields for a post with ACF support.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private static function get_custom_fields( int $post_id ): array {
		$configured_fields = wss_get_option( 'wp_custom_fields', array() );

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
				$field_object = get_field_object( $field_key, $post_id );

				if ( $field_object && ! empty( $field_object['value'] ) ) {
					$value = $field_object['value'];

					if ( is_array( $value ) ) {
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
			$value = get_post_meta( $post_id, $field_key, true );

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
	 * @param array $value The ACF field value.
	 * @return array
	 */
	private static function flatten_acf_value( array $value ): array {
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
