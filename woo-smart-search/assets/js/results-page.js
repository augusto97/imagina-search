/**
 * Woo Smart Search — Results Page
 *
 * Faceted search results with AJAX filtering, sorting, pagination,
 * and URL parameter persistence.
 *
 * @package WooSmartSearch
 */

( function () {
	'use strict';

	/* ---- Globals from wp_localize_script ---- */
	var cfg = window.wssConfig || {};

	/* ---- State ---- */
	var state = {
		query:   '',
		page:    1,
		limit:   12,
		sort:    '',
		filters: {},          // { categories: ['Cat A'], stock_status: ['instock'], ... }
		priceMin: null,
		priceMax: null,
		view:    'grid',      // 'grid' or 'list'
		total:   0,
		facets:  {},
		loading: false,
		controller: null
	};

	/* ---- DOM refs (set on init) ---- */
	var dom = {};

	/* ---- Init ---- */
	function init() {
		var page = document.querySelector( '.wss-results-page' );
		if ( ! page ) {
			return;
		}

		dom.page           = page;
		dom.grid           = page.querySelector( '.wss-products-grid' );
		dom.sidebar        = page.querySelector( '.wss-filters-sidebar' );
		dom.toolbar        = page.querySelector( '.wss-results-toolbar' );
		dom.sortSelect     = page.querySelector( '.wss-sort-select' );
		dom.viewToggle     = page.querySelector( '.wss-view-toggle' );
		dom.pagination     = page.querySelector( '.wss-pagination' );
		dom.loading        = page.querySelector( '.wss-results-loading' );
		dom.noResults      = page.querySelector( '.wss-no-results' );
		dom.resultsCount   = page.querySelector( '.wss-results-count' );
		dom.activeFilters  = page.querySelector( '.wss-active-filters' );
		dom.mobileToggle   = page.querySelector( '.wss-mobile-filter-toggle' );
		dom.mobileOverlay  = page.querySelector( '.wss-mobile-filter-overlay' );
		dom.filterClose    = page.querySelector( '.wss-filter-panel-close button' );

		// Read URL params.
		readUrlParams();

		// Bind events.
		bindEvents();

		// Initial search.
		performSearch();
	}

	/* ---- URL Params ---- */

	function readUrlParams() {
		var params = new URLSearchParams( window.location.search );

		state.query    = params.get( 'q' ) || params.get( 's' ) || '';
		state.page     = parseInt( params.get( 'paged' ) || '1', 10 );
		state.sort     = params.get( 'sort' ) || '';
		state.view     = params.get( 'view' ) || 'grid';

		var priceMin = params.get( 'price_min' );
		var priceMax = params.get( 'price_max' );
		if ( priceMin ) state.priceMin = parseFloat( priceMin );
		if ( priceMax ) state.priceMax = parseFloat( priceMax );

		// Read filter params (filter_categories=A,B).
		params.forEach( function ( value, key ) {
			if ( key.indexOf( 'filter_' ) === 0 ) {
				var filterName = key.replace( 'filter_', '' );
				state.filters[ filterName ] = value.split( ',' );
			}
		} );
	}

	function updateUrl() {
		var params = new URLSearchParams();
		params.set( 'q', state.query );

		if ( state.page > 1 ) params.set( 'paged', state.page );
		if ( state.sort )     params.set( 'sort', state.sort );
		if ( state.view !== 'grid' ) params.set( 'view', state.view );
		if ( state.priceMin !== null ) params.set( 'price_min', state.priceMin );
		if ( state.priceMax !== null ) params.set( 'price_max', state.priceMax );

		Object.keys( state.filters ).forEach( function ( key ) {
			if ( state.filters[ key ].length ) {
				params.set( 'filter_' + key, state.filters[ key ].join( ',' ) );
			}
		} );

		var newUrl = window.location.pathname + '?' + params.toString();
		window.history.replaceState( null, '', newUrl );
	}

	/* ---- Events ---- */

	function bindEvents() {
		// Sort.
		if ( dom.sortSelect ) {
			dom.sortSelect.value = state.sort;
			dom.sortSelect.addEventListener( 'change', function () {
				state.sort = this.value;
				state.page = 1;
				performSearch();
			} );
		}

		// View toggle.
		if ( dom.viewToggle ) {
			var btns = dom.viewToggle.querySelectorAll( 'button' );
			btns.forEach( function ( btn ) {
				if ( btn.dataset.view === state.view ) btn.classList.add( 'wss-active' );
				btn.addEventListener( 'click', function () {
					btns.forEach( function ( b ) { b.classList.remove( 'wss-active' ); } );
					btn.classList.add( 'wss-active' );
					state.view = btn.dataset.view;
					if ( dom.grid ) {
						dom.grid.classList.toggle( 'wss-view-list', state.view === 'list' );
					}
					updateUrl();
				} );
			} );
		}

		// Mobile filter toggle.
		if ( dom.mobileToggle ) {
			dom.mobileToggle.addEventListener( 'click', function () {
				if ( dom.sidebar ) dom.sidebar.classList.add( 'wss-open' );
				if ( dom.mobileOverlay ) dom.mobileOverlay.classList.add( 'wss-visible' );
				document.body.style.overflow = 'hidden';
			} );
		}

		if ( dom.mobileOverlay ) {
			dom.mobileOverlay.addEventListener( 'click', closeMobileFilters );
		}

		if ( dom.filterClose ) {
			dom.filterClose.addEventListener( 'click', closeMobileFilters );
		}
	}

	function closeMobileFilters() {
		if ( dom.sidebar ) dom.sidebar.classList.remove( 'wss-open' );
		if ( dom.mobileOverlay ) dom.mobileOverlay.classList.remove( 'wss-visible' );
		document.body.style.overflow = '';
	}

	/* ---- Search ---- */

	function performSearch() {
		if ( state.loading && state.controller ) {
			state.controller.abort();
		}

		state.loading = true;
		showLoading( true );
		updateUrl();

		state.controller = new AbortController();

		// Build filter string for Meilisearch.
		var filterParts = [];

		Object.keys( state.filters ).forEach( function ( key ) {
			if ( state.filters[ key ].length ) {
				var conditions = state.filters[ key ].map( function ( v ) {
					return key + ' = "' + v.replace( /"/g, '\\"' ) + '"';
				} );
				filterParts.push( '(' + conditions.join( ' OR ' ) + ')' );
			}
		} );

		if ( state.priceMin !== null ) {
			filterParts.push( 'price >= ' + state.priceMin );
		}
		if ( state.priceMax !== null ) {
			filterParts.push( 'price <= ' + state.priceMax );
		}

		var params = new URLSearchParams();
		params.set( 'q', state.query );
		params.set( 'limit', state.limit );
		params.set( 'page', state.page );

		if ( filterParts.length ) {
			params.set( 'filters', filterParts.join( ' AND ' ) );
		}

		if ( state.sort ) {
			params.set( 'sort', state.sort );
		}

		// Always request all valid Meilisearch facet fields; visibility is
		// controlled at render time via cfg.visibleFacets.
		params.set( 'facets', 'categories,stock_status,on_sale,brand,rating' );

		var url = cfg.apiUrl + '?' + params.toString();

		fetch( url, {
			signal: state.controller.signal,
			headers: { 'X-WP-Nonce': cfg.nonce }
		} )
		.then( function ( res ) { return res.json(); } )
		.then( function ( data ) {
			state.loading = false;
			state.total   = data.total || 0;
			state.facets  = data.facets || {};

			showLoading( false );
			renderResults( data.hits || [] );
			renderFacets( state.facets );
			renderActiveFilters();
			renderPagination();
			updateResultsCount();
		} )
		.catch( function ( err ) {
			if ( err.name === 'AbortError' ) return;
			state.loading = false;
			showLoading( false );
			console.error( 'WSS Results:', err );
		} );
	}

	/* ---- Render Results ---- */

	function renderResults( hits ) {
		if ( ! dom.grid ) return;

		if ( ! hits.length ) {
			dom.grid.innerHTML = '';
			if ( dom.noResults ) dom.noResults.style.display = 'block';
			return;
		}

		if ( dom.noResults ) dom.noResults.style.display = 'none';

		dom.grid.classList.toggle( 'wss-view-list', state.view === 'list' );

		var html = '';
		hits.forEach( function ( hit ) {
			html += buildProductCard( hit );
		} );

		dom.grid.innerHTML = html;

		// Click tracking.
		dom.grid.querySelectorAll( '.wss-product-card a' ).forEach( function ( link ) {
			link.addEventListener( 'click', function () {
				var productId = link.closest( '.wss-product-card' ).dataset.id;
				if ( productId && cfg.trackClickUrl ) {
					trackClick( state.query, productId );
				}
			} );
		} );
	}

	function buildProductCard( hit ) {
		var imgSrc    = hit.image || cfg.placeholderImg || '';
		var name      = hit.name_highlighted ? decodeHtml( hit.name_highlighted ) : escapeHtml( decodeHtml( hit.name || '' ) );
		var category  = ( hit.categories && hit.categories.length ) ? escapeHtml( decodeHtml( hit.categories[0] ) ) : '';
		var permalink = hit.permalink || '#';
		var saleBadge = '';

		// Price.
		var priceHtml = '';
		if ( typeof hit.price !== 'undefined' ) {
			if ( hit.on_sale && hit.regular_price > hit.price ) {
				var discount = Math.round( ( 1 - hit.price / hit.regular_price ) * 100 );
				saleBadge = '<span class="wss-sale-badge">-' + discount + '%</span>';
				priceHtml = '<span class="wss-price-current">' + formatPrice( hit.price ) + '</span>' +
					'<span class="wss-price-regular">' + formatPrice( hit.regular_price ) + '</span>';
			} else if ( hit.price_min && hit.price_max && hit.price_min !== hit.price_max ) {
				priceHtml = '<span class="wss-price-range">' +
					formatPrice( hit.price_min ) + ' – ' + formatPrice( hit.price_max ) + '</span>';
			} else {
				priceHtml = '<span class="wss-price-current">' + formatPrice( hit.price ) + '</span>';
			}
		}

		// Stock.
		var stockHtml = '';
		if ( hit.stock_status ) {
			var stockClass = 'wss-stock-' + hit.stock_status;
			var stockLabel = hit.stock_status === 'instock' ? ( cfg.i18n ? cfg.i18n.inStock : 'In stock' ) :
				hit.stock_status === 'outofstock' ? ( cfg.i18n ? cfg.i18n.outOfStock : 'Out of stock' ) :
				( cfg.i18n ? cfg.i18n.onBackorder : 'On backorder' );
			stockHtml = '<div class="wss-product-card-stock">' +
				'<span class="wss-stock-dot ' + stockClass + '"></span>' +
				'<span>' + escapeHtml( stockLabel ) + '</span></div>';
		}

		// Rating.
		var ratingHtml = '';
		if ( hit.rating && hit.rating > 0 ) {
			ratingHtml = '<div class="wss-product-card-rating">' +
				'<span class="wss-stars">' + buildStars( hit.rating ) + '</span>' +
				( hit.review_count ? '<span class="wss-review-count">(' + hit.review_count + ')</span>' : '' ) +
				'</div>';
		}

		return '<div class="wss-product-card" data-id="' + ( hit.id || '' ) + '">' +
			'<a href="' + escapeHtml( permalink ) + '">' +
			'<div class="wss-product-card-image">' +
				saleBadge +
				'<img src="' + escapeHtml( imgSrc ) + '" alt="' + escapeHtml( hit.name || '' ) + '" loading="lazy" />' +
			'</div>' +
			'<div class="wss-product-card-body">' +
				( category ? '<div class="wss-product-card-category">' + category + '</div>' : '' ) +
				'<div class="wss-product-card-name">' + name + '</div>' +
				'<div class="wss-product-card-price">' + priceHtml + '</div>' +
				stockHtml +
				ratingHtml +
			'</div>' +
			'</a></div>';
	}

	/* ---- Render Facets (Sidebar) ---- */

	function renderFacets( facets ) {
		if ( ! dom.sidebar ) return;

		// Keep mobile close button.
		var closeHtml = '<div class="wss-filter-panel-close"><button type="button" aria-label="Close">&times;</button></div>';
		var html = closeHtml;

		// Determine which facets are visible from config (admin-friendly keys).
		var visibleList = ( cfg.visibleFacets || 'categories,price,stock,attributes,brands,rating' ).split( ',' );
		function isFacetVisible( key ) {
			return visibleList.indexOf( key ) !== -1;
		}

		// Categories.
		if ( isFacetVisible( 'categories' ) && facets.categories ) {
			html += buildCheckboxFilter( 'categories', cfg.i18n ? 'Categories' : 'Categories', facets.categories );
		}

		// Price slider.
		if ( isFacetVisible( 'price' ) ) {
			html += buildPriceFilter();
		}

		// Stock status.
		if ( isFacetVisible( 'stock' ) && facets.stock_status ) {
			html += buildCheckboxFilter( 'stock_status', 'Stock', facets.stock_status );
		}

		// Brand.
		if ( isFacetVisible( 'brands' ) && facets.brand && Object.keys( facets.brand ).length ) {
			html += buildCheckboxFilter( 'brand', 'Brand', facets.brand );
		}

		// Rating.
		if ( isFacetVisible( 'rating' ) && facets.rating ) {
			html += buildRatingFilter( facets.rating );
		}

		dom.sidebar.innerHTML = html;

		// Re-bind close button.
		var newClose = dom.sidebar.querySelector( '.wss-filter-panel-close button' );
		if ( newClose ) {
			newClose.addEventListener( 'click', closeMobileFilters );
		}

		// Bind filter events.
		bindFilterEvents();
	}

	function buildCheckboxFilter( key, label, values ) {
		var entries = Object.entries( values ).sort( function ( a, b ) { return b[1] - a[1]; } );
		if ( ! entries.length ) return '';

		var selected = state.filters[ key ] || [];

		var html = '<div class="wss-filter-group" data-filter="' + key + '">' +
			'<button class="wss-filter-group-header" type="button">' +
			'<span>' + escapeHtml( label ) + '</span>' +
			'<span class="wss-chevron">&#9660;</span>' +
			'</button><div class="wss-filter-group-body">';

		entries.forEach( function ( entry ) {
			var val   = entry[0];
			var decoded = decodeHtml( val );
			var count = entry[1];
			var checked = selected.indexOf( val ) !== -1 ? ' checked' : '';
			html += '<label class="wss-filter-option">' +
				'<input type="checkbox" value="' + escapeHtml( val ) + '"' + checked + ' />' +
				'<span class="wss-filter-label">' + escapeHtml( decoded ) + '</span>' +
				'<span class="wss-filter-count">(' + count + ')</span>' +
				'</label>';
		} );

		html += '</div></div>';
		return html;
	}

	function buildPriceFilter() {
		return '<div class="wss-filter-group" data-filter="price">' +
			'<button class="wss-filter-group-header" type="button">' +
			'<span>Price</span>' +
			'<span class="wss-chevron">&#9660;</span>' +
			'</button>' +
			'<div class="wss-filter-group-body">' +
			'<div class="wss-price-slider-wrap">' +
			'<div class="wss-price-inputs">' +
			'<input type="number" class="wss-price-min" placeholder="Min" value="' + ( state.priceMin !== null ? state.priceMin : '' ) + '" min="0" step="1" />' +
			'<span class="wss-price-sep">–</span>' +
			'<input type="number" class="wss-price-max" placeholder="Max" value="' + ( state.priceMax !== null ? state.priceMax : '' ) + '" min="0" step="1" />' +
			'</div></div></div></div>';
	}

	function buildRatingFilter( values ) {
		var html = '<div class="wss-filter-group wss-rating-filter" data-filter="rating">' +
			'<button class="wss-filter-group-header" type="button">' +
			'<span>Rating</span>' +
			'<span class="wss-chevron">&#9660;</span>' +
			'</button><div class="wss-filter-group-body">';

		var selected = state.filters.rating || [];

		for ( var i = 5; i >= 1; i-- ) {
			var count   = values[ i ] || 0;
			var checked = selected.indexOf( String( i ) ) !== -1 ? ' checked' : '';
			html += '<label class="wss-filter-option">' +
				'<input type="checkbox" value="' + i + '"' + checked + ' />' +
				'<span class="wss-rating-stars">' + buildStars( i ) + '</span>' +
				'<span class="wss-filter-count">(' + count + ')</span>' +
				'</label>';
		}

		html += '</div></div>';
		return html;
	}

	function bindFilterEvents() {
		if ( ! dom.sidebar ) return;

		// Collapse toggle.
		dom.sidebar.querySelectorAll( '.wss-filter-group-header' ).forEach( function ( header ) {
			header.addEventListener( 'click', function () {
				header.closest( '.wss-filter-group' ).classList.toggle( 'wss-collapsed' );
			} );
		} );

		// Checkbox filters.
		dom.sidebar.querySelectorAll( '.wss-filter-group[data-filter]' ).forEach( function ( group ) {
			var filterKey = group.dataset.filter;
			if ( filterKey === 'price' ) return;

			group.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( cb ) {
				cb.addEventListener( 'change', function () {
					if ( ! state.filters[ filterKey ] ) {
						state.filters[ filterKey ] = [];
					}
					if ( cb.checked ) {
						state.filters[ filterKey ].push( cb.value );
					} else {
						state.filters[ filterKey ] = state.filters[ filterKey ].filter( function ( v ) {
							return v !== cb.value;
						} );
					}
					state.page = 1;
					performSearch();
				} );
			} );
		} );

		// Price inputs.
		var priceMinInput = dom.sidebar.querySelector( '.wss-price-min' );
		var priceMaxInput = dom.sidebar.querySelector( '.wss-price-max' );

		var priceDebounce;
		function onPriceChange() {
			clearTimeout( priceDebounce );
			priceDebounce = setTimeout( function () {
				var minVal = priceMinInput ? priceMinInput.value : '';
				var maxVal = priceMaxInput ? priceMaxInput.value : '';
				state.priceMin = minVal !== '' ? parseFloat( minVal ) : null;
				state.priceMax = maxVal !== '' ? parseFloat( maxVal ) : null;
				state.page = 1;
				performSearch();
			}, 500 );
		}

		if ( priceMinInput ) priceMinInput.addEventListener( 'input', onPriceChange );
		if ( priceMaxInput ) priceMaxInput.addEventListener( 'input', onPriceChange );
	}

	/* ---- Active Filters ---- */

	function renderActiveFilters() {
		if ( ! dom.activeFilters ) return;

		var tags = [];
		var hasFilters = false;

		Object.keys( state.filters ).forEach( function ( key ) {
			state.filters[ key ].forEach( function ( val ) {
				hasFilters = true;
				tags.push(
					'<span class="wss-active-filter-tag" data-filter="' + escapeHtml( key ) + '" data-value="' + escapeHtml( val ) + '">' +
					escapeHtml( val ) + ' <span class="wss-remove">&times;</span></span>'
				);
			} );
		} );

		if ( state.priceMin !== null || state.priceMax !== null ) {
			hasFilters = true;
			var priceLabel = ( state.priceMin !== null ? formatPrice( state.priceMin ) : '' ) +
				' – ' + ( state.priceMax !== null ? formatPrice( state.priceMax ) : '' );
			tags.push(
				'<span class="wss-active-filter-tag" data-filter="price">' +
				escapeHtml( priceLabel ) + ' <span class="wss-remove">&times;</span></span>'
			);
		}

		if ( hasFilters ) {
			tags.push( '<button class="wss-clear-all-filters" type="button">Clear all</button>' );
		}

		dom.activeFilters.innerHTML = tags.join( '' );

		// Bind removal.
		dom.activeFilters.querySelectorAll( '.wss-active-filter-tag' ).forEach( function ( tag ) {
			tag.addEventListener( 'click', function () {
				var filterKey = tag.dataset.filter;
				if ( filterKey === 'price' ) {
					state.priceMin = null;
					state.priceMax = null;
				} else {
					var filterVal = tag.dataset.value;
					state.filters[ filterKey ] = ( state.filters[ filterKey ] || [] ).filter( function ( v ) {
						return v !== filterVal;
					} );
				}
				state.page = 1;
				performSearch();
			} );
		} );

		var clearBtn = dom.activeFilters.querySelector( '.wss-clear-all-filters' );
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function () {
				state.filters  = {};
				state.priceMin = null;
				state.priceMax = null;
				state.page     = 1;
				performSearch();
			} );
		}
	}

	/* ---- Pagination ---- */

	function renderPagination() {
		if ( ! dom.pagination ) return;

		var totalPages = Math.ceil( state.total / state.limit );
		if ( totalPages <= 1 ) {
			dom.pagination.innerHTML = '';
			return;
		}

		var html = '';

		// Prev.
		html += '<button class="wss-page-prev"' + ( state.page <= 1 ? ' disabled' : '' ) + '>&laquo;</button>';

		// Page numbers.
		var start = Math.max( 1, state.page - 2 );
		var end   = Math.min( totalPages, state.page + 2 );

		if ( start > 1 ) {
			html += '<button data-page="1">1</button>';
			if ( start > 2 ) html += '<span style="padding:0 4px;">...</span>';
		}

		for ( var i = start; i <= end; i++ ) {
			html += '<button data-page="' + i + '"' + ( i === state.page ? ' class="wss-active"' : '' ) + '>' + i + '</button>';
		}

		if ( end < totalPages ) {
			if ( end < totalPages - 1 ) html += '<span style="padding:0 4px;">...</span>';
			html += '<button data-page="' + totalPages + '">' + totalPages + '</button>';
		}

		// Next.
		html += '<button class="wss-page-next"' + ( state.page >= totalPages ? ' disabled' : '' ) + '>&raquo;</button>';

		dom.pagination.innerHTML = html;

		// Bind.
		dom.pagination.querySelectorAll( 'button[data-page]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				state.page = parseInt( btn.dataset.page, 10 );
				performSearch();
				window.scrollTo( { top: dom.page.offsetTop - 20, behavior: 'smooth' } );
			} );
		} );

		var prevBtn = dom.pagination.querySelector( '.wss-page-prev' );
		var nextBtn = dom.pagination.querySelector( '.wss-page-next' );

		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', function () {
				if ( state.page > 1 ) {
					state.page--;
					performSearch();
					window.scrollTo( { top: dom.page.offsetTop - 20, behavior: 'smooth' } );
				}
			} );
		}

		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', function () {
				if ( state.page < totalPages ) {
					state.page++;
					performSearch();
					window.scrollTo( { top: dom.page.offsetTop - 20, behavior: 'smooth' } );
				}
			} );
		}
	}

	/* ---- Helpers ---- */

	function showLoading( show ) {
		if ( dom.loading ) {
			dom.loading.classList.toggle( 'wss-visible', show );
		}
		if ( dom.grid ) {
			dom.grid.style.opacity = show ? '0.5' : '1';
		}
	}

	function updateResultsCount() {
		if ( dom.resultsCount ) {
			dom.resultsCount.textContent = state.total + ' products';
		}
	}

	function formatPrice( amount ) {
		if ( typeof amount !== 'number' || isNaN( amount ) ) return '';

		var decimals    = cfg.decimals || 2;
		var decSep      = cfg.decimalSep || '.';
		var thousandSep = cfg.thousandSep || ',';
		var symbol      = cfg.currencySymbol || '$';
		var pos         = cfg.currencyPos || 'left';

		var fixed  = amount.toFixed( decimals );
		var parts  = fixed.split( '.' );
		var intPart = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, thousandSep );
		var formatted = decimals > 0 ? intPart + decSep + parts[1] : intPart;

		switch ( pos ) {
			case 'left':       return symbol + formatted;
			case 'left_space': return symbol + ' ' + formatted;
			case 'right':      return formatted + symbol;
			case 'right_space':return formatted + ' ' + symbol;
			default:           return symbol + formatted;
		}
	}

	function buildStars( rating ) {
		var full  = Math.floor( rating );
		var half  = rating - full >= 0.5 ? 1 : 0;
		var empty = 5 - full - half;
		var s     = '';

		for ( var i = 0; i < full; i++ )  s += '&#9733;';
		for ( var j = 0; j < half; j++ )  s += '&#9733;';
		for ( var k = 0; k < empty; k++ ) s += '<span class="wss-star-empty">&#9733;</span>';

		return s;
	}

	function escapeHtml( str ) {
		var div       = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}

	function decodeHtml( str ) {
		var txt = document.createElement( 'textarea' );
		txt.innerHTML = str;
		return txt.value;
	}

	function trackClick( query, productId ) {
		if ( ! cfg.trackClickUrl ) return;

		var formData = new FormData();
		formData.append( 'query', query );
		formData.append( 'product_id', productId );

		fetch( cfg.trackClickUrl, {
			method: 'POST',
			headers: { 'X-WP-Nonce': cfg.nonce },
			body: formData
		} ).catch( function () {} );
	}

	/* ---- Boot ---- */
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
