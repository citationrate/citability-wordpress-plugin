/* global wp */
( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
	const { __ } = wp.i18n;
	const { useState, useEffect, useCallback, createElement: el } = wp.element;
	const { Button, SelectControl, Spinner, PanelBody, Tooltip, TextControl } = wp.components;
	const { useSelect } = wp.data;
	const apiFetch = wp.apiFetch;

	// Tipi di CONTENUTO/PAGINA — risposta naturale a "Di cosa parla questa pagina?".
	// Le entità di settore (Restaurant, MedicalClinic, ...) restano nel backend ma
	// non sono più offerte qui: descrivono l'attività, non il contenuto della pagina.
	const SCHEMA_TYPES = [
		{ label: __( 'Choose…', 'citationrate-ai-visibility' ), value: '' },
		{ label: __( 'Article', 'citationrate-ai-visibility' ), value: 'Article' },
		{ label: __( 'Blog post', 'citationrate-ai-visibility' ), value: 'BlogPosting' },
		{ label: __( 'News', 'citationrate-ai-visibility' ), value: 'NewsArticle' },
		{ label: __( 'FAQ page (questions and answers)', 'citationrate-ai-visibility' ), value: 'FAQPage' },
		{ label: __( 'Tutorial guide', 'citationrate-ai-visibility' ), value: 'HowTo' },
		{ label: __( 'Recipe', 'citationrate-ai-visibility' ), value: 'Recipe' },
		{ label: __( 'Review', 'citationrate-ai-visibility' ), value: 'Review' },
		{ label: __( 'Video', 'citationrate-ai-visibility' ), value: 'VideoObject' },
		{ label: __( 'Product page', 'citationrate-ai-visibility' ), value: 'Product' },
		{ label: __( 'Service', 'citationrate-ai-visibility' ), value: 'Service' },
		{ label: __( 'Event', 'citationrate-ai-visibility' ), value: 'Event' },
		{ label: __( 'Course / lesson', 'citationrate-ai-visibility' ), value: 'Course' },
		{ label: __( 'Job posting', 'citationrate-ai-visibility' ), value: 'JobPosting' },
	];

	const BAND_LABELS = {
		red: __( 'Critical', 'citationrate-ai-visibility' ),
		yellow: __( 'Needs work', 'citationrate-ai-visibility' ),
		blue: __( 'Fair', 'citationrate-ai-visibility' ),
		sage: __( 'Good', 'citationrate-ai-visibility' ),
		green: __( 'Excellent', 'citationrate-ai-visibility' ),
	};

	const MACRO_LABELS = {
		coherence: __( 'Coherence', 'citationrate-ai-visibility' ),
		identity: __( 'Identity', 'citationrate-ai-visibility' ),
		content: __( 'Content', 'citationrate-ai-visibility' ),
		performance: __( 'Performance', 'citationrate-ai-visibility' ),
		reputation: __( 'Reputation', 'citationrate-ai-visibility' ),
	};

	const MACRO_DESC = {
		coherence: __( 'How clearly the title and subheadings show what the page is about.', 'citationrate-ai-visibility' ),
		identity: __( 'How well the HTML code communicates who you are and what you do.', 'citationrate-ai-visibility' ),
		content: __( 'How complete, readable and well-organized the text is.', 'citationrate-ai-visibility' ),
		performance: __( 'How technically sound and secure the page is.', 'citationrate-ai-visibility' ),
		reputation: __( 'How much the content cites reliable sources and links to external sites.', 'citationrate-ai-visibility' ),
	};

	// URL piattaforma con UTM per CTA (registrati lato piattaforma: GA4 + signup attribution).
	const PLATFORM_URL = 'https://suite.citationrate.com/';
	const utmLink = ( base, campaign ) =>
		base + ( base.indexOf( '?' ) === -1 ? '?' : '&' ) +
		'utm_source=wp_plugin&utm_medium=widget&utm_campaign=' + campaign;

	// --- Opt-in anonymous telemetry (see readme "Privacy") ----------------
	// Fires ONLY when the site owner enabled the toggle (default off). Sends an
	// allow-listed event + coarse buckets to the plugin's OWN REST route, which
	// forwards it server-side. No PII, no URL, no page content ever leaves the
	// site; the visitor's browser never contacts CitationRate.
	const TELEMETRY_ON = !! ( window.CitationRateWidget && window.CitationRateWidget.telemetry );
	const _trackedOnce = {};
	const scoreBand = ( s ) => {
		s = Number( s ) || 0;
		if ( s <= 30 ) return '0-30';
		if ( s <= 50 ) return '31-50';
		if ( s <= 70 ) return '51-70';
		if ( s <= 85 ) return '71-85';
		return '86-100';
	};
	const track = ( event, props, once ) => {
		if ( ! TELEMETRY_ON ) return;
		if ( once ) {
			if ( _trackedOnce[ event ] ) return;
			_trackedOnce[ event ] = true;
		}
		try {
			apiFetch( {
				path: '/citability/v1/telemetry',
				method: 'POST',
				data: { event: event, props: props || {} },
			} ).catch( () => {} );
		} catch ( e ) {}
	};

	// --- JSON-LD guided form helpers -------------------------------------
	// Etichette in lingua utente per le chiavi schema.org (mai mostrate grezze).
	const FIELD_LABELS = {
		headline: __( 'Title', 'citationrate-ai-visibility' ),
		name: __( 'Name', 'citationrate-ai-visibility' ),
		title: __( 'Title', 'citationrate-ai-visibility' ),
		description: __( 'Description', 'citationrate-ai-visibility' ),
		reviewBody: __( 'Review text', 'citationrate-ai-visibility' ),
		author: __( 'Author', 'citationrate-ai-visibility' ),
		datePublished: __( 'Publication date', 'citationrate-ai-visibility' ),
		dateModified: __( 'Last updated', 'citationrate-ai-visibility' ),
		datePosted: __( 'Publication date', 'citationrate-ai-visibility' ),
		uploadDate: __( 'Upload date', 'citationrate-ai-visibility' ),
		startDate: __( 'Start date', 'citationrate-ai-visibility' ),
		endDate: __( 'End date', 'citationrate-ai-visibility' ),
		image: __( 'Image (URL)', 'citationrate-ai-visibility' ),
		thumbnailUrl: __( 'Thumbnail (URL)', 'citationrate-ai-visibility' ),
		contentUrl: __( 'Video URL', 'citationrate-ai-visibility' ),
		embedUrl: __( 'Embed URL', 'citationrate-ai-visibility' ),
		publisher: __( 'Publisher', 'citationrate-ai-visibility' ),
		mainEntityOfPage: __( 'Page URL', 'citationrate-ai-visibility' ),
		recipeIngredient: __( 'Ingredients', 'citationrate-ai-visibility' ),
		recipeInstructions: __( 'Recipe steps', 'citationrate-ai-visibility' ),
		step: __( 'Steps', 'citationrate-ai-visibility' ),
		text: __( 'Text', 'citationrate-ai-visibility' ),
		mainEntity: __( 'Questions and answers', 'citationrate-ai-visibility' ),
		acceptedAnswer: __( 'Answer', 'citationrate-ai-visibility' ),
		brand: __( 'Brand', 'citationrate-ai-visibility' ),
		offers: __( 'Offer / price', 'citationrate-ai-visibility' ),
		price: __( 'Price', 'citationrate-ai-visibility' ),
		priceCurrency: __( 'Currency', 'citationrate-ai-visibility' ),
		availability: __( 'Availability', 'citationrate-ai-visibility' ),
		url: __( 'URL', 'citationrate-ai-visibility' ),
		telephone: __( 'Phone', 'citationrate-ai-visibility' ),
		priceRange: __( 'Price range', 'citationrate-ai-visibility' ),
		address: __( 'Address', 'citationrate-ai-visibility' ),
		streetAddress: __( 'Street', 'citationrate-ai-visibility' ),
		addressLocality: __( 'City', 'citationrate-ai-visibility' ),
		addressRegion: __( 'Province/Region', 'citationrate-ai-visibility' ),
		postalCode: __( 'Postal code', 'citationrate-ai-visibility' ),
		addressCountry: __( 'Country', 'citationrate-ai-visibility' ),
		serviceType: __( 'Service type', 'citationrate-ai-visibility' ),
		areaServed: __( 'Area served', 'citationrate-ai-visibility' ),
		provider: __( 'Provider', 'citationrate-ai-visibility' ),
		itemReviewed: __( 'What you\'re reviewing', 'citationrate-ai-visibility' ),
		reviewRating: __( 'Rating', 'citationrate-ai-visibility' ),
		ratingValue: __( 'Rating', 'citationrate-ai-visibility' ),
		bestRating: __( 'Max rating', 'citationrate-ai-visibility' ),
		worstRating: __( 'Min rating', 'citationrate-ai-visibility' ),
		employmentType: __( 'Contract type', 'citationrate-ai-visibility' ),
		hiringOrganization: __( 'Company', 'citationrate-ai-visibility' ),
		jobLocation: __( 'Job location', 'citationrate-ai-visibility' ),
		location: __( 'Location', 'citationrate-ai-visibility' ),
		eventStatus: __( 'Event status', 'citationrate-ai-visibility' ),
		applicationCategory: __( 'App category', 'citationrate-ai-visibility' ),
		operatingSystem: __( 'Operating system', 'citationrate-ai-visibility' ),
		sameAs: __( 'Profiles / links', 'citationrate-ai-visibility' ),
		logo: __( 'Logo (URL)', 'citationrate-ai-visibility' ),
	};
	const FIELD_PH = {
		price: __( 'e.g. 29.90', 'citationrate-ai-visibility' ),
		ratingValue: __( 'e.g. 4.5', 'citationrate-ai-visibility' ),
		startDate: __( 'e.g. 2026-06-15 20:00', 'citationrate-ai-visibility' ),
		endDate: __( 'e.g. 2026-06-15 23:00', 'citationrate-ai-visibility' ),
		priceCurrency: __( 'EUR', 'citationrate-ai-visibility' ),
		telephone: __( 'e.g. +39 02 1234567', 'citationrate-ai-visibility' ),
		employmentType: __( 'e.g. FULL_TIME', 'citationrate-ai-visibility' ),
		serviceType: __( 'e.g. Tax consulting', 'citationrate-ai-visibility' ),
		applicationCategory: __( 'e.g. BusinessApplication', 'citationrate-ai-visibility' ),
		operatingSystem: __( 'e.g. iOS, Android, Web', 'citationrate-ai-visibility' ),
	};

	// Immutable set at a key/index path.
	function setIn( obj, path, value ) {
		if ( ! path.length ) return value;
		const head = path[ 0 ];
		const clone = Array.isArray( obj ) ? obj.slice() : Object.assign( {}, obj );
		clone[ head ] = setIn( obj ? obj[ head ] : undefined, path.slice( 1 ), value );
		return clone;
	}
	// Empty copy of a value, keeping the schema @type markers.
	function blankLike( item ) {
		if ( typeof item === 'string' || typeof item === 'number' ) return '';
		if ( Array.isArray( item ) ) return item.length ? [ blankLike( item[ 0 ] ) ] : [ '' ];
		if ( item && typeof item === 'object' ) {
			const o = {};
			Object.keys( item ).forEach( ( k ) => { o[ k ] = k === '@type' ? item[ k ] : blankLike( item[ k ] ); } );
			return o;
		}
		return '';
	}
	function contextLabel( parentType, key ) {
		if ( parentType === 'Question' && key === 'name' ) return __( 'Question', 'citationrate-ai-visibility' );
		if ( parentType === 'Answer' && key === 'text' ) return __( 'Answer', 'citationrate-ai-visibility' );
		if ( parentType === 'HowToStep' && key === 'text' ) return __( 'Step description', 'citationrate-ai-visibility' );
		return FIELD_LABELS[ key ] || key;
	}

	function CitabilitySidebar() {
		const postId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );
		// Live editor content — re-scored on every edit (debounced), no save needed.
		const editedContent = useSelect( ( select ) => select( 'core/editor' ).getEditedPostContent(), [] );
		const editedTitle = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'title' ), [] );
		const editedExcerpt = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'excerpt' ), [] );

		const [ result, setResult ] = useState( null );
		const [ loading, setLoading ] = useState( false );
		const [ schema, setSchema ] = useState( '' );
		const [ jsonld, setJsonld ] = useState( null );
		const [ savingJsonld, setSavingJsonld ] = useState( false );
		const [ message, setMessage ] = useState( '' );
		const [ showRaw, setShowRaw ] = useState( false );

		// POST the live editor content so the score reflects unsaved edits.
		const runScore = useCallback( () => {
			if ( ! postId ) return;
			setLoading( true );
			apiFetch( {
				path: `/citability/v1/score/${ postId }`,
				method: 'POST',
				data: { content: editedContent, title: editedTitle, excerpt: editedExcerpt },
			} )
				.then( ( data ) => {
					setResult( data );
					track( 'widget_loaded', { score_band: scoreBand( data && data.score ) }, true );
				} )
				.catch( () => setResult( null ) )
				.finally( () => setLoading( false ) );
		}, [ postId, editedContent, editedTitle, editedExcerpt ] );

		const fetchJsonld = useCallback( () => {
			if ( ! postId ) return;
			apiFetch( { path: `/citability/v1/jsonld/${ postId }` } )
				.then( ( r ) => {
					setJsonld( r.data );
					// Keep the dropdown in sync with any markup already saved.
					if ( r.data && r.data[ '@type' ] ) {
						setSchema( r.data[ '@type' ] );
					}
				} )
				.catch( () => setJsonld( null ) );
		}, [ postId ] );

		useEffect( () => {
			fetchJsonld();
		}, [ fetchJsonld ] );

		// Debounced live re-score: clears the pending timer on each keystroke and
		// fires 800ms after the user stops editing. runScore changes whenever the
		// edited content changes, so the cleanup gives us a true trailing debounce.
		useEffect( () => {
			if ( ! postId ) return;
			const handle = setTimeout( runScore, 800 );
			return () => clearTimeout( handle );
		}, [ runScore, postId ] );

		const loadTemplate = ( type ) => {
			const t = type || schema;
			if ( ! t || ! postId ) return;
			apiFetch( {
				path: `/citability/v1/jsonld/${ postId }/template?type=${ encodeURIComponent( t ) }`,
			} ).then( ( r ) => {
				setJsonld( r.data );
				setMessage( __( 'Fill in the fields and save.', 'citationrate-ai-visibility' ) );
			} );
		};

		const saveJsonld = () => {
			if ( ! jsonld ) return;
			setSavingJsonld( true );
			setMessage( '' );
			apiFetch( {
				path: `/citability/v1/jsonld/${ postId }`,
				method: 'POST',
				data: { data: jsonld },
			} )
				.then( () => {
					setMessage( __( 'JSON-LD saved. It will be injected into the page <head>.', 'citationrate-ai-visibility' ) );
					track( 'jsonld_saved', { schema: jsonld && jsonld[ '@type' ] } );
					runScore();
				} )
				.catch( ( err ) => {
					setMessage( __( 'Error: ', 'citationrate-ai-visibility' ) + ( err.message || 'unknown' ) );
				} )
				.finally( () => setSavingJsonld( false ) );
		};

		const removeJsonld = () => {
			apiFetch( {
				path: `/citability/v1/jsonld/${ postId }`,
				method: 'DELETE',
			} ).then( () => {
				setJsonld( null );
				setMessage( __( 'JSON-LD removed.', 'citationrate-ai-visibility' ) );
				track( 'jsonld_removed', {} );
				runScore();
			} );
		};

		const renderScore = () => {
			if ( loading && ! result ) {
				return el( 'div', { className: 'citability-sidebar' }, el( Spinner ) );
			}
			if ( ! result ) {
				return el( 'p', null, __( 'Can\'t calculate the score.', 'citationrate-ai-visibility' ) );
			}
			const band = result.band || 'red';
			return el(
				'div',
				{ className: 'citability-sidebar' },
				el(
					'div',
					{ className: `citationrate-ai-visibility-card citability-band-${ band }` },
					el(
						Tooltip,
						{ text: __( 'The Citability Score measures how citable your brand is by conversational AI engines', 'citationrate-ai-visibility' ) },
						el( 'span', { className: 'citability-info', tabIndex: 0, role: 'note', 'aria-label': __( 'What is the Citability Score', 'citationrate-ai-visibility' ) }, 'i' )
					),
					el( 'span', { className: 'citationrate-ai-visibility-number' }, result.score ),
					el( 'div', null,
						el( 'div', { style: { fontSize: 12, opacity: 0.8 } }, __( 'out of 100', 'citationrate-ai-visibility' ) ),
						el( 'strong', null, BAND_LABELS[ band ] || band ),
						result.meta && el( 'div', { className: 'citability-params' },
							`${ result.meta.params_checked } / ${ result.meta.params_total } ` + __( 'parameters', 'citationrate-ai-visibility' )
						)
					)
				),
				el(
					'div',
					{ className: 'citability-macros' },
					Object.keys( result.macros ).map( ( m ) =>
						el(
							'div',
							{ key: m, className: 'citability-macro' },
							el(
								'div',
								{ className: 'citability-macro-label' },
								el( 'span', null,
									( MACRO_LABELS[ m ] || m ) + ' ',
									el(
										Tooltip,
										{ text: MACRO_DESC[ m ] || '' },
										el( 'span', { className: 'citability-macro-i', tabIndex: 0, role: 'note' }, 'i' )
									)
								),
								el( 'span', null, `${ result.macros[ m ] }%` )
							),
							el(
								'div',
								{ className: 'citability-macro-bar' },
								el( 'div', { className: 'citability-macro-fill', style: { width: `${ result.macros[ m ] }%` } } )
							)
						)
					)
				),
				result.suggestions.length > 0 &&
					el(
						'div',
						{ className: 'citability-suggestions' },
						el( 'h3', { style: { fontSize: 13, margin: '12px 0 6px' } }, __( 'What to improve', 'citationrate-ai-visibility' ) ),
						result.suggestions.slice( 0, 8 ).map( ( s, i ) =>
							el(
								'div',
								{ key: i, className: `citability-suggestion ${ s.severity }` },
								el( 'strong', null, s.label + ' · ' ),
								s.message
							)
						)
					)
			);
		};

		const renderCitationRate = () => {
			if ( ! result ) return null;
			const score = Number( result.score ) || 0;
			// Figurative model built on the real Citability Score. A linear
			// rate = score would overstate reality (a citability of 80 does NOT
			// mean an 80% citation rate). We dampen it with a convex curve —
			// score² / 100 — so weak pages project a low rate and only truly
			// citable pages approach high values (50→25%, 70→49%, 90→81%).
			// This tracks real-world citation rates far more honestly. The true
			// value can only be measured by querying the models, which AVI does.
			const rate = Math.round( ( score * score ) / 100 );
			const perTen = Math.round( rate / 10 );
			const aviUrl = utmLink( 'https://avi.citationrate.com/', 'citation_rate' );
			return el(
				'div',
				{ className: 'citability-cr' },
				el( 'h3', { className: 'citability-cr-title' }, __( 'Citation Rate', 'citationrate-ai-visibility' ) ),
				el(
					'div',
					{ className: 'citability-cr-figure' },
					el( 'div', { className: 'citability-cr-big' }, `~${ rate }%` ),
					el(
						'p',
						{ className: 'citability-cr-note' },
						__( 'Out of 10 queries in your industry you\'d be cited about', 'citationrate-ai-visibility' ) +
							` ${ perTen } ` +
							__( 'times. Discover your real citations with the AI Visibility Index (AVI).', 'citationrate-ai-visibility' )
					)
				),
				el(
					'a',
					{
						className: 'citability-cr-cta',
						href: aviUrl,
						target: '_blank',
						rel: 'noopener noreferrer',
						onClick: () => track( 'cta_clicked', { cta: 'avi' } ),
					},
					__( 'Click here to discover your Citation Rate', 'citationrate-ai-visibility' )
				)
			);
		};

		// Primary funnel CTA: this lite score is partial — full audit on the platform.
		const renderUpgrade = () => {
			if ( ! result ) return null;
			return el(
				'div',
				{ className: 'citability-upgrade' },
				el( 'p', { className: 'citability-upgrade-text' },
					el( 'strong', null, __( 'This is a partial on-page score.', 'citationrate-ai-visibility' ) ),
					' ' + __( 'It only analyzes this page\'s content. Run the full audit for free on the CitationRate platform to make your brand citable by AI.', 'citationrate-ai-visibility' )
				),
				el( 'a', {
					className: 'citability-upgrade-cta',
					href: utmLink( PLATFORM_URL, 'complete_score' ),
					target: '_blank',
					rel: 'noopener noreferrer',
					onClick: () => track( 'cta_clicked', { cta: 'complete_score' } ),
				}, __( 'Complete your score for free', 'citationrate-ai-visibility' ) )
			);
		};

		// Recursive guided editor: turns the JSON-LD object into labelled inputs
		// so the user fills plain fields instead of editing raw JSON.
		const renderField = ( key, value, path, labelOverride ) => {
			if ( key === '@context' || key === '@type' ) return null;
			const lbl = labelOverride || FIELD_LABELS[ key ] || key;
			const rowKey = path.join( '.' );

			if ( typeof value === 'string' || typeof value === 'number' ) {
				return el( TextControl, {
					key: rowKey,
					label: lbl,
					value: String( value ),
					placeholder: FIELD_PH[ key ] || '',
					onChange: ( v ) => setJsonld( setIn( jsonld, path, v ) ),
				} );
			}

			if ( Array.isArray( value ) ) {
				const rows = value.map( ( item, i ) => {
					const itemPath = path.concat( i );
					const removeBtn = el( Button, {
						key: 'rm', variant: 'tertiary', isDestructive: true, isSmall: true,
						onClick: () => {
							const next = value.slice();
							next.splice( i, 1 );
							setJsonld( setIn( jsonld, path, next ) );
						},
					}, '✕' );
					if ( typeof item === 'string' || typeof item === 'number' ) {
						return el( 'div', { key: i, className: 'citability-field-row' },
							el( TextControl, {
								label: '', value: String( item ),
								onChange: ( v ) => setJsonld( setIn( jsonld, itemPath, v ) ),
							} ),
							removeBtn
						);
					}
					return el( 'div', { key: i, className: 'citability-field-group' },
						renderObjectFields( item, itemPath ),
						removeBtn
					);
				} );
				return el( 'div', { key: rowKey, className: 'citability-field-block' },
					el( 'div', { className: 'citability-field-label' }, lbl ),
					...rows,
					el( Button, {
						key: 'add', variant: 'secondary', isSmall: true,
						onClick: () => setJsonld( setIn( jsonld, path, value.concat( [ blankLike( value.length ? value[ 0 ] : '' ) ] ) ) ),
					}, __( '+ Add', 'citationrate-ai-visibility' ) )
				);
			}

			if ( value && typeof value === 'object' ) {
				return el( 'div', { key: rowKey, className: 'citability-field-block' },
					el( 'div', { className: 'citability-field-label' }, lbl ),
					renderObjectFields( value, path )
				);
			}
			return null;
		};

		const renderObjectFields = ( obj, path ) => {
			const parentType = obj && obj[ '@type' ];
			return Object.keys( obj )
				.filter( ( k ) => k !== '@context' && k !== '@type' )
				.map( ( k ) => renderField( k, obj[ k ], path.concat( k ), contextLabel( parentType, k ) ) );
		};

		const renderJsonldWizard = () => {
			return el(
				PanelBody,
				{
					title: __( 'Help AI understand this page', 'citationrate-ai-visibility' ),
					initialOpen: false,
					onToggle: ( open ) => { if ( open ) track( 'wizard_opened', {}, true ); },
				},
				el( SelectControl, {
					label: __( 'What is this page about?', 'citationrate-ai-visibility' ),
					value: schema,
					options: SCHEMA_TYPES,
					onChange: ( v ) => {
						setSchema( v );
						if ( v ) {
							loadTemplate( v );
							track( 'schema_selected', { schema: v } );
						}
					},
				} ),
				jsonld && el(
					'div',
					{ style: { display: 'flex', gap: 8, marginBottom: 8 } },
					el( Button, { variant: 'primary', isBusy: savingJsonld, onClick: saveJsonld }, __( 'Save to <head>', 'citationrate-ai-visibility' ) ),
					el( Button, { variant: 'tertiary', isDestructive: true, onClick: removeJsonld }, __( 'Remove', 'citationrate-ai-visibility' ) )
				),
				message && el( 'p', { style: { fontSize: 12 } }, message ),
				jsonld && el(
					'div',
					{ className: 'citability-jsonld-form' },
					...renderObjectFields( jsonld, [] )
				),
				jsonld && el( Button, {
					variant: 'link', isSmall: true,
					style: { marginTop: 8 },
					onClick: () => setShowRaw( ! showRaw ),
				}, showRaw ? __( 'Hide JSON', 'citationrate-ai-visibility' ) : __( 'Show JSON (advanced)', 'citationrate-ai-visibility' ) ),
				jsonld && showRaw && el(
					'pre',
					{ className: 'citability-jsonld-block' },
					JSON.stringify( jsonld, null, 2 )
				)
			);
		};

		return el(
			'div',
			null,
			el(
				PluginSidebarMoreMenuItem,
				{ target: 'citability-sidebar' },
				__( 'Citability Score', 'citationrate-ai-visibility' )
			),
			el(
				PluginSidebar,
				{
					name: 'citability-sidebar',
					title: __( 'Citability Score', 'citationrate-ai-visibility' ),
					icon: 'chart-area',
				},
				renderScore(),
				renderUpgrade(),
				renderCitationRate(),
				renderJsonldWizard(),
				el( 'p', { className: 'citability-powered' },
					__( 'powered by', 'citationrate-ai-visibility' ) + ' ',
					el( 'a', {
						href: utmLink( PLATFORM_URL, 'powered_by' ),
						target: '_blank',
						rel: 'noopener noreferrer',
					}, 'CitationRate' )
				)
			)
		);
	}

	registerPlugin( 'citationrate-ai-visibility', { render: CitabilitySidebar } );
} )( window.wp );
