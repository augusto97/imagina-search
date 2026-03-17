/**
 * Woo Smart Search - Gutenberg Block
 */
(function (blocks, element, blockEditor, components) {
	var el = element.createElement;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;

	blocks.registerBlockType('woo-smart-search/search-bar', {
		title: 'Woo Smart Search',
		icon: 'search',
		category: 'woocommerce',
		description: 'Smart product search bar with instant results.',
		attributes: {
			placeholder: { type: 'string', default: '' },
			width: { type: 'string', default: '100%' }
		},
		edit: function (props) {
			var attrs = props.attributes;
			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Settings', initialOpen: true },
						el(TextControl, {
							label: 'Placeholder text',
							value: attrs.placeholder,
							onChange: function (val) { props.setAttributes({ placeholder: val }); }
						}),
						el(TextControl, {
							label: 'Width',
							value: attrs.width,
							onChange: function (val) { props.setAttributes({ width: val }); }
						})
					)
				),
				el(
					'div',
					{ className: 'wss-block-preview', style: { padding: '16px', background: '#f6f7f7', borderRadius: '4px' } },
					el('input', {
						type: 'text',
						placeholder: attrs.placeholder || 'Search products...',
						readOnly: true,
						style: {
							width: attrs.width || '100%',
							padding: '10px 12px',
							fontSize: '14px',
							border: '1px solid #c3c4c7',
							borderRadius: '4px'
						}
					}),
					el('p', { style: { fontSize: '12px', color: '#8c8f94', marginTop: '8px' } }, 'Woo Smart Search widget')
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
