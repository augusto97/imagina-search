/**
 * Woo Smart Search - Gutenberg Block
 */
(function (blocks, element, blockEditor, components) {
	var el = element.createElement;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;

	var layoutDescriptions = {
		standard: 'Simple dropdown list with product results.',
		expanded: 'Two-column layout with sidebar (popular searches, categories) and product grid. Recommended for header placement.',
		compact: 'Minimal layout with smaller text and tighter spacing.'
	};

	blocks.registerBlockType('woo-smart-search/search-bar', {
		title: 'Woo Smart Search',
		icon: 'search',
		category: 'woocommerce',
		description: 'Smart product search bar with instant results.',
		attributes: {
			placeholder: { type: 'string', default: '' },
			width: { type: 'string', default: '100%' },
			layout: { type: 'string', default: '' },
			showImage: { type: 'string', default: '' },
			showPrice: { type: 'string', default: '' },
			showCategory: { type: 'string', default: '' },
			maxResults: { type: 'string', default: '' },
			theme: { type: 'string', default: '' }
		},
		edit: function (props) {
			var attrs = props.attributes;
			var layoutLabel = attrs.layout || 'default (from settings)';
			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Layout', initialOpen: true },
						el(SelectControl, {
							label: 'Widget layout',
							value: attrs.layout,
							options: [
								{ label: 'Default (from plugin settings)', value: '' },
								{ label: 'Standard — Dropdown list', value: 'standard' },
								{ label: 'Expanded — Two columns with sidebar', value: 'expanded' },
								{ label: 'Compact — Minimal', value: 'compact' }
							],
							onChange: function (val) { props.setAttributes({ layout: val }); },
							help: attrs.layout ? layoutDescriptions[attrs.layout] : 'Uses the layout configured in Woo Smart Search settings.'
						}),
						el(TextControl, {
							label: 'Width',
							value: attrs.width,
							onChange: function (val) { props.setAttributes({ width: val }); },
							help: 'CSS width value (e.g. 100%, 500px, 30em)'
						}),
						el(SelectControl, {
							label: 'Theme',
							value: attrs.theme,
							options: [
								{ label: 'Default (from settings)', value: '' },
								{ label: 'Light', value: 'light' },
								{ label: 'Dark', value: 'dark' }
							],
							onChange: function (val) { props.setAttributes({ theme: val }); }
						})
					),
					el(
						PanelBody,
						{ title: 'Display Options', initialOpen: false },
						el(TextControl, {
							label: 'Placeholder text',
							value: attrs.placeholder,
							onChange: function (val) { props.setAttributes({ placeholder: val }); }
						}),
						el(TextControl, {
							label: 'Max results',
							value: attrs.maxResults,
							onChange: function (val) { props.setAttributes({ maxResults: val }); },
							help: 'Leave empty to use plugin settings.'
						}),
						el(SelectControl, {
							label: 'Show images',
							value: attrs.showImage,
							options: [
								{ label: 'Default (from settings)', value: '' },
								{ label: 'Yes', value: 'yes' },
								{ label: 'No', value: 'no' }
							],
							onChange: function (val) { props.setAttributes({ showImage: val }); }
						}),
						el(SelectControl, {
							label: 'Show prices',
							value: attrs.showPrice,
							options: [
								{ label: 'Default (from settings)', value: '' },
								{ label: 'Yes', value: 'yes' },
								{ label: 'No', value: 'no' }
							],
							onChange: function (val) { props.setAttributes({ showPrice: val }); }
						}),
						el(SelectControl, {
							label: 'Show categories',
							value: attrs.showCategory,
							options: [
								{ label: 'Default (from settings)', value: '' },
								{ label: 'Yes', value: 'yes' },
								{ label: 'No', value: 'no' }
							],
							onChange: function (val) { props.setAttributes({ showCategory: val }); }
						})
					)
				),
				// Block preview
				el(
					'div',
					{ className: 'wss-block-preview', style: { padding: '16px', background: '#f6f7f7', borderRadius: '8px', border: '1px solid #e0e0e0' } },
					el(
						'div',
						{ style: { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px' } },
						el('span', { className: 'dashicons dashicons-search', style: { color: '#2563eb' } }),
						el('strong', { style: { fontSize: '13px' } }, 'Woo Smart Search')
					),
					el('input', {
						type: 'text',
						placeholder: attrs.placeholder || 'Search products...',
						readOnly: true,
						style: {
							width: attrs.width || '100%',
							padding: '10px 12px',
							fontSize: '14px',
							border: '1px solid #c3c4c7',
							borderRadius: '6px',
							boxSizing: 'border-box'
						}
					}),
					el(
						'div',
						{ style: { display: 'flex', gap: '8px', marginTop: '8px', flexWrap: 'wrap' } },
						el('span', { style: { fontSize: '11px', background: '#e8f0fe', color: '#1a56db', padding: '2px 8px', borderRadius: '10px' } },
							'Layout: ' + (attrs.layout || 'default')
						),
						attrs.theme ? el('span', { style: { fontSize: '11px', background: attrs.theme === 'dark' ? '#374151' : '#f3f4f6', color: attrs.theme === 'dark' ? '#f9fafb' : '#374151', padding: '2px 8px', borderRadius: '10px' } },
							'Theme: ' + attrs.theme
						) : null
					)
				)
			);
		},
		save: function () {
			return null; // Render callback handles output.
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components
);
