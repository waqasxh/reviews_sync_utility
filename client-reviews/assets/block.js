( function ( blocks, element, blockEditor, components, i18n, serverSideRender ) {
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var RangeControl = components.RangeControl;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var ServerSideRender = serverSideRender;

	blocks.registerBlockType( 'client-reviews/reviews', {
		title: __( 'Client Reviews', 'client-reviews' ),
		icon: 'star-filled',
		category: 'widgets',
		attributes: {
			limit: { type: 'number', default: 5 },
			minRating: { type: 'number', default: 0 },
			layout: { type: 'string', default: 'grid' },
			businessName: { type: 'string', default: '' },
		},
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Review Settings', 'client-reviews' ) },
						el( RangeControl, {
							label: __( 'Number of reviews', 'client-reviews' ),
							value: attributes.limit,
							onChange: function ( value ) {
								setAttributes( { limit: value } );
							},
							min: 1,
							max: 50,
						} ),
						el( RangeControl, {
							label: __( 'Minimum rating', 'client-reviews' ),
							value: attributes.minRating,
							onChange: function ( value ) {
								setAttributes( { minRating: value } );
							},
							min: 0,
							max: 5,
						} ),
						el( SelectControl, {
							label: __( 'Layout', 'client-reviews' ),
							value: attributes.layout,
							options: [
								{ label: __( 'Grid', 'client-reviews' ), value: 'grid' },
								{ label: __( 'List', 'client-reviews' ), value: 'list' },
								{ label: __( 'Carousel', 'client-reviews' ), value: 'carousel' },
							],
							onChange: function ( value ) {
								setAttributes( { layout: value } );
							},
						} ),
						el( TextControl, {
							label: __( 'Business name override', 'client-reviews' ),
							help: __( 'Used only in the review schema markup; defaults to the site title.', 'client-reviews' ),
							value: attributes.businessName,
							onChange: function ( value ) {
								setAttributes( { businessName: value } );
							},
						} )
					)
				),
				el( ServerSideRender, {
					block: 'client-reviews/reviews',
					attributes: attributes,
				} )
			);
		},
		save: function () {
			return null; // Dynamic block: PHP render_callback owns the frontend markup.
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
);
