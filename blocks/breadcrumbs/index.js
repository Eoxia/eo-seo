/**
 * Editor of the "Breadcrumbs" block. Plain script, no build step: it uses the WordPress globals.
 */
( function ( wp ) {
	const el = wp.element.createElement;
	const { __ } = wp.i18n;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl, ToggleControl } = wp.components;
	const ServerSideRender = wp.serverSideRender;

	wp.blocks.registerBlockType( 'eo-seo/breadcrumbs', {
		edit( { attributes, setAttributes, name } ) {
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Settings', 'eo-seo' ) },
						el( TextControl, {
							label: __( 'Separator', 'eo-seo' ),
							value: attributes.separator,
							onChange: ( separator ) => setAttributes( { separator } ),
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true,
						} ),
						el( ToggleControl, {
							label: __( 'Show the home page', 'eo-seo' ),
							checked: attributes.showHome,
							onChange: ( showHome ) => setAttributes( { showHome } ),
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el( ServerSideRender, { block: name, attributes } )
			);
		},
		save: () => null,
	} );
} )( window.wp );
