/**
 * EO SEO admin screens: media picker, character counters, template variables and
 * the address section of a local business. No dependency besides wp.media and wp.i18n.
 */
( function () {
	const { __, sprintf } = wp.i18n;

	// Media picker.
	document.querySelectorAll( '[data-eo-seo-media]' ).forEach( ( field ) => {
		const input = field.querySelector( '[data-eo-seo-media-input]' );
		const preview = field.querySelector( '[data-eo-seo-media-preview]' );
		const remove = field.querySelector( '[data-eo-seo-media-remove]' );
		const large = field.classList.contains( 'eo-seo-media--large' );
		let frame;

		field.querySelector( '[data-eo-seo-media-select]' ).addEventListener( 'click', () => {
			if ( ! frame ) {
				frame = wp.media( {
					title: __( 'Choose an image', 'eo-seo' ),
					library: { type: 'image' },
					multiple: false,
				} );
				frame.on( 'select', () => {
					const image = frame.state().get( 'selection' ).first().toJSON();
					const sizes = image.sizes || {};
					const size = ( large ? sizes.medium_large || sizes.large : sizes.thumbnail ) || image;
					const img = document.createElement( 'img' );
					img.src = size.url;
					img.alt = '';
					preview.replaceChildren( img );
					input.value = image.id;
					remove.hidden = false;
					field.classList.add( 'has-image' );
				} );
			}
			frame.open();
		} );

		remove.addEventListener( 'click', () => {
			input.value = '0';
			preview.replaceChildren();
			remove.hidden = true;
			field.classList.remove( 'has-image' );
		} );
	} );

	// Character counters (term screen).
	document.querySelectorAll( '[data-eo-seo-counter]' ).forEach( ( field ) => {
		const [ min, max ] = field.dataset.eoSeoCounter.split( ',' ).map( Number );
		const output = document.createElement( 'span' );
		output.className = 'eo-seo-counter';
		field.after( output );

		const update = () => {
			const length = field.value.length;
			output.textContent = sprintf(
				/* translators: 1: number of characters, 2: minimum, 3: maximum. */
				__( '%1$d characters (recommended: %2$d to %3$d)', 'eo-seo' ),
				length,
				min,
				max
			);
			output.classList.toggle( 'is-bad', length > 0 && ( length < min || length > max ) );
		};
		field.addEventListener( 'input', update );
		update();
	} );

	// Template variables: a click inserts the variable in the last focused template field.
	const templates = document.querySelectorAll( '[data-eo-seo-template]' );
	let lastField = templates[ 0 ];
	templates.forEach( ( field ) => field.addEventListener( 'focus', () => ( lastField = field ) ) );
	document.querySelectorAll( '[data-eo-seo-variable]' ).forEach( ( chip ) => {
		chip.addEventListener( 'click', () => {
			if ( ! lastField ) {
				return;
			}
			const variable = chip.dataset.eoSeoVariable;
			const start = lastField.selectionStart ?? lastField.value.length;
			const end = lastField.selectionEnd ?? start;
			const before = lastField.value.slice( 0, start );
			const glue = before && ! before.endsWith( ' ' ) ? ' ' : '';
			lastField.value = before + glue + variable + lastField.value.slice( end );
			lastField.focus();
			const caret = start + glue.length + variable.length;
			lastField.setSelectionRange( caret, caret );
		} );
	} );

	// Address and phone: only for a local business.
	const types = document.querySelector( '[data-eo-seo-identity-type]' );
	const address = document.querySelector( '[data-eo-seo-local-business]' );
	if ( types && address ) {
		types.addEventListener( 'change', ( event ) => {
			address.hidden = 'local_business' !== event.target.value;
		} );
	}
} )();
