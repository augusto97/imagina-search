/**
 * Woo Smart Search - Frontend Search Widget (vanilla JS)
 * Supports: standard, expanded, compact, amazon, falabella, fullscreen layouts.
 * @package WooSmartSearch
 */
(function () {
	'use strict';

	var config = window.wssConfig || {};
	var cache = {};
	var popularCache = null;
	var activeController = null;

	// Ultra-fast mode: direct Meilisearch search (bypasses WordPress entirely).
	var useDirect = !!(config.meiliUrl && config.meiliKey && config.meiliIndex);
	var meiliSearchUrl = useDirect
		? config.meiliUrl + '/indexes/' + encodeURIComponent(config.meiliIndex) + '/search'
		: '';

	/**
	 * Perform a direct Meilisearch POST search.
	 * Returns a promise that resolves to the normalized response format.
	 */
	function meiliSearch(query, limit, facets, signal) {
		var body = {
			q: query,
			limit: parseInt(limit, 10) || 8,
			attributesToHighlight: ['name'],
			highlightPreTag: '<mark>',
			highlightPostTag: '</mark>'
		};
		if (facets && facets.length) {
			body.facets = facets;
		}

		// Filter by content_source unless mixed mode.
		if (!config.isMixed && config.contentSource) {
			var sourceValue = config.isEcommerce ? 'woocommerce' : 'wordpress';
			body.filter = 'content_source = "' + sourceValue + '"';
		}

		return fetch(meiliSearchUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Authorization': 'Bearer ' + config.meiliKey
			},
			body: JSON.stringify(body),
			signal: signal
		})
		.then(function (res) {
			if (!res.ok) {
				return res.json().catch(function () { return {}; }).then(function (errBody) {
					console.error('WSS Meilisearch error:', errBody);
					throw new Error('Meili HTTP ' + res.status + ': ' + (errBody.message || ''));
				});
			}
			return res.json();
		})
		.then(function (data) {
			// Normalize to match the WP REST API response format.
			var hits = (data.hits || []).map(function (hit) {
				var formatted = hit._formatted || {};
				hit.name_highlighted = formatted.name || hit.name || '';
				return hit;
			});
			return {
				hits: hits,
				total: data.estimatedTotalHits || hits.length,
				facets: data.facetDistribution || {},
				processingTimeMs: data.processingTimeMs || 0
			};
		});
	}

	function init() {
		var wrappers = document.querySelectorAll('.wss-search-wrapper');
		wrappers.forEach(function (wrapper) {
			initWidget(wrapper);
		});
	}

	function initWidget(wrapper) {
		// Detect layout.
		var layout = 'standard';
		if (wrapper.classList.contains('wss-layout-expanded')) layout = 'expanded';
		else if (wrapper.classList.contains('wss-layout-compact')) layout = 'compact';
		else if (wrapper.classList.contains('wss-layout-amazon')) layout = 'amazon';
		else if (wrapper.classList.contains('wss-layout-falabella')) layout = 'falabella';
		else if (wrapper.classList.contains('wss-layout-fullscreen')) layout = 'fullscreen';

		var isExpanded = layout === 'expanded';
		var isCompact = layout === 'compact';
		var isAmazon = layout === 'amazon';
		var isFalabella = layout === 'falabella';
		var isFullscreen = layout === 'fullscreen';
		var needsFacets = isExpanded || isFalabella || isFullscreen;

		var input = wrapper.querySelector('.wss-search-input');
		var dropdown = wrapper.querySelector('.wss-results-dropdown');
		var productsContainer = wrapper.querySelector('.wss-results-products');
		var categoriesContainer = wrapper.querySelector('.wss-results-categories');
		var skeletonContainer = wrapper.querySelector('.wss-results-skeleton');
		var emptyContainer = wrapper.querySelector('.wss-results-empty');
		var errorContainer = wrapper.querySelector('.wss-results-error');
		var footer = wrapper.querySelector('.wss-results-footer');
		var viewAllLink = wrapper.querySelector('.wss-view-all');
		var spinner = wrapper.querySelector('.wss-search-spinner');
		var icon = wrapper.querySelector('.wss-search-icon');
		var clearBtn = wrapper.querySelector('.wss-search-clear');
		var backdrop = wrapper.querySelector('.wss-mobile-backdrop');
		var mobileCloseBtn = wrapper.querySelector('.wss-mobile-close-btn');
		var selectedIndex = -1;
		var debounceTimer = null;
		var isMobileOverlay = false;
		var lastQuery = '';

		// Expanded layout DOM refs.
		var popularContainer = isExpanded ? wrapper.querySelector('.wss-popular-searches') : null;
		var popularList = isExpanded ? wrapper.querySelector('.wss-popular-list') : null;
		var sidebarCatContainer = isExpanded ? wrapper.querySelector('.wss-sidebar-categories') : null;
		var sidebarCatList = isExpanded ? wrapper.querySelector('.wss-sidebar-categories-list') : null;
		var suggestionsContainer = isExpanded ? wrapper.querySelector('.wss-suggestions') : null;
		var suggestionsList = isExpanded ? wrapper.querySelector('.wss-suggestions-list') : null;
		var mainHeading = isExpanded ? wrapper.querySelector('.wss-expanded-main-heading') : null;

		// Falabella layout DOM refs.
		var falabellaBrandsList = isFalabella ? wrapper.querySelector('.wss-falabella-brands-list') : null;
		var falabellaCategoriesList = isFalabella ? wrapper.querySelector('.wss-falabella-categories-list') : null;

		// Fullscreen layout DOM refs.
		var fullscreenOverlay = isFullscreen ? wrapper.querySelector('.wss-fullscreen-overlay') : null;
		var fullscreenInput = isFullscreen ? wrapper.querySelector('.wss-fullscreen-input') : null;
		var fullscreenClose = isFullscreen ? wrapper.querySelector('.wss-fullscreen-close') : null;
		var fullscreenClear = isFullscreen ? wrapper.querySelector('.wss-fullscreen-clear') : null;
		var fullscreenViewAll = isFullscreen ? wrapper.querySelector('.wss-fullscreen-view-all') : null;
		var fullscreenCatList = isFullscreen ? wrapper.querySelector('.wss-fullscreen-categories-list') : null;
		var fullscreenBrandsList = isFullscreen ? wrapper.querySelector('.wss-fullscreen-brands-list') : null;

		if (!input) return;

		// Fullscreen: the main input triggers the overlay.
		if (isFullscreen) {
			input.addEventListener('focus', function () {
				openFullscreen();
			});
			input.addEventListener('click', function () {
				openFullscreen();
			});

			if (fullscreenInput) {
				fullscreenInput.addEventListener('input', function () {
					var query = fullscreenInput.value.trim();
					clearTimeout(debounceTimer);
					if (query.length < (config.minQueryLength || 2)) {
						clearFullscreenResults();
						toggleFullscreenClear(false);
						return;
					}
					toggleFullscreenClear(true);
					debounceTimer = setTimeout(function () {
						performSearch(query);
					}, config.debounceTime || 150);
				});

				fullscreenInput.addEventListener('keydown', function (e) {
					if (e.key === 'Escape') {
						closeFullscreen();
					} else if (e.key === 'Enter') {
						e.preventDefault();
						if (fullscreenInput.value.trim().length >= (config.minQueryLength || 2)) {
							window.location.href = getSearchPageUrl(fullscreenInput.value.trim());
						}
					}
				});
			}

			if (fullscreenClose) {
				fullscreenClose.addEventListener('click', closeFullscreen);
			}

			if (fullscreenClear) {
				fullscreenClear.addEventListener('click', function () {
					if (fullscreenInput) {
						fullscreenInput.value = '';
						clearFullscreenResults();
						toggleFullscreenClear(false);
						fullscreenInput.focus();
					}
				});
			}

			// No further event binding needed for fullscreen — skip the rest.
		}

		// Non-fullscreen: input events.
		if (!isFullscreen) {
			input.addEventListener('input', function () {
				var query = input.value.trim();
				clearTimeout(debounceTimer);
				if (query.length < (config.minQueryLength || 2)) {
					hideDropdown();
					toggleClear(false);
					return;
				}
				toggleClear(true);
				debounceTimer = setTimeout(function () {
					performSearch(query);
				}, config.debounceTime || 150);
			});

			// Clear button.
			if (clearBtn) {
				clearBtn.addEventListener('click', function () {
					input.value = '';
					hideDropdown();
					toggleClear(false);
					input.focus();
				});
			}

			// Keyboard navigation.
			input.addEventListener('keydown', function (e) {
				if (!dropdown) return;
				var items = dropdown.querySelectorAll('.wss-result-item');
				switch (e.key) {
					case 'ArrowDown':
						e.preventDefault();
						if (!dropdown.classList.contains('wss-visible')) return;
						selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
						updateSelection(items);
						break;
					case 'ArrowUp':
						e.preventDefault();
						if (!dropdown.classList.contains('wss-visible')) return;
						selectedIndex = Math.max(selectedIndex - 1, -1);
						updateSelection(items);
						break;
					case 'Enter':
						e.preventDefault();
						if (selectedIndex >= 0 && items[selectedIndex]) {
							var href = items[selectedIndex].getAttribute('href');
							if (href) window.location.href = href;
						} else if (input.value.trim().length >= (config.minQueryLength || 2)) {
							window.location.href = getSearchPageUrl(input.value.trim());
						}
						break;
					case 'Escape':
						hideDropdown();
						input.blur();
						break;
					case 'Tab':
						hideDropdown();
						break;
				}
			});

			// Close dropdown on click outside.
			document.addEventListener('click', function (e) {
				if (!wrapper.contains(e.target)) {
					hideDropdown();
				}
			});

			// Mobile backdrop close.
			if (backdrop) {
				backdrop.addEventListener('click', function () {
					hideDropdown();
				});
			}

			// Mobile close button.
			if (mobileCloseBtn) {
				mobileCloseBtn.addEventListener('click', function () {
					hideDropdown();
				});
			}

			// Focus - only re-show dropdown if there are already results from a previous search.
			input.addEventListener('focus', function () {
				if (input.value.trim().length >= (config.minQueryLength || 2) &&
					(productsContainer.children.length > 0 || (emptyContainer && emptyContainer.classList.contains('wss-visible')))) {
					showDropdown();
				}
			});
		}

		/* ---- Expanded Layout: Popular Searches ---- */

		function loadPopularSearches() {
			if (!isExpanded || !popularContainer) return;
			if (popularCache) { renderPopularSearches(popularCache); return; }
			if (!config.popularUrl) return;
			fetch(config.popularUrl + '?limit=8', { headers: { 'X-WP-Nonce': config.nonce } })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					popularCache = data.searches || [];
					renderPopularSearches(popularCache);
				})
				.catch(function () { /* ignore */ });
		}

		function renderPopularSearches(searches) {
			if (!popularList || !searches.length) { hideState(popularContainer); return; }
			var heading = popularContainer.querySelector('.wss-sidebar-heading');
			if (heading) heading.textContent = config.i18n.popularSearches || 'POPULAR';
			popularList.innerHTML = '';
			searches.forEach(function (item) {
				var li = document.createElement('li');
				var a = document.createElement('a');
				a.href = getSearchPageUrl(item.query);
				a.className = 'wss-popular-item';
				a.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> ' + escHtml(item.query);
				a.addEventListener('click', function (e) {
					e.preventDefault();
					input.value = item.query;
					input.dispatchEvent(new Event('input'));
				});
				li.appendChild(a);
				popularList.appendChild(li);
			});
			showState(popularContainer);
		}

		function renderSidebarCategories(facets) {
			if (!isExpanded || !sidebarCatContainer || !sidebarCatList) return;
			var cats = facets.categories;
			if (!cats || typeof cats !== 'object') { hideState(sidebarCatContainer); return; }
			var heading = sidebarCatContainer.querySelector('.wss-sidebar-heading');
			if (heading) heading.textContent = config.i18n.categories || 'CATEGORIES';
			sidebarCatList.innerHTML = '';
			var entries = Object.entries(cats).sort(function (a, b) { return b[1] - a[1]; });
			var max = Math.min(entries.length, 8);
			for (var i = 0; i < max; i++) {
				var catName = decodeHtml(entries[i][0]);
				var li = document.createElement('li');
				var a = document.createElement('a');
				a.href = getSearchPageUrl(lastQuery + '&filter_categories=' + encodeURIComponent(catName));
				a.className = 'wss-sidebar-cat-item';
				a.textContent = catName;
				li.appendChild(a);
				sidebarCatList.appendChild(li);
			}
			showState(sidebarCatContainer);
		}

		function renderSuggestions(query) {
			if (!isExpanded || !suggestionsContainer || !suggestionsList) return;
			var heading = suggestionsContainer.querySelector('.wss-sidebar-heading');
			if (heading) heading.textContent = config.i18n.suggestions || 'Suggestions';
			var words = query.split(/\s+/).filter(function (w) { return w.length > 1; });
			if (words.length < 1) { hideState(suggestionsContainer); return; }
			suggestionsList.innerHTML = '';
			var suggestions = [query];
			words.forEach(function (word) {
				if (word !== query && word.length > 2) suggestions.push(word);
			});
			suggestions = suggestions.slice(0, 5);
			suggestions.forEach(function (s) {
				var li = document.createElement('li');
				var a = document.createElement('a');
				a.href = '#';
				a.className = 'wss-suggestion-item';
				a.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> ' + escHtml(s);
				a.addEventListener('click', function (e) {
					e.preventDefault();
					input.value = s;
					input.dispatchEvent(new Event('input'));
				});
				li.appendChild(a);
				suggestionsList.appendChild(li);
			});
			showState(suggestionsContainer);
		}

		/* ---- Falabella: Render columns ---- */

		function renderFalabellaColumns(facets) {
			if (!isFalabella) return;

			// Brands column (or tags for WP content mode).
			if (falabellaBrandsList) {
				falabellaBrandsList.innerHTML = '';
				var brandData = facets.brand || facets.tags;
				var brandFilterKey = facets.brand ? 'brand' : 'tags';
				// Update heading text based on data source.
				var brandHeading = falabellaBrandsList.closest('.wss-falabella-col');
				if (brandHeading) {
					var headingEl = brandHeading.querySelector('.wss-column-heading');
					if (headingEl) headingEl.textContent = facets.brand ? (config.i18n.relatedBrands || 'Related Brands') : (config.i18n.relatedTags || 'Related Tags');
				}
				if (brandData && typeof brandData === 'object') {
					var entries = Object.entries(brandData).sort(function (a, b) { return b[1] - a[1]; });
					entries.slice(0, 10).forEach(function (entry) {
						var li = document.createElement('li');
						var a = document.createElement('a');
						a.href = getSearchPageUrl(lastQuery + '&filter_' + brandFilterKey + '=' + encodeURIComponent(entry[0]));
						a.textContent = decodeHtml(entry[0]);
						li.appendChild(a);
						falabellaBrandsList.appendChild(li);
					});
				}
			}

			// Categories column.
			if (falabellaCategoriesList) {
				falabellaCategoriesList.innerHTML = '';
				var cats = facets.categories;
				if (cats && typeof cats === 'object') {
					var catEntries = Object.entries(cats).sort(function (a, b) { return b[1] - a[1]; });
					catEntries.slice(0, 10).forEach(function (entry) {
						var li = document.createElement('li');
						var a = document.createElement('a');
						a.href = getSearchPageUrl(lastQuery + '&filter_categories=' + encodeURIComponent(entry[0]));
						a.textContent = decodeHtml(entry[0]);
						li.appendChild(a);
						falabellaCategoriesList.appendChild(li);
					});
				}
			}
		}

		/* ---- Fullscreen: Render columns ---- */

		function renderFullscreenColumns(facets, query) {
			if (!isFullscreen) return;

			// Categories column.
			if (fullscreenCatList) {
				fullscreenCatList.innerHTML = '';
				var cats = facets.categories;
				if (cats && typeof cats === 'object') {
					var catEntries = Object.entries(cats).sort(function (a, b) { return b[1] - a[1]; });
					catEntries.slice(0, 8).forEach(function (entry) {
						var catName = decodeHtml(entry[0]);
						var count = entry[1];
						var li = document.createElement('li');
						var a = document.createElement('a');
						a.href = getSearchPageUrl(query + '&filter_categories=' + encodeURIComponent(catName));
						a.innerHTML = '<span class="wss-fullscreen-cat-name">' + escHtml(catName) + '</span>' +
							'<span class="wss-fullscreen-cat-count">' + count + ' articles</span>';
						li.appendChild(a);
						fullscreenCatList.appendChild(li);
					});
				}
			}

			// Brands column.
			if (fullscreenBrandsList) {
				fullscreenBrandsList.innerHTML = '';
				var brands = facets.brand;
				if (brands && typeof brands === 'object') {
					var brandEntries = Object.entries(brands).sort(function (a, b) { return b[1] - a[1]; });
					brandEntries.slice(0, 8).forEach(function (entry) {
						var li = document.createElement('li');
						var a = document.createElement('a');
						a.href = getSearchPageUrl(lastQuery + '&filter_brand=' + encodeURIComponent(entry[0]));
						a.textContent = decodeHtml(entry[0]);
						li.appendChild(a);
						fullscreenBrandsList.appendChild(li);
					});
				}
			}

			// View all link.
			if (fullscreenViewAll) {
				fullscreenViewAll.href = getSearchPageUrl(query);
			}
		}

		function openFullscreen() {
			if (fullscreenOverlay) {
				fullscreenOverlay.classList.add('wss-visible');
				document.body.classList.add('wss-body-locked');
				if (fullscreenInput) {
					fullscreenInput.value = input.value;
					setTimeout(function () { fullscreenInput.focus(); }, 100);
				}
			}
		}

		function closeFullscreen() {
			if (fullscreenOverlay) {
				fullscreenOverlay.classList.remove('wss-visible');
				document.body.classList.remove('wss-body-locked');
				if (fullscreenInput) input.value = fullscreenInput.value;
			}
		}

		function clearFullscreenResults() {
			if (productsContainer) productsContainer.innerHTML = '';
			if (fullscreenCatList) fullscreenCatList.innerHTML = '';
			if (fullscreenBrandsList) fullscreenBrandsList.innerHTML = '';
			hideState(emptyContainer);
			hideState(errorContainer);
		}

		function toggleFullscreenClear(show) {
			if (fullscreenClear) fullscreenClear.style.display = show ? '' : 'none';
		}

		/* ---- Search ---- */

		function performSearch(query) {
			lastQuery = query;

			if (cache[query]) {
				renderResults(cache[query], query);
				return;
			}

			if (!isFullscreen) {
				showDropdown();
			}
			showLoading();

			if (activeController) activeController.abort();
			activeController = new AbortController();

			var limit = config.maxResults || 8;
			var defaultFacets = config.isEcommerce || config.isMixed
				? ['categories', 'tags', 'stock_status', 'on_sale', 'brand', 'rating']
				: ['categories', 'tags', 'post_type', 'author'];
			var facets = needsFacets ? (config.meilieFacets || defaultFacets) : null;
			var searchPromise;

			function wpFallbackSearch(q, lim, sig) {
				var defaultFacetStr = config.isEcommerce || config.isMixed
					? 'categories,tags,stock_status,on_sale,brand,rating'
					: 'categories,tags,post_type,author';
				var facetsParam = needsFacets ? '&facets=' + defaultFacetStr : '';
				var fallbackUrl = config.apiUrl + '?q=' + encodeURIComponent(q) + '&limit=' + lim + facetsParam;
				return fetch(fallbackUrl, {
					method: 'GET',
					headers: { 'X-WP-Nonce': config.nonce },
					signal: sig
				})
				.then(function (response) {
					if (!response.ok) throw new Error('HTTP ' + response.status);
					return response.json();
				});
			}

			if (useDirect) {
				// Ultra-fast: direct Meilisearch POST, with automatic WP fallback on failure.
				searchPromise = meiliSearch(query, limit, facets, activeController.signal)
					.catch(function (err) {
						if (err.name === 'AbortError') throw err;
						console.warn('WSS: Direct Meilisearch failed, falling back to WP REST API', err.message);
						return wpFallbackSearch(query, limit, activeController.signal);
					});
			} else {
				searchPromise = wpFallbackSearch(query, limit, activeController.signal);
			}

			searchPromise
				.then(function (data) {
					cache[query] = data;
					prefetchImages(data.hits || []);
					renderResults(data, query);
					trackSearch(query, data.total || 0);
					try {
						document.dispatchEvent(new CustomEvent('wss_search', {
							detail: { query: query, results: data.total || 0 }
						}));
					} catch (err) { /* ignore */ }
				})
				.catch(function (err) {
					if (err.name === 'AbortError') return;
					showError();
				})
				.finally(function () {
					hideLoading();
					if (!isFullscreen) hideSkeleton();
				});
		}

		function renderResults(data, query) {
			selectedIndex = -1;
			productsContainer.innerHTML = '';
			if (!isFullscreen) {
				hideSkeleton();
				hideState(emptyContainer);
				hideState(errorContainer);
				hideState(footer);
				hideState(categoriesContainer);
			} else {
				hideState(emptyContainer);
				hideState(errorContainer);
			}

			var hits = data.hits || [];
			var total = data.total || 0;
			var facets = data.facets || {};

			// Layout-specific sidebar/column rendering.
			if (isExpanded) {
				renderSidebarCategories(facets);
				renderSuggestions(query);
				hideState(popularContainer);
				if (mainHeading) {
				if (config.isEcommerce) {
					mainHeading.textContent = config.i18n.products || 'PRODUCTS';
				} else if (config.isMixed) {
					mainHeading.textContent = config.i18n.results || 'RESULTS';
				} else {
					mainHeading.textContent = config.i18n.content || 'CONTENT';
				}
			}
			}

			if (isFalabella) {
				renderFalabellaColumns(facets);
			}

			if (isFullscreen) {
				renderFullscreenColumns(facets, query);
			}

			// Standard layout: category pills.
			if (!isExpanded && !isFalabella && !isFullscreen && !isAmazon && facets.categories && typeof facets.categories === 'object') {
				var catEntries = Object.entries(facets.categories);
				if (catEntries.length > 0) renderCategories(catEntries, query);
			}

			if (hits.length === 0) {
				showState(emptyContainer);
				emptyContainer.textContent = (config.i18n.noResults || 'No results found for') + ' \u201c' + query + '\u201d';
				if (isExpanded && mainHeading) hideState(mainHeading);
				if (!isFullscreen) showDropdown();
				return;
			}

			if (isExpanded && mainHeading) showState(mainHeading);

			// In mixed mode, group results by content_source with section headers.
			if (config.isMixed && !isAmazon && !isFalabella) {
				var productHits = [];
				var contentHits = [];
				hits.forEach(function (hit) {
					if (hit.content_source === 'woocommerce') {
						productHits.push(hit);
					} else {
						contentHits.push(hit);
					}
				});

				var globalIdx = 0;

				if (productHits.length) {
					var prodHeader = document.createElement('div');
					prodHeader.className = 'wss-mixed-dropdown-header';
					prodHeader.textContent = config.i18n.products || 'Products';
					productsContainer.appendChild(prodHeader);
					productHits.forEach(function (hit) {
						var item = createResultItem(hit, globalIdx++, query);
						productsContainer.appendChild(item);
					});
				}

				if (contentHits.length) {
					var contentHeader = document.createElement('div');
					contentHeader.className = 'wss-mixed-dropdown-header';
					contentHeader.textContent = config.i18n.content || 'Content';
					productsContainer.appendChild(contentHeader);
					contentHits.forEach(function (hit) {
						var item = createResultItem(hit, globalIdx++, query);
						productsContainer.appendChild(item);
					});
				}
			} else {
				hits.forEach(function (hit, index) {
					var item = createResultItem(hit, index, query);
					productsContainer.appendChild(item);
				});
			}

			// Footer (non-fullscreen).
			if (!isFullscreen && footer) {
				showState(footer);
				viewAllLink.href = getSearchPageUrl(query);
				if (total > hits.length) {
					viewAllLink.textContent = (config.i18n.viewAll || 'View all %d results').replace('%d', total) + ' \u2192';
				} else {
					viewAllLink.textContent = (config.i18n.viewAllResults || 'View all results') + ' \u2192';
				}
			}

			if (!isFullscreen) showDropdown();
		}

		function renderCategories(catEntries, query) {
			if (!categoriesContainer) return;
			categoriesContainer.innerHTML = '';
			var label = document.createElement('span');
			label.className = 'wss-categories-label';
			label.textContent = config.i18n.categories || 'Categories';
			categoriesContainer.appendChild(label);

			var pills = document.createElement('div');
			pills.className = 'wss-categories-pills';
			var max = Math.min(catEntries.length, 5);
			for (var i = 0; i < max; i++) {
				var catName = decodeHtml(catEntries[i][0]);
				var catCount = catEntries[i][1];
				var pill = document.createElement('a');
				pill.className = 'wss-category-pill';
				pill.href = getSearchPageUrl(query + '&filter_categories=' + encodeURIComponent(catName));
				pill.textContent = catName + (catCount ? ' (' + catCount + ')' : '');
				pills.appendChild(pill);
			}
			categoriesContainer.appendChild(pills);
			showState(categoriesContainer);
		}

		function createResultItem(hit, index, query) {
			var a = document.createElement('a');
			a.href = hit.permalink || '#';
			a.className = 'wss-result-item';
			a.setAttribute('role', 'option');
			a.setAttribute('aria-selected', 'false');

			var html = '';

			// Amazon layout: search icon + text only.
			if (isAmazon) {
				html += '<span class="wss-amazon-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>';
				html += '<div class="wss-result-info">';
				var amazonTitle = hit.name_highlighted ? sanitizeHighlight(hit.name_highlighted) : escHtml(decodeHtml(hit.name || ''));
				html += '<h4 class="wss-result-title">' + amazonTitle + '</h4>';
				html += '</div>';
				a.innerHTML = html;
				a.addEventListener('mouseenter', function () { selectedIndex = index; updateSelection(dropdown.querySelectorAll('.wss-result-item')); });
				a.addEventListener('click', function () { trackClick(query, hit.id); });
				return a;
			}

			// Falabella layout: text only (no icon since it's in a suggestions column).
			if (isFalabella) {
				html += '<div class="wss-result-info">';
				var falTitle = hit.name_highlighted ? sanitizeHighlight(hit.name_highlighted) : escHtml(decodeHtml(hit.name || ''));
				html += '<h4 class="wss-result-title">' + falTitle + '</h4>';
				html += '</div>';
				a.innerHTML = html;
				a.addEventListener('mouseenter', function () { selectedIndex = index; updateSelection(dropdown.querySelectorAll('.wss-result-item')); });
				a.addEventListener('click', function () { trackClick(query, hit.id); });
				return a;
			}

			// Image (hidden in compact layout).
			if (!isCompact && config.showImage !== false) {
				var imgSrc = hit.image || config.placeholderImg || '';
				html += '<div class="wss-result-image">';
				html += '<img src="' + escHtml(imgSrc) + '" alt="' + escHtml(hit.name || '') + '" width="60" height="60" />';
				html += '</div>';
			}

			html += '<div class="wss-result-info">';

			// Category.
			if (!isCompact && config.showCategory !== false && hit.categories && hit.categories.length) {
				html += '<span class="wss-result-category">' + escHtml(decodeHtml(hit.categories[0])) + '</span>';
			}

			// Title with highlighting.
			var title = hit.name_highlighted ? sanitizeHighlight(hit.name_highlighted) : escHtml(decodeHtml(hit.name || ''));
			html += '<h4 class="wss-result-title">' + title + '</h4>';

			// Detect if this hit is WordPress content (vs WooCommerce product).
			var hitIsWpContent = hit.content_source === 'wordpress' || (!config.isEcommerce && !config.isMixed && typeof hit.price === 'undefined');

			if (hitIsWpContent) {
				// WordPress content: show excerpt, author, date.
				if (!isCompact && config.showExcerpt !== false && hit.description) {
					html += '<span class="wss-result-excerpt">' + escHtml(hit.description).substring(0, 80) + '</span>';
				}
				html += '<div class="wss-result-meta">';
				var wpMeta = [];
				if (config.showAuthor !== false && hit.author) wpMeta.push(escHtml(hit.author));
				if (config.showDate !== false && hit.date_created) {
					var d = new Date(hit.date_created * 1000);
					wpMeta.push(d.toLocaleDateString());
				}
				if (config.showPostType && hit.post_type && hit.post_type !== 'post') {
					wpMeta.push('<em>' + escHtml(hit.post_type) + '</em>');
				}
				if (wpMeta.length) html += '<span class="wss-result-wp-meta">' + wpMeta.join(' &middot; ') + '</span>';
				html += '</div>';
			} else {
				// WooCommerce product: show SKU, price, stock, rating.
				if (!isCompact && config.showSku && hit.sku) {
					html += '<span class="wss-result-sku">SKU: ' + escHtml(hit.sku) + '</span>';
				}

				html += '<div class="wss-result-meta">';
				if (config.showPrice !== false && hit.price !== undefined) {
					html += '<div class="wss-result-price">';
					if (hit.on_sale && hit.regular_price) {
						html += '<span class="wss-price-regular">' + formatPrice(hit.regular_price) + '</span> ';
						html += '<span class="wss-price-current wss-price-sale">' + formatPrice(hit.price) + '</span>';
						if (!isCompact) {
							var discount = Math.round((1 - hit.price / hit.regular_price) * 100);
							if (discount > 0) html += ' <span class="wss-sale-badge">-' + discount + '%</span>';
						}
					} else if (hit.price_min && hit.price_max && hit.price_min !== hit.price_max) {
						html += '<span class="wss-price-current">' + formatPrice(hit.price_min) + ' &ndash; ' + formatPrice(hit.price_max) + '</span>';
					} else {
						html += '<span class="wss-price-current">' + formatPrice(hit.price) + '</span>';
					}
					html += '</div>';
				}

				if (!isCompact && config.showStock !== false && hit.stock_status) {
					var stockClass = 'wss-stock-dot wss-stock-' + hit.stock_status;
					var stockText = config.i18n[hit.stock_status === 'instock' ? 'inStock' : (hit.stock_status === 'outofstock' ? 'outOfStock' : 'onBackorder')] || hit.stock_status;
					html += '<span class="' + stockClass + '" title="' + escHtml(stockText) + '"><span class="wss-stock-circle"></span>' + escHtml(stockText) + '</span>';
				}
				html += '</div>'; // .wss-result-meta

				if (!isCompact && config.showRating && hit.rating) {
					html += '<span class="wss-result-rating">';
					var fullStars = Math.floor(hit.rating);
					for (var i = 0; i < 5; i++) html += i < fullStars ? '\u2605' : '\u2606';
					if (hit.review_count) html += ' <span class="wss-review-count">(' + hit.review_count + ')</span>';
					html += '</span>';
				}
			}
			html += '</div>'; // .wss-result-info

			a.innerHTML = html;
			a.addEventListener('mouseenter', function () {
				selectedIndex = index;
				var items = (dropdown || productsContainer.parentElement).querySelectorAll('.wss-result-item');
				updateSelection(items);
			});
			a.addEventListener('click', function () { trackClick(query, hit.id); });
			return a;
		}

		function trackClick(query, productId) {
			if (!config.trackClickUrl || !productId) return;
			try {
				fetch(config.trackClickUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
					body: JSON.stringify({ query: query, product_id: productId }),
					keepalive: true
				});
			} catch (err) { /* silent fail */ }
		}

		function updateSelection(items) {
			items.forEach(function (item, i) {
				if (i === selectedIndex) {
					item.classList.add('wss-result-active');
					item.setAttribute('aria-selected', 'true');
					item.scrollIntoView({ block: 'nearest' });
				} else {
					item.classList.remove('wss-result-active');
					item.setAttribute('aria-selected', 'false');
				}
			});
		}

		function showDropdown() {
			if (!dropdown) return;
			dropdown.classList.add('wss-visible');
			input.setAttribute('aria-expanded', 'true');
			if (window.innerWidth < 768) {
				isMobileOverlay = true;
				wrapper.classList.add('wss-mobile-open');
				document.body.classList.add('wss-body-locked');
				if (backdrop) backdrop.classList.add('wss-visible');
			}
		}

		function hideDropdown() {
			if (!dropdown) return;
			dropdown.classList.remove('wss-visible');
			input.setAttribute('aria-expanded', 'false');
			selectedIndex = -1;
			if (isMobileOverlay) {
				isMobileOverlay = false;
				wrapper.classList.remove('wss-mobile-open');
				document.body.classList.remove('wss-body-locked');
				if (backdrop) backdrop.classList.remove('wss-visible');
			}
		}

		function showSkeleton() {
			if (skeletonContainer) skeletonContainer.classList.add('wss-visible');
			if (productsContainer) productsContainer.innerHTML = '';
			hideState(emptyContainer);
			hideState(errorContainer);
			hideState(footer);
			hideState(categoriesContainer);
		}

		function hideSkeleton() {
			if (skeletonContainer) skeletonContainer.classList.remove('wss-visible');
		}

		function showLoading() {
			if (spinner) spinner.style.display = '';
			if (icon) icon.style.display = 'none';
		}

		function hideLoading() {
			if (spinner) spinner.style.display = 'none';
			var activeInput = isFullscreen ? fullscreenInput : input;
			if (!activeInput.value.trim() && icon) icon.style.display = '';
		}

		function toggleClear(show) {
			if (show) {
				if (clearBtn) clearBtn.style.display = '';
				if (icon) icon.style.display = 'none';
			} else {
				if (clearBtn) clearBtn.style.display = 'none';
				if (icon) icon.style.display = '';
			}
		}

		function showState(el) { if (el) el.classList.add('wss-visible'); }
		function hideState(el) { if (el) el.classList.remove('wss-visible'); }

		function showError() {
			if (productsContainer) productsContainer.innerHTML = '';
			if (!isFullscreen) hideSkeleton();
			hideState(emptyContainer);
			showState(errorContainer);
			if (errorContainer) errorContainer.textContent = config.i18n.error || 'Connection error, please try again';
			hideState(footer);
			if (!isFullscreen) showDropdown();
		}
	}

	/**
	 * Track search query for analytics via non-blocking beacon.
	 * Only sends data in direct Meilisearch mode (WP proxy tracks automatically).
	 */
	function trackSearch(query, totalResults) {
		if (!useDirect || !config.trackClickUrl) return;
		try {
			var data = JSON.stringify({ query: query, total: totalResults });
			if (navigator.sendBeacon) {
				navigator.sendBeacon(
					config.apiUrl.replace('/search', '/track-search'),
					new Blob([data], { type: 'application/json' })
				);
			}
		} catch (err) { /* silent fail */ }
	}

	function formatPrice(price) {
		if (price === undefined || price === null) return '';
		var decimals = config.decimals !== undefined ? config.decimals : 2;
		var decSep = config.decimalSep || '.';
		var thousandSep = config.thousandSep || ',';
		var symbol = config.currencySymbol || '$';
		var pos = config.currencyPos || 'left';
		var number = parseFloat(price).toFixed(decimals);
		var parts = number.split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
		var formatted = parts.join(decSep);
		switch (pos) {
			case 'left': return symbol + formatted;
			case 'right': return formatted + symbol;
			case 'left_space': return symbol + '\u00a0' + formatted;
			case 'right_space': return formatted + '\u00a0' + symbol;
			default: return symbol + formatted;
		}
	}

	/**
	 * Prefetch product images so they appear instantly when results render.
	 */
	function prefetchImages(hits) {
		for (var i = 0; i < hits.length; i++) {
			var src = hits[i].image;
			if (src) {
				var img = new Image();
				img.src = src;
			}
		}
	}

	function getSearchPageUrl(query) {
		var url = config.searchUrl || '/?s={query}&post_type=product';
		return url.replace('{query}', encodeURIComponent(query));
	}

	function escHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	function decodeHtml(str) {
		var txt = document.createElement('textarea');
		txt.innerHTML = str;
		return txt.value;
	}

	/**
	 * Sanitize highlighted HTML from Meilisearch.
	 * Only allows <mark> tags; all other HTML is escaped.
	 */
	function sanitizeHighlight(str) {
		if (!str) return '';
		// Decode HTML entities first.
		var decoded = decodeHtml(str);
		// Extract <mark>...</mark> segments, escape everything else.
		var parts = decoded.split(/(<mark>[\s\S]*?<\/mark>)/gi);
		var result = '';
		for (var i = 0; i < parts.length; i++) {
			if (/^<mark>/i.test(parts[i])) {
				// Extract inner content, escape it, then re-wrap in <mark>.
				var inner = parts[i].replace(/<\/?mark>/gi, '');
				result += '<mark>' + escHtml(inner) + '</mark>';
			} else {
				result += escHtml(parts[i]);
			}
		}
		return result;
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
