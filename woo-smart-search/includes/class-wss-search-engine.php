<?php
/**
 * Search Engine Interface.
 *
 * Defines the contract that all search engine backends must implement.
 * Both Meilisearch (cloud/self-hosted) and Local (MySQL) engines
 * conform to this interface.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface WSS_Search_Engine
 */
interface WSS_Search_Engine {

	/**
	 * Test the connection / availability of the engine.
	 *
	 * @return array { success: bool, message: string, version: string }
	 */
	public function test_connection(): array;

	/**
	 * Create an index (or equivalent structure).
	 *
	 * @param string $index_name Index identifier.
	 * @return bool
	 */
	public function create_index( string $index_name ): bool;

	/**
	 * Delete an entire index.
	 *
	 * @param string $index_name Index identifier.
	 * @return bool
	 */
	public function delete_index( string $index_name ): bool;

	/**
	 * Get index statistics (document count, indexing status).
	 *
	 * @param string $index_name Index identifier.
	 * @return array { numberOfDocuments: int, isIndexing: bool }
	 */
	public function get_index_stats( string $index_name ): array;

	/**
	 * Configure index settings (searchable, filterable, sortable, displayed attributes).
	 *
	 * @param string $index_name Index identifier.
	 * @param array  $settings   Settings array.
	 * @return bool
	 */
	public function configure_index( string $index_name, array $settings ): bool;

	/**
	 * Add or replace documents in the index.
	 *
	 * @param string $index_name Index identifier.
	 * @param array  $documents  Array of document arrays.
	 * @return array { success: bool, message?: string }
	 */
	public function index_documents( string $index_name, array $documents ): array;

	/**
	 * Delete a single document by ID.
	 *
	 * @param string $index_name  Index identifier.
	 * @param string $document_id Document ID.
	 * @return bool
	 */
	public function delete_document( string $index_name, string $document_id ): bool;

	/**
	 * Delete all documents from an index.
	 *
	 * @param string $index_name Index identifier.
	 * @return bool
	 */
	public function delete_all_documents( string $index_name ): bool;

	/**
	 * Search documents.
	 *
	 * @param string $index_name Index identifier.
	 * @param string $query      Search query.
	 * @param array  $options    Search options (limit, offset, filters, facets, sort, highlight_fields).
	 * @return array { hits: array, query: string, estimatedTotalHits: int, facetDistribution: array, processingTimeMs: int }
	 */
	public function search( string $index_name, string $query, array $options = array() ): array;

	/**
	 * Set synonyms for the index.
	 *
	 * @param string $index_name Index identifier.
	 * @param array  $synonyms   Synonyms map.
	 * @return bool
	 */
	public function set_synonyms( string $index_name, array $synonyms ): bool;

	/**
	 * Set stop words for the index.
	 *
	 * @param string $index_name Index identifier.
	 * @param array  $stop_words Stop words list.
	 * @return bool
	 */
	public function set_stop_words( string $index_name, array $stop_words ): bool;

	/**
	 * Get the engine type identifier.
	 *
	 * @return string 'meilisearch' or 'local'
	 */
	public function get_engine_type(): string;
}
