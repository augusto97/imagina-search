=== Woo Smart Search ===
Contributors: imagina
Tags: woocommerce, search, meilisearch, typesense, instant search
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Replace WooCommerce native search with an instant, ultra-fast search experience powered by Meilisearch or Typesense.

== Description ==

Woo Smart Search integrates an external search engine (Meilisearch or Typesense) to replace the native WooCommerce search with a professional, instant search experience.

**Key Features:**

* **Instant Search** - Results appear as you type with 200ms debounce
* **Typo Tolerance** - Built-in fuzzy matching for misspelled queries
* **Dual Engine Support** - Choose between Meilisearch or Typesense
* **Faceted Filters** - Filter by category, price, stock, attributes
* **Highlighting** - Search terms are highlighted in results
* **Keyboard Navigation** - Full arrow key, Enter, Escape support
* **Responsive Design** - Mobile-first with fullscreen dropdown on phones
* **WooCommerce Integration** - Auto-syncs products on create/update/delete
* **Customizable** - Colors, fonts, visible elements, custom CSS
* **Developer Friendly** - 20+ hooks and filters for extensibility
* **Fallback** - Gracefully falls back to native search if engine is unavailable

== Installation ==

1. Upload the `woo-smart-search` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Go to WooCommerce > Smart Search to configure
4. Enter your Meilisearch or Typesense credentials
5. Click "Test Connection" to verify
6. Run "Full Sync" to index all products
7. The search widget will automatically replace the native search

== Frequently Asked Questions ==

= What search engines are supported? =

Meilisearch (v1.0+) and Typesense (v0.25+).

= Do I need to host the search engine myself? =

Yes, you need a running Meilisearch or Typesense instance. Both can run on the same server as WordPress or on a separate VPS. Cloud-hosted options are also available.

= Does it work with variable products? =

Yes, variable products are indexed with all their variations, including variation-specific attributes and price ranges.

= Can I customize which fields are searchable? =

Yes, through the admin panel and the `wss_searchable_attributes` filter.

= Does it support WPML/Polylang? =

Basic support is included. Full multi-language indexing depends on your setup.

== Changelog ==

= 1.0.0 =
* Initial release
* Meilisearch and Typesense support
* Full and incremental product sync
* Instant search widget with autocomplete
* Admin panel with connection, indexing, search, appearance, and log tabs
* Shortcode, WordPress widget, and Gutenberg block
* REST API proxy with rate limiting and caching
* Keyboard navigation and accessibility
* Spanish translation included
