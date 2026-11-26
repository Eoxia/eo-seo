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

	// Site scan: the dashboard runs the batches one after the other while it is open, then reloads
	// to show the new figures. Without JavaScript, the form starts the scan in the background (WP-Cron).
	const scan = document.querySelector( '[data-eo-seo-scan]' );
	if ( scan && wp.apiFetch ) {
		const status = scan.querySelector( '[data-eo-seo-scan-status]' );
		const bar = scan.querySelector( '[data-eo-seo-scan-bar]' );
		const button = scan.querySelector( '[data-eo-seo-scan-button]' );

		const show = ( state ) => {
			const total = Math.max( state.total, 1 );
			bar.hidden = false;
			bar.firstElementChild.style.width = Math.round( ( 100 * state.done ) / total ) + '%';
			status.textContent = sprintf(
				/* translators: 1: contents analyzed, 2: contents to analyze. */
				__( 'Scan in progress: %1$d of %2$d contents.', 'eo-seo' ),
				Math.min( state.done, state.total ),
				state.total
			);
		};

		const step = () =>
			wp.apiFetch( { path: '/eo-seo/v1/scan/step', method: 'POST' } ).then( ( state ) => {
				show( state );
				if ( 'running' === state.status ) {
					// A batch of the background scan may hold the lock: try again shortly.
					return new Promise( ( resolve ) => setTimeout( resolve, 400 ) ).then( step );
				}
				status.textContent = __( 'Scan finished. Updating the figures…', 'eo-seo' );
				window.location.reload();
			} );

		const fail = () => {
			status.textContent = __( 'The scan could not continue from this page: it goes on in the background.', 'eo-seo' );
			button.disabled = false;
			scan.classList.remove( 'is-running' );
		};

		scan.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			button.disabled = true;
			scan.classList.add( 'is-running' );
			wp.apiFetch( { path: '/eo-seo/v1/scan', method: 'POST' } )
				.then( ( state ) => {
					show( state );
					return step();
				} )
				.catch( fail );
		} );

		// A scan already running (nightly or after a change): this page helps it along.
		if ( '1' === scan.dataset.running ) {
			step().catch( fail );
		}
	}

	// Address and phone: only for a local business.
	const types = document.querySelector( '[data-eo-seo-identity-type]' );
	const address = document.querySelector( '[data-eo-seo-local-business]' );
	if ( types && address ) {
		types.addEventListener( 'change', ( event ) => {
			address.hidden = 'local_business' !== event.target.value;
		} );
	}
} )();
