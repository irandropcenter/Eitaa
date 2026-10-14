/**
 * Editor script of the "Eitaa login button" block (no build step required).
 * The block is dynamic: the front-end markup is rendered by PHP.
 *
 * @package MW24_Eitaa
 */
( function ( wp, strings ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var be = wp.blockEditor || wp.editor;
	var components = wp.components;
	var t = strings || {};

	wp.blocks.registerBlockType( 'mw24-eitaa/login-button', {
		edit: function ( props ) {
			var blockProps = be && be.useBlockProps ? be.useBlockProps() : {};
			var label = props.attributes.label || t.defaultLabel || 'Eitaa';

			return el(
				'div',
				blockProps,
				el(
					be.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: t.title || '' },
						el( components.TextControl, {
							label: t.labelField || '',
							help: t.labelHelp || '',
							value: props.attributes.label,
							onChange: function ( value ) {
								props.setAttributes( { label: value } );
							}
						} )
					)
				),
				el(
					'div',
					{ className: 'mw24-eitaa-login mw24-eitaa-login--preview' },
					el( 'span', { className: 'mw24-eitaa-login__btn', role: 'presentation' }, label ),
					el( 'p', { className: 'mw24-eitaa-login__preview-note' }, t.preview || '' )
				)
			);
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp, window.MW24EitaaBlock );
