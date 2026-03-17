<?php
/**
 * Search Engine Interface.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface WSS_Search_Engine
 *
 * Common interface for all search engine adapters.
 */
interface WSS_Search_Engine {

	/**
	 * Connect to the search engine.
	 *
	 * @param array $config Connection configuration.
	 * @return bool
	 */
	public function connect( array $config ): bool;

	/**
	 * Test the connection to the search engine.
	 *
	 * @return array { success: bool, message: string, version: string }
	 */
	public function test_connection(): array;

	/**
	 * Create an index.
	 *
	 * @param string $index_name Index name.
	 * @param array  $settings   Index settings.
	 * @return bool
	 */
	public function create_index( string $index_name, array $settings = array() ): bool;

	/**
	 * Delete an index.
	 *
	 * @param string $index_name Index name.
	 * @return bool
	 */
	public function delete_index( string $index_name ): bool;

	/**
	 * Get index statistics.
	 *
	 * @param string $index_name Index name.
	 * @return array
	 */
	public function get_index_stats( string $index_name ): array;

	/**
	 * Configure index settings.
	 *
	 * @param string $index_name Index name.
	 * @param array  $settings   Settings to apply.
	 * @return bool
	 */
	public function configure_index( string $index_name, array $settings ): bool;

	/**
	 * Index documents (add or replace).
	 *
	 * @param string $index_name Index name.
	 * @param array  $documents  Documents to index.
	 * @return array
	 */
	public function index_documents( string $index_name, array $documents ): array;

	/**
	 * Update existing documents.
	 *
	 * @param string $index_name Index name.
	 * @param array  $documents  Documents to update.
	 * @return array
	 */
	public function update_documents( string $index_name, array $documents ): array;

	/**
	 * Delete a single document.
	 *
	 * @param string $index_name  Index name.
	 * @param string $document_id Document ID.
	 * @return bool
	 */
	public function delete_document( string $index_name, string $document_id ): bool;

	/**
	 * Delete all documents from an index.
	 *
	 * @param string $index_name Index name.
	 * @return bool
	 */
	public function delete_all_documents( string $index_name ): bool;

	/**
	 * Search documents.
	 *
	 * @param string $index_name Index name.
	 * @param string $query      Search query.
	 * @param array  $options    Search options (limit, offset, filters, facets, sort, highlight_fields).
	 * @return array
	 */
	public function search( string $index_name, string $query, array $options = array() ): array;

	/**
	 * Set searchable attributes.
	 *
	 * @param string $index_name Index name.
	 * @param array  $attributes Attributes list.
	 * @return bool
	 */
	public function set_searchable_attributes( string $index_name, array $attributes ): bool;

	/**
	 * Set filterable attributes.
	 *
	 * @param string $index_name Index name.
	 * @param array  $attributes Attributes list.
	 * @return bool
	 */
	public function set_filterable_attributes( string $index_name, array $attributes ): bool;

	/**
	 * Set sortable attributes.
	 *
	 * @param string $index_name Index name.
	 * @param array  $attributes Attributes list.
	 * @return bool
	 */
	public function set_sortable_attributes( string $index_name, array $attributes ): bool;

	/**
	 * Set synonyms.
	 *
	 * @param string $index_name Index name.
	 * @param array  $synonyms   Synonyms configuration.
	 * @return bool
	 */
	public function set_synonyms( string $index_name, array $synonyms ): bool;

	/**
	 * Set stop words.
	 *
	 * @param string $index_name Index name.
	 * @param array  $stop_words Stop words list.
	 * @return bool
	 */
	public function set_stop_words( string $index_name, array $stop_words ): bool;
}
