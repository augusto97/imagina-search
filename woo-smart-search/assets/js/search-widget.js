/**
 * Woo Smart Search - Frontend Search Widget
 *
 * Vanilla JS, no jQuery dependency.
 * Handles instant search with debounce, keyboard navigation,
 * and accessible dropdown results.
 *
 * @package WooSmartSearch
 */
(function () {
	'use strict';

	var config = window.wssConfig || {};
	var cache = {};
	var activeController = null;

	/**
	 * Initialize all search widgets on the page.
	 */
	function init() {
		var wrappers = document.querySelectorAll('.wss-search-wrapper');
		wrappers.forEach(function (wrapper) {
			initWidget(wrapper);
		});
	}

	/**
	 * Initialize a single search widget.
	 *
	 * @param {HTMLElement} wrapper The widget wrapper element.
	 */
	function initWidget(wrapper) {
		var input = wrapper.querySelector('.wss-search-input');
		var dropdown = wrapper.querySelector('.wss-results-dropdown');
		var productsContainer = wrapper.querySelector('.wss-results-products');
		var emptyContainer = wrapper.querySelector('.wss-results-empty');
		var errorContainer = wrapper.querySelector('.wss-results-error');
		var footer = wrapper.querySelector('.wss-results-footer');
		var viewAllLink = wrapper.querySelector('.wss-view-all');
		var spinner = wrapper.querySelector('.wss-search-spinner');
		var icon = wrapper.querySelector('.wss-search-icon');
		var clearBtn = wrapper.querySelector('.wss-search-clear');
		var selectedIndex = -1;
		var debounceTimer = null;

		if (!input) return;

		// Input event with debounce.
		input.addEventListener('input', function () {
			var query = input.value.trim();

			clearTimeout(debounceTimer);

			if (query.length < (config.minQueryLength || 2)) {
				hideDropdown();
				clearBtn.style.display = 'none';
				icon.style.display = '';
				return;
			}

			clearBtn.style.display = '';
			icon.style.display = 'none';

			debounceTimer = setTimeout(function () {
				performSearch(query);
			}, config.debounceTime || 200);
		});

		// Clear button.
		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				input.value = '';
				hideDropdown();
				clearBtn.style.display = 'none';
				icon.style.display = '';
				input.focus();
			});
		}

		// Keyboard navigation.
		input.addEventListener('keydown', function (e) {
			var items = dropdown.querySelectorAll('.wss-result-item');

			switch (e.key) {
				case 'ArrowDown':
					e.preventDefault();
					if (dropdown.style.display === 'none') return;
					selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
					updateSelection(items);
					break;
				case 'ArrowUp':
					e.preventDefault();
					if (dropdown.style.display === 'none') return;
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

		// Focus — show dropdown if has content.
		input.addEventListener('focus', function () {
			if (productsContainer.children.length > 0 || emptyContainer.style.display !== 'none') {
				showDropdown();
			}
		});

		/**
		 * Perform the search request.
		 *
		 * @param {string} query Search query.
		 */
		function performSearch(query) {
			// Check local cache.
			if (cache[query]) {
				renderResults(cache[query], query);
				return;
			}

			showLoading();

			// Cancel previous request.
			if (activeController) {
				activeController.abort();
			}
			activeController = new AbortController();

			var url = config.apiUrl + '?q=' + encodeURIComponent(query) + '&limit=' + (config.maxResults || 8);

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
				});
		}

		/**
		 * Render search results in the dropdown.
		 *
		 * @param {Object} data  API response.
		 * @param {string} query The search query.
		 */
		function renderResults(data, query) {
			selectedIndex = -1;
			productsContainer.innerHTML = '';
			emptyContainer.style.display = 'none';
			errorContainer.style.display = 'none';
			footer.style.display = 'none';

			var hits = data.hits || [];
			var total = data.total || 0;

			if (hits.length === 0) {
				emptyContainer.style.display = '';
				emptyContainer.textContent = (config.i18n.noResults || 'No results found for') + ' "' + query + '"';
				showDropdown();
				return;
			}

			hits.forEach(function (hit, index) {
				var item = createResultItem(hit, index);
				productsContainer.appendChild(item);
			});

			if (total > hits.length) {
				footer.style.display = '';
				viewAllLink.href = getSearchPageUrl(query);
				viewAllLink.textContent = (config.i18n.viewAll || 'View all %d results').replace('%d', total) + ' \u2192';
			}

			showDropdown();
		}

		/**
		 * Create a single result item element.
		 *
		 * @param {Object} hit   The result hit.
		 * @param {number} index The result index.
		 * @return {HTMLElement}
		 */
		function createResultItem(hit, index) {
			var a = document.createElement('a');
			a.href = hit.permalink || '#';
			a.className = 'wss-result-item';
			a.setAttribute('role', 'option');
			a.setAttribute('aria-selected', 'false');

			var html = '';

			// Image.
			if (config.showImage !== false) {
				var imgSrc = hit.image || config.placeholderImg || '';
				html += '<div class="wss-result-image">';
				html += '<img src="' + escHtml(imgSrc) + '" alt="' + escHtml(hit.name || '') + '" loading="lazy" />';
				html += '</div>';
			}

			html += '<div class="wss-result-info">';

			// Category.
			if (config.showCategory !== false && hit.categories && hit.categories.length) {
				html += '<span class="wss-result-category">' + escHtml(hit.categories[0]) + '</span>';
			}

			// Title with highlighting.
			var title = hit.name_highlighted || escHtml(hit.name || '');
			html += '<h4 class="wss-result-title">' + title + '</h4>';

			// SKU.
			if (config.showSku && hit.sku) {
				html += '<span class="wss-result-sku">SKU: ' + escHtml(hit.sku) + '</span>';
			}

			// Price.
			if (config.showPrice !== false && hit.price !== undefined) {
				html += '<div class="wss-result-price">';
				if (hit.on_sale && hit.regular_price) {
					html += '<span class="wss-price-regular">' + formatPrice(hit.regular_price) + '</span> ';
					html += '<span class="wss-price-current wss-price-sale">' + formatPrice(hit.price) + '</span>';
					var discount = Math.round((1 - hit.price / hit.regular_price) * 100);
					if (discount > 0) {
						html += ' <span class="wss-price-badge">-' + discount + '%</span>';
					}
				} else if (hit.price_min && hit.price_max && hit.price_min !== hit.price_max) {
					html += '<span class="wss-price-current">' + formatPrice(hit.price_min) + ' &ndash; ' + formatPrice(hit.price_max) + '</span>';
				} else {
					html += '<span class="wss-price-current">' + formatPrice(hit.price) + '</span>';
				}
				html += '</div>';
			}

			// Stock.
			if (config.showStock !== false && hit.stock_status) {
				var stockClass = 'wss-stock-' + hit.stock_status;
				var stockText = config.i18n[hit.stock_status === 'instock' ? 'inStock' : (hit.stock_status === 'outofstock' ? 'outOfStock' : 'onBackorder')] || hit.stock_status;
				html += '<span class="wss-result-stock ' + stockClass + '">' + escHtml(stockText) + '</span>';
			}

			// Rating.
			if (config.showRating && hit.rating) {
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

			html += '</div>';

			a.innerHTML = html;

			// Hover handling.
			a.addEventListener('mouseenter', function () {
				selectedIndex = index;
				var items = dropdown.querySelectorAll('.wss-result-item');
				updateSelection(items);
			});

			return a;
		}

		/**
		 * Update the visual selection state.
		 *
		 * @param {NodeList} items Result items.
		 */
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
			dropdown.style.display = '';
			input.setAttribute('aria-expanded', 'true');
		}

		function hideDropdown() {
			dropdown.style.display = 'none';
			input.setAttribute('aria-expanded', 'false');
			selectedIndex = -1;
		}

		function showLoading() {
			spinner.style.display = '';
			icon.style.display = 'none';
		}

		function hideLoading() {
			spinner.style.display = 'none';
			if (!input.value.trim()) {
				icon.style.display = '';
			}
		}

		function showError() {
			productsContainer.innerHTML = '';
			emptyContainer.style.display = 'none';
			errorContainer.style.display = '';
			errorContainer.textContent = config.i18n.error || 'Connection error, please try again';
			footer.style.display = 'none';
			showDropdown();
		}
	}

	/**
	 * Format a price according to WooCommerce settings.
	 *
	 * @param {number} price The price value.
	 * @return {string}
	 */
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

	/**
	 * Get the full search page URL.
	 *
	 * @param {string} query Search query.
	 * @return {string}
	 */
	function getSearchPageUrl(query) {
		var url = config.searchUrl || '/?s={query}&post_type=product';
		return url.replace('{query}', encodeURIComponent(query));
	}

	/**
	 * Escape HTML for safe insertion.
	 *
	 * @param {string} str String to escape.
	 * @return {string}
	 */
	function escHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
