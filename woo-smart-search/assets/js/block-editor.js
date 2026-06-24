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
	var RangeControl = components.RangeControl;
	var __experimentalUnitControl = components.__experimentalUnitControl;

	var searchIcon = el('svg', {
		xmlns: 'http://www.w3.org/2000/svg', width: 18, height: 18,
		viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor',
		strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round',
		style: { flexShrink: 0 }
	},
		el('circle', { cx: 11, cy: 11, r: 8 }),
		el('line', { x1: 21, y1: 21, x2: 16.65, y2: 16.65 })
	);

	blocks.registerBlockType('woo-smart-search/search-bar', {
		title: 'Woo Smart Search',
		icon: 'search',
		category: 'woocommerce',
		description: 'Smart product search bar with instant results.',
		supports: {
			align: ['wide', 'full'],
			spacing: { margin: true, padding: true },
		},
		attributes: {
			placeholder: { type: 'string', default: '' },
			width: { type: 'string', default: '100%' },
			layout: { type: 'string', default: '' },
			showImage: { type: 'string', default: '' },
			showPrice: { type: 'string', default: '' },
			showCategory: { type: 'string', default: '' },
			showSku: { type: 'string', default: '' },
			showStock: { type: 'string', default: '' },
			showRating: { type: 'string', default: '' },
			maxResults: { type: 'string', default: '' },
			theme: { type: 'string', default: '' },
			showIcon: { type: 'boolean', default: true },
			iconPosition: { type: 'string', default: 'left' },
			borderRadius: { type: 'number', default: 8 },
			inputHeight: { type: 'number', default: 44 },
		},
		edit: function (props) {
			var attrs = props.attributes;
			var placeholder = attrs.placeholder || 'Search products...';

			// Build preview input styles.
			var inputStyle = {
				width: '100%',
				padding: '0 14px',
				height: attrs.inputHeight + 'px',
				fontSize: '14px',
				border: '1px solid #d1d5db',
				borderRadius: attrs.borderRadius + 'px',
				boxSizing: 'border-box',
				outline: 'none',
				fontFamily: 'inherit',
				color: '#374151',
				background: '#fff',
			};

			if (attrs.showIcon) {
				if (attrs.iconPosition === 'left') {
					inputStyle.paddingLeft = '40px';
				} else {
					inputStyle.paddingRight = '40px';
				}
			}

			var iconStyle = {
				position: 'absolute',
				top: '50%',
				transform: 'translateY(-50%)',
				color: '#9ca3af',
				pointerEvents: 'none',
				display: 'flex',
				alignItems: 'center',
			};
			if (attrs.iconPosition === 'left') {
				iconStyle.left = '12px';
			} else {
				iconStyle.right = '12px';
			}

			return el(
				element.Fragment,
				null,
				// Inspector controls (sidebar panel).
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
								{ label: 'Default (from settings)', value: '' },
								{ label: 'Standard — Dropdown list', value: 'standard' },
								{ label: 'Expanded — Two columns', value: 'expanded' },
								{ label: 'Compact — Minimal', value: 'compact' },
								{ label: 'Amazon — Text suggestions', value: 'amazon' },
								{ label: 'Multi-column — Columns layout', value: 'falabella' },
								{ label: 'Fullscreen — Overlay', value: 'fullscreen' },
							],
							onChange: function (val) { props.setAttributes({ layout: val }); },
						}),
						el(SelectControl, {
							label: 'Theme',
							value: attrs.theme,
							options: [
								{ label: 'Default (from settings)', value: '' },
								{ label: 'Light', value: 'light' },
								{ label: 'Dark', value: 'dark' },
							],
							onChange: function (val) { props.setAttributes({ theme: val }); },
						}),
						el(TextControl, {
							label: 'Width',
							value: attrs.width,
							onChange: function (val) { props.setAttributes({ width: val }); },
							help: 'CSS value: 100%, 500px, 30em',
						})
					),
					el(
						PanelBody,
						{ title: 'Search Input', initialOpen: true },
						el(TextControl, {
							label: 'Placeholder text',
							value: attrs.placeholder,
							onChange: function (val) { props.setAttributes({ placeholder: val }); },
						}),
						el(ToggleControl, {
							label: 'Show search icon',
							checked: attrs.showIcon,
							onChange: function (val) { props.setAttributes({ showIcon: val }); },
						}),
						attrs.showIcon ? el(SelectControl, {
							label: 'Icon position',
							value: attrs.iconPosition,
							options: [
								{ label: 'Left', value: 'left' },
								{ label: 'Right', value: 'right' },
							],
							onChange: function (val) { props.setAttributes({ iconPosition: val }); },
						}) : null,
						el(RangeControl, {
							label: 'Input height (px)',
							value: attrs.inputHeight,
							onChange: function (val) { props.setAttributes({ inputHeight: val }); },
							min: 32,
							max: 64,
						}),
						el(RangeControl, {
							label: 'Border radius (px)',
							value: attrs.borderRadius,
							onChange: function (val) { props.setAttributes({ borderRadius: val }); },
							min: 0,
							max: 30,
						})
					),
					el(
						PanelBody,
						{ title: 'Results Display', initialOpen: false },
						el(TextControl, {
							label: 'Max results',
							value: attrs.maxResults,
							onChange: function (val) { props.setAttributes({ maxResults: val }); },
							help: 'Leave empty for plugin default.',
						}),
						el(SelectControl, {
							label: 'Show images',
							value: attrs.showImage,
							options: [
								{ label: 'Default', value: '' },
								{ label: 'Yes', value: 'yes' },
								{ label: 'No', value: 'no' },
							],
							onChange: function (val) { props.setAttributes({ showImage: val }); },
						}),
						el(SelectControl, {
							label: 'Show prices',
							value: attrs.showPrice,
							options: [
								{ label: 'Default', value: '' },
								{ label: 'Yes', value: 'yes' },
								{ label: 'No', value: 'no' },
							],
							onChange: function (val) { props.setAttributes({ showPrice: val }); },
						}),
						el(SelectControl, {
							label: 'Show categories',
							value: attrs.showCategory,
							options: [
								{ label: 'Default', value: '' },
								{ label: 'Yes', value: 'yes' },
								{ label: 'No', value: 'no' },
							],
							onChange: function (val) { props.setAttributes({ showCategory: val }); },
						}),
						el(SelectControl, {
							label: 'Show SKU',
							value: attrs.showSku,
							options: [
								{ label: 'Default', value: '' },
								{ label: 'Yes', value: 'yes' },
								{ label: 'No', value: 'no' },
							],
							onChange: function (val) { props.setAttributes({ showSku: val }); },
						}),
						el(SelectControl, {
							label: 'Show stock status',
							value: attrs.showStock,
							options: [
								{ label: 'Default', value: '' },
								{ label: 'Yes', value: 'yes' },
								{ label: 'No', value: 'no' },
							],
							onChange: function (val) { props.setAttributes({ showStock: val }); },
						}),
						el(SelectControl, {
							label: 'Show rating',
							value: attrs.showRating,
							options: [
								{ label: 'Default', value: '' },
								{ label: 'Yes', value: 'yes' },
								{ label: 'No', value: 'no' },
							],
							onChange: function (val) { props.setAttributes({ showRating: val }); },
						})
					)
				),
				// Block preview — clean input that looks like the real widget.
				el(
					'div',
					{
						style: {
							width: attrs.width || '100%',
							maxWidth: '100%',
							position: 'relative',
						}
					},
					attrs.showIcon ? el('span', { style: iconStyle }, searchIcon) : null,
					el('input', {
						type: 'text',
						placeholder: placeholder,
						readOnly: true,
						style: inputStyle,
					})
				)
			);
		},
		save: function () {
			return null;
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components
);
