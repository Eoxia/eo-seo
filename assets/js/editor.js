/**
 * EO SEO in the block editor: a coloured badge in the top bar (next to the Settings button)
 * opens a full sidebar, in place of the Block / Page sidebar, with the SEO fields (saved as
 * post metas by the editor itself), the Google preview and the checks (computed by the server
 * from the unsaved values).
 *
 * Plain script, no build step: it only uses the WordPress globals.
 */
( function ( wp, config ) {
	if ( ! wp || ! config ) {
		return;
	}

	const el = wp.element.createElement;
	const { Fragment, useEffect, useState } = wp.element;
	const { __, _n, sprintf } = wp.i18n;
	const { useSelect, useDispatch } = wp.data;
	const editor = wp.editor || {};
	const PluginSidebar = editor.PluginSidebar || ( wp.editPost && wp.editPost.PluginSidebar );
	const PluginSidebarMoreMenuItem = editor.PluginSidebarMoreMenuItem || ( wp.editPost && wp.editPost.PluginSidebarMoreMenuItem );
	const { TextControl, TextareaControl, ToggleControl, Button, Spinner, BaseControl, PanelBody } = wp.components;
	const { MediaUpload, MediaUploadCheck } = wp.blockEditor;

	if ( ! PluginSidebar ) {
		return;
	}

	const GROUPS = {
		main: [ 'title', 'description', 'focus_keyword' ],
		advanced: [ 'noindex', 'canonical' ],
		social: [ 'og_title', 'og_description', 'og_image_id' ],
	};
	const SEVERITIES = [
		[ 'problem', __( 'Problems', 'eo-seo' ) ],
		[ 'improve', __( 'To improve', 'eo-seo' ) ],
		[ 'readability', __( 'Readability', 'eo-seo' ) ],
	];

	/**
	 * Coloured badge of a level.
	 *
	 * @param {Object} props       Props.
	 * @param {string} props.level problem, improve, good, noindex or unknown.
	 * @param {string} props.size  "small" or "large".
	 */
	function Badge( { level, size } ) {
		return el( 'span', {
			className: 'eo-seo-badge eo-seo-badge--' + ( level || 'unknown' ) + ( 'large' === size ? ' eo-seo-badge--large' : '' ),
			'aria-hidden': true,
		} );
	}

	/**
	 * Character counter under a field. It measures only what is typed: an empty field
	 * says that the text comes from the template instead.
	 *
	 * @param {Object} props             Props.
	 * @param {string} props.value       Text typed.
	 * @param {string} props.placeholder Text given by the template when the field is empty.
	 * @param {Object} props.field       Field definition.
	 */
	function Counter( { value, placeholder, field } ) {
		if ( ! field.max ) {
			return null;
		}
		if ( ! value ) {
			return el(
				'div',
				{ className: 'eo-seo-meter eo-seo-meter--auto' },
				el( 'span', { className: 'eo-seo-meter__bar' }, el( 'span', { style: { width: 0 } } ) ),
				el(
					'span',
					{ className: 'eo-seo-meter__text' },
					placeholder
						? __( 'Empty: the template writes it automatically (grey text). Write your own.', 'eo-seo' )
						: __( 'Empty.', 'eo-seo' )
				)
			);
		}
		const length = value.length;
		const ratio = Math.min( 1, length / field.max );
		const state = length < field.min || length > field.max ? 'bad' : 'good';
		return el(
			'div',
			{ className: 'eo-seo-meter eo-seo-meter--' + state },
			el( 'span', { className: 'eo-seo-meter__bar' }, el( 'span', { style: { width: ratio * 100 + '%' } } ) ),
			el(
				'span',
				{ className: 'eo-seo-meter__text' },
				sprintf(
					/* translators: 1: number of characters, 2: minimum, 3: maximum. */
					__( '%1$d characters (recommended: %2$d to %3$d)', 'eo-seo' ),
					length,
					field.min,
					field.max
				)
			)
		);
	}

	/**
	 * One SEO field.
	 *
	 * @param {Object}   props             Props.
	 * @param {string}   props.name        Field name.
	 * @param {Object}   props.field       Field definition.
	 * @param {*}        props.value       Value.
	 * @param {Function} props.onChange    Change handler.
	 * @param {string}   props.placeholder Placeholder (value given by the template).
	 */
	function Field( { name, field, value, onChange, placeholder } ) {
		const common = {
			label: field.label,
			help: field.help,
			__nextHasNoMarginBottom: true,
		};

		if ( 'checkbox' === field.input ) {
			return el( 'div', { className: 'eo-seo-editor-field' }, el( ToggleControl, { ...common, checked: !! value, onChange } ) );
		}

		if ( 'image' === field.input ) {
			return el(
				'div',
				{ className: 'eo-seo-editor-field' },
				el(
					BaseControl,
					{ ...common, id: 'eo-seo-' + name },
					el(
						MediaUploadCheck,
						null,
						el( MediaUpload, {
							allowedTypes: [ 'image' ],
							value,
							onSelect: ( media ) => onChange( media.id ),
							render: ( { open } ) =>
								el(
									'div',
									{ className: 'eo-seo-editor-media' },
									el( Button, { variant: 'secondary', onClick: open }, value ? __( 'Replace the image', 'eo-seo' ) : __( 'Choose an image', 'eo-seo' ) ),
									value ? el( Button, { variant: 'link', isDestructive: true, onClick: () => onChange( 0 ) }, __( 'Remove', 'eo-seo' ) ) : null
								),
						} )
					)
				)
			);
		}

		const Control = 'textarea' === field.input ? TextareaControl : TextControl;
		return el(
			'div',
			{ className: 'eo-seo-editor-field' },
			el( Control, {
				...common,
				value: value || '',
				placeholder: placeholder || '',
				type: 'url' === field.input ? 'url' : undefined,
				onChange,
				rows: 'textarea' === field.input ? 3 : undefined,
				__next40pxDefaultSize: 'textarea' !== field.input ? true : undefined,
			} ),
			el( Counter, { value, placeholder, field } )
		);
	}

	/**
	 * Google result preview.
	 *
	 * @param {Object} props         Props.
	 * @param {Object} props.preview Resolved title, description and address.
	 */
	function Preview( { preview } ) {
		return el(
			'div',
			{ className: 'eo-seo-serp', 'aria-label': __( 'Search result preview', 'eo-seo' ) },
			el( 'span', { className: 'eo-seo-serp__url' }, preview.url ),
			el( 'span', { className: 'eo-seo-serp__title' }, preview.title ),
			el( 'span', { className: 'eo-seo-serp__description' }, preview.description )
		);
	}

	/**
	 * Indicator at the top of the sidebar.
	 *
	 * @param {Object} props        Props.
	 * @param {Object} props.result Analysis, or null while loading.
	 */
	function Summary( { result } ) {
		if ( ! result ) {
			return el( 'div', { className: 'eo-seo-summary' }, el( Spinner ) );
		}
		return el(
			'div',
			{ className: 'eo-seo-summary eo-seo-summary--' + result.level },
			el( Badge, { level: result.level, size: 'large' } ),
			el(
				'div',
				null,
				el( 'strong', { className: 'eo-seo-summary__level' }, config.levels[ result.level ] ),
				'noindex' === result.level
					? el( 'span', { className: 'eo-seo-summary__points' }, __( 'Hidden from search engines: nothing to check.', 'eo-seo' ) )
					: el(
						'span',
						{ className: 'eo-seo-summary__points' },
						sprintf(
							/* translators: 1: number of checks passed, 2: number of checks. */
							__( '%1$d/%2$d points OK', 'eo-seo' ),
							result.passed,
							result.total
						)
					)
			)
		);
	}

	/**
	 * Checks, grouped by severity; passed checks are folded.
	 *
	 * @param {Object} props        Props.
	 * @param {Object} props.result Analysis.
	 */
	function Checks( { result } ) {
		const failed = result.checks.filter( ( check ) => ! check.passed );
		const passed = result.checks.filter( ( check ) => check.passed );
		const item = ( check ) =>
			el(
				'li',
				{ key: check.id, className: 'eo-seo-check eo-seo-check--' + ( check.passed ? 'ok' : check.severity ) },
				el( 'strong', null, check.label ),
				el( 'span', null, check.message )
			);

		return el(
			Fragment,
			null,
			SEVERITIES.map( ( [ severity, label ] ) => {
				const list = failed.filter( ( check ) => check.severity === severity );
				return list.length
					? el( 'div', { key: severity, className: 'eo-seo-checks-group' }, el( 'h3', null, label ), el( 'ul', { className: 'eo-seo-checks' }, list.map( item ) ) )
					: null;
			} ),
			passed.length
				? el(
					'details',
					{ className: 'eo-seo-checks-group eo-seo-checks-group--ok' },
					el(
						'summary',
						null,
						sprintf(
							/* translators: %d: number of checks. */
							_n( '%d point OK', '%d points OK', passed.length, 'eo-seo' ),
							passed.length
						)
					),
					el( 'ul', { className: 'eo-seo-checks' }, passed.map( item ) )
				)
				: null
		);
	}

	/**
	 * The plugin: badge in the top bar and sidebar.
	 */
	function EoSeo() {
		const { postId, title, slug, content, excerpt, meta } = useSelect( ( select ) => {
			const store = select( 'core/editor' );
			return {
				postId: store.getCurrentPostId(),
				title: store.getEditedPostAttribute( 'title' ),
				slug: store.getEditedPostSlug ? store.getEditedPostSlug() : store.getEditedPostAttribute( 'slug' ),
				content: store.getEditedPostContent(),
				excerpt: store.getEditedPostAttribute( 'excerpt' ),
				meta: store.getEditedPostAttribute( 'meta' ) || {},
			};
		}, [] );
		const { editPost } = useDispatch( 'core/editor' );
		const [ result, setResult ] = useState( null );

		// "Edit the SEO" from the admin bar of the site (?eo-seo=open): open the EO SEO sidebar.
		useEffect( () => {
			const url = new URL( window.location.href );
			if ( 'open' !== url.searchParams.get( 'eo-seo' ) ) {
				return;
			}
			const store = wp.data.dispatch( 'core/interface' );
			if ( store && store.enableComplementaryArea ) {
				store.enableComplementaryArea( 'core', 'eo-seo/sidebar' );
			}
			// Not again when the page is reloaded.
			url.searchParams.delete( 'eo-seo' );
			window.history.replaceState( window.history.state, '', url.toString() );
		}, [] );

		const values = {};
		Object.keys( config.fields ).forEach( ( name ) => {
			values[ name ] = meta[ config.fields[ name ].meta ];
		} );
		const valuesKey = JSON.stringify( values );

		useEffect( () => {
			const timer = setTimeout( () => {
				wp.apiFetch( {
					path: config.route + postId,
					method: 'POST',
					data: { title, slug, content, excerpt, fields: values },
				} )
					.then( setResult )
					.catch( () => setResult( null ) );
			}, 800 );
			return () => clearTimeout( timer );
		}, [ postId, title, slug, content, excerpt, valuesKey ] ); // eslint-disable-line react-hooks/exhaustive-deps

		// Before the first analysis, the badge shows the level stored at the last save.
		const level = result ? result.level : meta._eo_seo_status || 'unknown';
		const set = ( name ) => ( value ) => editPost( { meta: { [ config.fields[ name ].meta ]: value } } );
		const placeholders = result ? { title: result.preview.title, description: result.preview.description } : {};
		const fieldsOf = ( names ) =>
			names
				.filter( ( name ) => config.fields[ name ] )
				.map( ( name ) =>
					el( Field, {
						key: name,
						name,
						field: config.fields[ name ],
						value: values[ name ],
						onChange: set( name ),
						placeholder: placeholders[ name ],
					} )
				);

		const icon = el( 'span', { className: 'eo-seo-toolbar-icon' }, el( Badge, { level } ) );
		const label = sprintf(
			/* translators: %s: SEO level (Good, Needs improvement…). */
			__( 'EO SEO: %s', 'eo-seo' ),
			config.levels[ level ] || __( 'Not analyzed yet', 'eo-seo' )
		);

		return el(
			Fragment,
			null,
			PluginSidebarMoreMenuItem ? el( PluginSidebarMoreMenuItem, { target: 'sidebar', icon }, __( 'EO SEO', 'eo-seo' ) ) : null,
			el(
				PluginSidebar,
				{ name: 'sidebar', title: label, icon, className: 'eo-seo-sidebar' },
				el(
					'div',
					{ className: 'eo-seo-sidebar__head' },
					el( Summary, { result } ),
					result ? el( Preview, { preview: result.preview } ) : null
				),
				el( PanelBody, { title: __( 'Search engines', 'eo-seo' ), initialOpen: true }, fieldsOf( GROUPS.main ) ),
				el( PanelBody, { title: __( 'Checks', 'eo-seo' ), initialOpen: true }, result ? el( Checks, { result } ) : el( Spinner ) ),
				el( PanelBody, { title: __( 'Social sharing', 'eo-seo' ), initialOpen: false }, fieldsOf( GROUPS.social ) ),
				el( PanelBody, { title: __( 'Advanced', 'eo-seo' ), initialOpen: false }, fieldsOf( GROUPS.advanced ) )
			)
		);
	}

	// A plugin sidebar is pinned in the top bar by default: the badge sits next to the Settings button.
	wp.plugins.registerPlugin( 'eo-seo', { render: EoSeo } );
} )( window.wp, window.eoSeoEditor );
