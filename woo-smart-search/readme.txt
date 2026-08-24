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

= 6.32.0 =
* Admin: the "Last Sync" indicator now reflects real sync activity. It was only updated by a manual Full Sync, so it could read "days ago" even while incremental syncs kept the index current — making it look like indexing had stopped when it had not. It now also updates whenever the queue processes items (incremental and periodic syncs), so a genuinely stale value is a real signal that nothing is syncing (usually WP-Cron / Action Scheduler not running on the site).

= 6.31.0 =
* Sync: price/stock changes pushed straight to postmeta are now picked up. Some API integrations and ERPs update a product with update_post_meta('_price', ...) instead of the WooCommerce CRUD save, so woocommerce_update_product never fires and the meta-change handler ignored those keys (it only watched configured custom fields) — search kept showing the old price/stock until a full sync. The handler now also re-indexes the product when a core WooCommerce field changes (_price, _regular_price, _sale_price, sale dates, _stock, _stock_status, _manage_stock, _backorders, _sku, dimensions). Note: integrations that write with raw SQL (no update_post_meta) still fire no hook and are covered only by the periodic reindex.

= 6.30.0 =
* Sync: WooCommerce Advanced Bulk Edit (WCABE) integration, verified against the plugin's actual source (v6.2). Its bulk save writes with direct SQL and does NOT fire the standard WooCommerce/WordPress hooks — which is why bulk edits only showed up on a scheduled reindex — but it fires a per-product action, wcabe_product_save_completed, with each saved product's ID. The plugin now listens to that action and re-indexes exactly the edited products (a variation re-indexes its parent) in the same request, so WCABE bulk edits appear in search immediately. Supersedes the 6.29.0 approach, which hooked the argument-less wcabe_after_bulk_save with a delta-reindex fallback.

= 6.29.0 =
* Fix: reverted a regression in 6.28.0 that rewrote the sync queue from an out-of-date copy and removed WSS_Sync_Queue::add_wake_up(), which caused a fatal error when the Meilisearch connection recovered or a WP All Import finished. The complete queue is restored — request-end draining (which has existed since 6.19.0, flushing the response first so the caller is never blocked) plus the Action Scheduler / WP-Cron fallback, retries and error handling.
* Sync: added integration with "WooCommerce Advanced Bulk Edit" (WCABE, by WPMelon / Algol Plus). That plugin saves via direct SQL and bypasses the standard WooCommerce/WordPress hooks, so its edits were only reflected on the next scheduled reindex. The plugin now listens for its wcabe_after_bulk_save action and re-indexes the affected products in the same request; when the action does not pass a product-ID list it falls back to the delta reindex.

= 6.28.0 =
* Superseded by 6.29.0 — this release contained a sync-queue regression (fatal error on Meilisearch reconnect / WP All Import completion). Update to 6.29.0.

= 6.27.0 =
* Admin: added inline help next to the stock/visibility toggles so it is clear what each does — "Index Out of Stock" and "Index Hidden Products" (Indexing tab) explain that Off removes those products from the index, and "Show Out of Stock" (Results Page tab) explains it is a search-time filter that hides products without removing them from the index.

= 6.26.0 =
* Sync: products are now removed from the index the moment they stop qualifying, not just on the next full sync. The incremental sync only checked the publish status, so a product that went out of stock (with "Index out of stock" off), was hidden, or moved to an excluded category while still published was re-indexed instead of removed and lingered forever. It now applies the same rules as the full sync (published + in stock + not hidden + not in an excluded category) and deletes the product when it no longer qualifies (and re-adds it when it qualifies again, e.g. back in stock). Default behavior is unchanged: with "Index out of stock" on (the default) all products stay indexed as before. NOTE: to purge products that already lingered in the index, run Indexing > Clear Index once, then Full Sync.

= 6.25.0 =
* Fix (local engine): SKU/code fragment search ("551" for SKU "UTD55108") did not work when the local index had been configured before 6.19.0. The stored searchable-fields list still had "sku"/"all_skus" but not "search_codes", so a full re-index rebuilt the documents (with the field) but never tokenized it — the exact SKU matched via the "sku" field, but a fragment, which only lives in "search_codes", did not. The indexer now always includes the core identity/code fields (name, sku, all_skus, search_codes) regardless of the stored config, so the index config can no longer silently drop fragment search. IMPORTANT: run one Full Sync (Indexing > Full Sync) after updating so the fragments are tokenized into the index.

= 6.24.0 =
* Search: SKU/code fragments typed with their separator now match too (local engine). Codes are indexed in collapsed form ("abc1234"), so a fragment like "abc-12" or "0-123" previously did not match; the query now also tries a collapsed alphanumeric form of separator/number tokens. NOTE: fragment search for SKUs relies on the search_codes field introduced in 6.19.0 — existing products must be re-indexed once (Indexing > Full Sync) for any SKU fragment search to work.

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
