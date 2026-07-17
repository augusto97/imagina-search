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

= 6.23.0 =
* Analytics: results page searches made in direct Meilisearch mode are now recorded too. These never reach the server (the browser queries Meilisearch directly), so they were the last search path left unlogged; a non-blocking tracking beacon now reports them, without double-counting the proxy/local paths that already log server-side.

= 6.22.0 =
* Analytics: fixed the statistics panel showing all zeros. Local-engine searches were never recorded (they bypass the REST proxy that logs on the server and the direct-mode tracking beacon), so the search log stayed empty. Searches are now logged server-side directly from the local search endpoint, so volume, top queries, zero-result queries and CTR populate correctly. Also aligned the search-log timestamp to UTC to match the analytics date filters (fixes an off-by-timezone "Today" count).
* Admin: the "Results Page" tab no longer duplicates the Synonyms / Stop Words fields — these are now managed only in the "Synonyms & Typos" tab (Stop Words moved there), and changes apply to the engine on save.
* Admin: re-exposed local search cache statistics (cached entries and size) on the Connection tab next to the Purge Cache button; the endpoint existed but was not surfaced in the current admin.

= 6.21.0 =
* Search: typo tolerance for the local engine. Small misspellings now still find products — e.g. "pantlon" or "pantilon" match "pantalón". Uses Levenshtein distance as a fallback only on searches that would otherwise return nothing (so correctly spelled searches are not slowed down); tolerance scales with word length (1 typo for 5–8 letters, 2 for 9+). Can be toggled off in the new Synonyms & Typos tab. (Meilisearch was already typo-tolerant.)
* Admin: new "Synonyms & Typos" tab to manage search synonyms from the panel (word → equivalent terms) and toggle local-engine typo tolerance. Synonyms are applied to the active engine immediately on save, no full re-index required.

= 6.20.0 =
* Search: accent-insensitive matching in the local engine. Words with tildes/diacritics are now folded to their base form at both index and query time, so "aviación" and "aviacion", "jabón" and "jabon", etc. find each other regardless of accents. Also applies to synonyms and stop words for consistency. (Meilisearch was already accent-insensitive.) NOTE: run a full re-index once so existing products are stored in the new normalized form.

= 6.19.0 =
* Search: SKUs and product codes are now findable by any fragment. Previously a code like "F-0065" was only matched by "f-0065"/"f0065"; now "0065", "065", etc. also find the product. Each code is expanded into its substrings and indexed as extra searchable tokens (new internal "search_codes" field). NOTE: run a full re-index once so existing products pick up the new tokens.
* Incremental sync: product changes are now indexed at the end of the same request (after the response is flushed) instead of only waiting for the background cron. Saving/updating a product — via the editor, REST API, quick/bulk edit or import — reflects in search almost immediately, even on low-traffic stores or sites with WP-Cron disabled. The scheduled run remains as a fallback.

= 6.18.0 =
* Optimisation for the widget mobile layout: when desktop and mobile both use a layout that shares the same markup (standard/compact/amazon), the widget is now rendered only once and the layout class is swapped by viewport — no duplicated HTML. The widget is still rendered per-viewport only when one of the layouts changes the HTML structure (expanded/falabella/fullscreen), where it is unavoidable.

= 6.17.0 =
* Search widget: separate mobile layout. You can now pick a different widget layout for phones (≤767px) independently of the desktop layout, under Widget → Mobile Widget Layout. When a distinct mobile layout is set, the widget is rendered per-viewport so each layout keeps its full structure.

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
