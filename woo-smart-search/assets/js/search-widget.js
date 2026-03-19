/**
 * Woo Smart Search - Frontend Search Widget (vanilla JS)
 * Supports standard, expanded (two-column), and compact layouts.
 * @package WooSmartSearch
 */
(function () {
	'use strict';

	var config = window.wssConfig || {};
	var cache = {};
	var popularCache = null;
	var activeController = null;

	function init() {
		var wrappers = document.querySelectorAll('.wss-search-wrapper');
		wrappers.forEach(function (wrapper) {
			initWidget(wrapper);
		});
	}

	function initWidget(wrapper) {
		// Detect layout from wrapper class (supports per-instance override).
		var isExpanded = wrapper.classList.contains('wss-layout-expanded');
		var isCompact = wrapper.classList.contains('wss-layout-compact');
		var layout = isExpanded ? 'expanded' : ( isCompact ? 'compact' : 'standard' );

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
		var selectedIndex = -1;
		var debounceTimer = null;
		var isMobileOverlay = false;
		var lastQuery = '';

		// Expanded layout DOM refs.
		var popularContainer = isExpanded ? wrapper.querySelector('.wss-popular-searches') : null;
		var popularList = isExpanded ? wrapper.querySelector('.wss-popular-list') : null;
		var sidebarCatContainer = isExpanded ? wrapper.querySelector('.wss-sidebar-categories') : null;
		var sidebarCatList = isExpanded ? wrapper.querySelector('.wss-sidebar-categories-list') : null;
		var sidebarPagesContainer = isExpanded ? wrapper.querySelector('.wss-sidebar-pages') : null;
		var sidebarPagesList = isExpanded ? wrapper.querySelector('.wss-sidebar-pages-list') : null;
		var suggestionsContainer = isExpanded ? wrapper.querySelector('.wss-suggestions') : null;
		var suggestionsList = isExpanded ? wrapper.querySelector('.wss-suggestions-list') : null;
		var mainHeading = isExpanded ? wrapper.querySelector('.wss-expanded-main-heading') : null;

		if (!input) return;

		// Input event with debounce.
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
			}, config.debounceTime || 200);
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

		// Focus - only re-show dropdown if there are already results from a previous search.
		input.addEventListener('focus', function () {
			if (input.value.trim().length >= (config.minQueryLength || 2) &&
				(productsContainer.children.length > 0 || (emptyContainer && emptyContainer.classList.contains('wss-visible')))) {
				showDropdown();
			}
		});

		/* ---- Expanded Layout: Popular Searches ---- */

		function loadPopularSearches() {
			if (!isExpanded || !popularContainer) return;

			if (popularCache) {
				renderPopularSearches(popularCache);
				return;
			}

			if (!config.popularUrl) return;

			fetch(config.popularUrl + '?limit=8', {
				headers: { 'X-WP-Nonce': config.nonce }
			})
				.then(function (r) { return r.json(); })
				.then(function (data) {
					popularCache = data.searches || [];
					renderPopularSearches(popularCache);
				})
				.catch(function () { /* ignore */ });
		}

		function renderPopularSearches(searches) {
			if (!popularList || !searches.length) {
				hideState(popularContainer);
				return;
			}

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
			if (!cats || typeof cats !== 'object') {
				hideState(sidebarCatContainer);
				return;
			}

			var heading = sidebarCatContainer.querySelector('.wss-sidebar-heading');
			if (heading) heading.textContent = config.i18n.categories || 'CATEGOR\u00cdAS';

			sidebarCatList.innerHTML = '';
			var entries = Object.entries(cats).sort(function (a, b) { return b[1] - a[1]; });
			var max = Math.min(entries.length, 8);
			for (var i = 0; i < max; i++) {
				var catName = decodeHtml(entries[i][0]);
				var catCount = entries[i][1];
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

			// Generate simple word suggestions from query.
			var words = query.split(/\s+/).filter(function (w) { return w.length > 1; });
			if (words.length < 1) {
				hideState(suggestionsContainer);
				return;
			}

			suggestionsList.innerHTML = '';
			// Show the original query plus truncated variations.
			var suggestions = [query];
			words.forEach(function (word) {
				if (word !== query && word.length > 2) {
					suggestions.push(word);
				}
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

		/* ---- Search ---- */

		function performSearch(query) {
			lastQuery = query;

			// Check local cache.
			if (cache[query]) {
				renderResults(cache[query], query);
				return;
			}

			showSkeleton();
			showDropdown();
			showLoading();

			// Cancel previous request.
			if (activeController) {
				activeController.abort();
			}
			activeController = new AbortController();

			var limit = config.maxResults || 8;
			var facetsParam = isExpanded ? '&facets=categories,stock_status,on_sale,brand,rating' : '';
			var url = config.apiUrl + '?q=' + encodeURIComponent(query) + '&limit=' + limit + facetsParam;

			fetch(url, {
				method: 'GET',
				headers: {
					'X-WP-Nonce': config.nonce
				},
				signal: activeController.signal
			})
				.then(function (response) {
					if (!response.ok) {
						throw new Error('HTTP ' + response.status);
					}
					return response.json();
				})
				.then(function (data) {
					cache[query] = data;
					renderResults(data, query);

					// Dispatch analytics event.
					try {
						var event = new CustomEvent('wss_search', {
							detail: { query: query, results: data.total || 0 }
						});
						document.dispatchEvent(event);
					} catch (err) { /* ignore */ }
				})
				.catch(function (err) {
					if (err.name === 'AbortError') return;
					showError();
				})
				.finally(function () {
					hideLoading();
					hideSkeleton();
				});
		}

		function renderResults(data, query) {
			selectedIndex = -1;
			productsContainer.innerHTML = '';
			hideSkeleton();
			hideState(emptyContainer);
			hideState(errorContainer);
			hideState(footer);
			hideState(categoriesContainer);

			var hits = data.hits || [];
			var total = data.total || 0;
			var facets = data.facets || {};

			// Expanded sidebar sections.
			if (isExpanded) {
				renderSidebarCategories(facets);
				renderSuggestions(query);
				// Hide popular during results.
				hideState(popularContainer);
				// Show PRODUCTOS heading above products grid.
				if (mainHeading) {
					mainHeading.textContent = config.i18n.products || 'PRODUCTOS';
				}
			}

			// Render category pills (standard layout only).
			if (!isExpanded && facets.categories && typeof facets.categories === 'object') {
				var catEntries = Object.entries(facets.categories);
				if (catEntries.length > 0) {
					renderCategories(catEntries, query);
				}
			}

			if (hits.length === 0) {
				showState(emptyContainer);
				emptyContainer.textContent = (config.i18n.noResults || 'No results found for') + ' \u201c' + query + '\u201d';
				if (isExpanded && mainHeading) hideState(mainHeading);
				showDropdown();
				return;
			}

			// Show the PRODUCTOS heading in expanded layout.
			if (isExpanded && mainHeading) showState(mainHeading);

			hits.forEach(function (hit, index) {
				var item = createResultItem(hit, index, query);
				productsContainer.appendChild(item);
			});

			// Always show "View all" footer when there are results.
			showState(footer);
			viewAllLink.href = getSearchPageUrl(query);
			if (total > hits.length) {
				viewAllLink.textContent = (config.i18n.viewAll || 'View all %d results').replace('%d', total) + ' \u2192';
			} else {
				viewAllLink.textContent = (config.i18n.viewAllResults || 'View all results') + ' \u2192';
			}

			showDropdown();
		}

		function renderCategories(catEntries, query) {
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

			// Image (hidden in compact layout).
			if (!isCompact && config.showImage !== false) {
				var imgSrc = hit.image || config.placeholderImg || '';
				html += '<div class="wss-result-image">';
				html += '<img src="' + escHtml(imgSrc) + '" alt="' + escHtml(hit.name || '') + '" width="60" height="60" loading="lazy" />';
				html += '</div>';
			}

			html += '<div class="wss-result-info">';

			// Category.
			if (!isCompact && config.showCategory !== false && hit.categories && hit.categories.length) {
				html += '<span class="wss-result-category">' + escHtml(decodeHtml(hit.categories[0])) + '</span>';
			}

			// Title with highlighting.
			var title = hit.name_highlighted ? decodeHtml(hit.name_highlighted) : escHtml(decodeHtml(hit.name || ''));
			html += '<h4 class="wss-result-title">' + title + '</h4>';

			// SKU.
			if (!isCompact && config.showSku && hit.sku) {
				html += '<span class="wss-result-sku">SKU: ' + escHtml(hit.sku) + '</span>';
			}

			// Price row.
			html += '<div class="wss-result-meta">';

			if (config.showPrice !== false && hit.price !== undefined) {
				html += '<div class="wss-result-price">';
				if (hit.on_sale && hit.regular_price) {
					html += '<span class="wss-price-regular">' + formatPrice(hit.regular_price) + '</span> ';
					html += '<span class="wss-price-current wss-price-sale">' + formatPrice(hit.price) + '</span>';
					if (!isCompact) {
						var discount = Math.round((1 - hit.price / hit.regular_price) * 100);
						if (discount > 0) {
							html += ' <span class="wss-sale-badge">-' + discount + '%</span>';
						}
					}
				} else if (hit.price_min && hit.price_max && hit.price_min !== hit.price_max) {
					html += '<span class="wss-price-current">' + formatPrice(hit.price_min) + ' &ndash; ' + formatPrice(hit.price_max) + '</span>';
				} else {
					html += '<span class="wss-price-current">' + formatPrice(hit.price) + '</span>';
				}
				html += '</div>';
			}

			// Stock indicator dot (not in compact).
			if (!isCompact && config.showStock !== false && hit.stock_status) {
				var stockClass = 'wss-stock-dot wss-stock-' + hit.stock_status;
				var stockText = config.i18n[hit.stock_status === 'instock' ? 'inStock' : (hit.stock_status === 'outofstock' ? 'outOfStock' : 'onBackorder')] || hit.stock_status;
				html += '<span class="' + stockClass + '" title="' + escHtml(stockText) + '"><span class="wss-stock-circle"></span>' + escHtml(stockText) + '</span>';
			}

			html += '</div>'; // .wss-result-meta

			// Rating (not in compact).
			if (!isCompact && config.showRating && hit.rating) {
				html += '<span class="wss-result-rating">';
				var fullStars = Math.floor(hit.rating);
				for (var i = 0; i < 5; i++) {
					html += i < fullStars ? '\u2605' : '\u2606';
				}
				if (hit.review_count) {
					html += ' <span class="wss-review-count">(' + hit.review_count + ')</span>';
				}
				html += '</span>';
			}

			html += '</div>'; // .wss-result-info

			a.innerHTML = html;

			// Hover handling.
			a.addEventListener('mouseenter', function () {
				selectedIndex = index;
				var items = dropdown.querySelectorAll('.wss-result-item');
				updateSelection(items);
			});

			// Click tracking.
			a.addEventListener('click', function () {
				trackClick(query, hit.id);
			});

			return a;
		}

		function trackClick(query, productId) {
			if (!config.trackClickUrl || !productId) return;

			try {
				fetch(config.trackClickUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': config.nonce
					},
					body: JSON.stringify({
						query: query,
						product_id: productId
					}),
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
			dropdown.classList.add('wss-visible');
			input.setAttribute('aria-expanded', 'true');

			// Mobile fullscreen overlay.
			if (window.innerWidth < 768) {
				isMobileOverlay = true;
				wrapper.classList.add('wss-mobile-open');
				document.body.classList.add('wss-body-locked');
				if (backdrop) backdrop.classList.add('wss-visible');
			}
		}

		function hideDropdown() {
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
			if (skeletonContainer) {
				skeletonContainer.classList.add('wss-visible');
			}
			productsContainer.innerHTML = '';
			hideState(emptyContainer);
			hideState(errorContainer);
			hideState(footer);
			hideState(categoriesContainer);
		}

		function hideSkeleton() {
			if (skeletonContainer) {
				skeletonContainer.classList.remove('wss-visible');
			}
		}

		function showLoading() {
			if (spinner) spinner.style.display = '';
			if (icon) icon.style.display = 'none';
		}

		function hideLoading() {
			if (spinner) spinner.style.display = 'none';
			if (!input.value.trim() && icon) {
				icon.style.display = '';
			}
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

		function showState(el) {
			if (el) el.classList.add('wss-visible');
		}

		function hideState(el) {
			if (el) el.classList.remove('wss-visible');
		}

		function showError() {
			productsContainer.innerHTML = '';
			hideSkeleton();
			hideState(emptyContainer);
			showState(errorContainer);
			errorContainer.textContent = config.i18n.error || 'Connection error, please try again';
			hideState(footer);
			showDropdown();
		}
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
			case 'left':
				return symbol + formatted;
			case 'right':
				return formatted + symbol;
			case 'left_space':
				return symbol + '\u00a0' + formatted;
			case 'right_space':
				return formatted + '\u00a0' + symbol;
			default:
				return symbol + formatted;
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

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
