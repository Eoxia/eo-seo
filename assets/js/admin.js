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

	// Length gauges of the SEO box: they measure only what is typed (an empty field uses the template).
	document.querySelectorAll( '[data-eo-seo-counter]' ).forEach( ( field ) => {
		const [ min, max ] = field.dataset.eoSeoCounter.split( ',' ).map( Number );
		const meter = document.createElement( 'div' );
		const bar = document.createElement( 'span' );
		const fill = document.createElement( 'span' );
		const text = document.createElement( 'span' );
		meter.className = 'eo-seo-meter';
		bar.className = 'eo-seo-meter__bar';
		text.className = 'eo-seo-meter__text';
		bar.append( fill );
		meter.append( bar, text );
		field.after( meter );

		const update = () => {
			const length = field.value.length;
			fill.style.width = Math.min( 100, ( 100 * length ) / max ) + '%';
			meter.className = 'eo-seo-meter';
			if ( ! length ) {
				text.textContent = field.placeholder
					? __( 'Empty: the template writes it automatically (grey text). Write your own.', 'eo-seo' )
					: __( 'Empty.', 'eo-seo' );
				return;
			}
			meter.classList.add( length < min || length > max ? 'eo-seo-meter--bad' : 'eo-seo-meter--good' );
			text.textContent = sprintf(
				/* translators: 1: number of characters, 2: minimum, 3: maximum. */
				__( '%1$d characters (recommended: %2$d to %3$d)', 'eo-seo' ),
				length,
				min,
				max
			);
		};
		field.addEventListener( 'input', update );
		update();
	} );

	// Live Google preview of the SEO box: what is typed, else the value of the template.
	document.querySelectorAll( '[data-eo-seo-box]' ).forEach( ( box ) => {
		const vars = { sitename: box.dataset.sitename, sep: box.dataset.sep, title: box.dataset.title, term: box.dataset.title };
		const render = ( value ) =>
			value
				.replace( /%([a-z_]+)%/g, ( match, name ) => ( name in vars ? vars[ name ] : '' ) )
				.replace( /\s+/g, ' ' )
				.trim();

		box.querySelectorAll( '[data-eo-seo-preview]' ).forEach( ( field ) => {
			const target = box.querySelector( '[data-eo-seo-serp="' + field.dataset.eoSeoPreview + '"]' );
			if ( target ) {
				field.addEventListener( 'input', () => {
					target.textContent = render( field.value || field.placeholder );
				} );
			}
		} );
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
