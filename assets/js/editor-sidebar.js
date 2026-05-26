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
		{ label: __( 'Choose…', 'citability-score' ), value: '' },
		{ label: __( 'Article', 'citability-score' ), value: 'Article' },
		{ label: __( 'Blog post', 'citability-score' ), value: 'BlogPosting' },
		{ label: __( 'News', 'citability-score' ), value: 'NewsArticle' },
		{ label: __( 'FAQ page (questions and answers)', 'citability-score' ), value: 'FAQPage' },
		{ label: __( 'Tutorial guide', 'citability-score' ), value: 'HowTo' },
		{ label: __( 'Recipe', 'citability-score' ), value: 'Recipe' },
		{ label: __( 'Review', 'citability-score' ), value: 'Review' },
		{ label: __( 'Video', 'citability-score' ), value: 'VideoObject' },
		{ label: __( 'Product page', 'citability-score' ), value: 'Product' },
		{ label: __( 'Service', 'citability-score' ), value: 'Service' },
		{ label: __( 'Event', 'citability-score' ), value: 'Event' },
		{ label: __( 'Course / lesson', 'citability-score' ), value: 'Course' },
		{ label: __( 'Job posting', 'citability-score' ), value: 'JobPosting' },
	];

	const BAND_LABELS = {
		red: __( 'Critical', 'citability-score' ),
		yellow: __( 'Needs work', 'citability-score' ),
		blue: __( 'Fair', 'citability-score' ),
		sage: __( 'Good', 'citability-score' ),
		green: __( 'Excellent', 'citability-score' ),
	};

	const MACRO_LABELS = {
		coherence: __( 'Coherence', 'citability-score' ),
		identity: __( 'Identity', 'citability-score' ),
		content: __( 'Content', 'citability-score' ),
		performance: __( 'Performance', 'citability-score' ),
		reputation: __( 'Reputation', 'citability-score' ),
	};

	const MACRO_DESC = {
		coherence: __( 'How clearly the title and subheadings show what the page is about.', 'citability-score' ),
		identity: __( 'How well the HTML code communicates who you are and what you do.', 'citability-score' ),
		content: __( 'How complete, readable and well-organized the text is.', 'citability-score' ),
		performance: __( 'How technically sound and secure the page is.', 'citability-score' ),
		reputation: __( 'How much the content cites reliable sources and links to external sites.', 'citability-score' ),
	};

	// URL piattaforma con UTM per CTA (registrati lato piattaforma: GA4 + signup attribution).
	const PLATFORM_URL = 'https://suite.citationrate.com/';
	const utmLink = ( base, campaign ) =>
		base + ( base.indexOf( '?' ) === -1 ? '?' : '&' ) +
		'utm_source=wp_plugin&utm_medium=widget&utm_campaign=' + campaign;

	// --- JSON-LD guided form helpers -------------------------------------
	// Etichette in lingua utente per le chiavi schema.org (mai mostrate grezze).
	const FIELD_LABELS = {
		headline: __( 'Title', 'citability-score' ),
		name: __( 'Name', 'citability-score' ),
		title: __( 'Title', 'citability-score' ),
		description: __( 'Description', 'citability-score' ),
		reviewBody: __( 'Review text', 'citability-score' ),
		author: __( 'Author', 'citability-score' ),
		datePublished: __( 'Publication date', 'citability-score' ),
		dateModified: __( 'Last updated', 'citability-score' ),
		datePosted: __( 'Publication date', 'citability-score' ),
		uploadDate: __( 'Upload date', 'citability-score' ),
		startDate: __( 'Start date', 'citability-score' ),
		endDate: __( 'End date', 'citability-score' ),
		image: __( 'Image (URL)', 'citability-score' ),
		thumbnailUrl: __( 'Thumbnail (URL)', 'citability-score' ),
		contentUrl: __( 'Video URL', 'citability-score' ),
		embedUrl: __( 'Embed URL', 'citability-score' ),
		publisher: __( 'Publisher', 'citability-score' ),
		mainEntityOfPage: __( 'Page URL', 'citability-score' ),
		recipeIngredient: __( 'Ingredients', 'citability-score' ),
		recipeInstructions: __( 'Recipe steps', 'citability-score' ),
		step: __( 'Steps', 'citability-score' ),
		text: __( 'Text', 'citability-score' ),
		mainEntity: __( 'Questions and answers', 'citability-score' ),
		acceptedAnswer: __( 'Answer', 'citability-score' ),
		brand: __( 'Brand', 'citability-score' ),
		offers: __( 'Offer / price', 'citability-score' ),
		price: __( 'Price', 'citability-score' ),
		priceCurrency: __( 'Currency', 'citability-score' ),
		availability: __( 'Availability', 'citability-score' ),
		url: __( 'URL', 'citability-score' ),
		telephone: __( 'Phone', 'citability-score' ),
		priceRange: __( 'Price range', 'citability-score' ),
		address: __( 'Address', 'citability-score' ),
		streetAddress: __( 'Street', 'citability-score' ),
		addressLocality: __( 'City', 'citability-score' ),
		addressRegion: __( 'Province/Region', 'citability-score' ),
		postalCode: __( 'Postal code', 'citability-score' ),
		addressCountry: __( 'Country', 'citability-score' ),
		serviceType: __( 'Service type', 'citability-score' ),
		areaServed: __( 'Area served', 'citability-score' ),
		provider: __( 'Provider', 'citability-score' ),
		itemReviewed: __( 'What you\'re reviewing', 'citability-score' ),
		reviewRating: __( 'Rating', 'citability-score' ),
		ratingValue: __( 'Rating', 'citability-score' ),
		bestRating: __( 'Max rating', 'citability-score' ),
		worstRating: __( 'Min rating', 'citability-score' ),
		employmentType: __( 'Contract type', 'citability-score' ),
		hiringOrganization: __( 'Company', 'citability-score' ),
		jobLocation: __( 'Job location', 'citability-score' ),
		location: __( 'Location', 'citability-score' ),
		eventStatus: __( 'Event status', 'citability-score' ),
		applicationCategory: __( 'App category', 'citability-score' ),
		operatingSystem: __( 'Operating system', 'citability-score' ),
		sameAs: __( 'Profiles / links', 'citability-score' ),
		logo: __( 'Logo (URL)', 'citability-score' ),
	};
	const FIELD_PH = {
		price: __( 'e.g. 29.90', 'citability-score' ),
		ratingValue: __( 'e.g. 4.5', 'citability-score' ),
		startDate: __( 'e.g. 2026-06-15 20:00', 'citability-score' ),
		endDate: __( 'e.g. 2026-06-15 23:00', 'citability-score' ),
		priceCurrency: __( 'EUR', 'citability-score' ),
		telephone: __( 'e.g. +39 02 1234567', 'citability-score' ),
		employmentType: __( 'e.g. FULL_TIME', 'citability-score' ),
		serviceType: __( 'e.g. Tax consulting', 'citability-score' ),
		applicationCategory: __( 'e.g. BusinessApplication', 'citability-score' ),
		operatingSystem: __( 'e.g. iOS, Android, Web', 'citability-score' ),
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
		if ( parentType === 'Question' && key === 'name' ) return __( 'Question', 'citability-score' );
		if ( parentType === 'Answer' && key === 'text' ) return __( 'Answer', 'citability-score' );
		if ( parentType === 'HowToStep' && key === 'text' ) return __( 'Step description', 'citability-score' );
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
				.then( ( data ) => setResult( data ) )
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
				setMessage( __( 'Fill in the fields and save.', 'citability-score' ) );
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
					setMessage( __( 'JSON-LD saved. It will be injected into the page <head>.', 'citability-score' ) );
					runScore();
				} )
				.catch( ( err ) => {
					setMessage( __( 'Error: ', 'citability-score' ) + ( err.message || 'unknown' ) );
				} )
				.finally( () => setSavingJsonld( false ) );
		};

		const removeJsonld = () => {
			apiFetch( {
				path: `/citability/v1/jsonld/${ postId }`,
				method: 'DELETE',
			} ).then( () => {
				setJsonld( null );
				setMessage( __( 'JSON-LD removed.', 'citability-score' ) );
				runScore();
			} );
		};

		const renderScore = () => {
			if ( loading && ! result ) {
				return el( 'div', { className: 'citability-sidebar' }, el( Spinner ) );
			}
			if ( ! result ) {
				return el( 'p', null, __( 'Can\'t calculate the score.', 'citability-score' ) );
			}
			const band = result.band || 'red';
			return el(
				'div',
				{ className: 'citability-sidebar' },
				el(
					'div',
					{ className: `citability-score-card citability-band-${ band }` },
					el(
						Tooltip,
						{ text: __( 'The Citability Score measures how citable your brand is by conversational AI engines', 'citability-score' ) },
						el( 'span', { className: 'citability-info', tabIndex: 0, role: 'note', 'aria-label': __( 'What is the Citability Score', 'citability-score' ) }, 'i' )
					),
					el( 'span', { className: 'citability-score-number' }, result.score ),
					el( 'div', null,
						el( 'div', { style: { fontSize: 12, opacity: 0.8 } }, __( 'out of 100', 'citability-score' ) ),
						el( 'strong', null, BAND_LABELS[ band ] || band ),
						result.meta && el( 'div', { className: 'citability-params' },
							`${ result.meta.params_checked } / ${ result.meta.params_total } ` + __( 'parameters', 'citability-score' )
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
						el( 'h3', { style: { fontSize: 13, margin: '12px 0 6px' } }, __( 'What to improve', 'citability-score' ) ),
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
				el( 'h3', { className: 'citability-cr-title' }, __( 'Citation Rate', 'citability-score' ) ),
				el(
					'div',
					{ className: 'citability-cr-figure' },
					el( 'div', { className: 'citability-cr-big' }, `~${ rate }%` ),
					el(
						'p',
						{ className: 'citability-cr-note' },
						__( 'Out of 10 queries in your industry you\'d be cited about', 'citability-score' ) +
							` ${ perTen } ` +
							__( 'times. Discover your real citations with the AI Visibility Index (AVI).', 'citability-score' )
					)
				),
				el(
					'a',
					{
						className: 'citability-cr-cta',
						href: aviUrl,
						target: '_blank',
						rel: 'noopener noreferrer',
					},
					__( 'Click here to discover your Citation Rate', 'citability-score' )
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
					el( 'strong', null, __( 'This is a partial on-page score.', 'citability-score' ) ),
					' ' + __( 'It only analyzes this page\'s content. Run the full audit for free on the CitationRate platform to make your brand citable by AI.', 'citability-score' )
				),
				el( 'a', {
					className: 'citability-upgrade-cta',
					href: utmLink( PLATFORM_URL, 'complete_score' ),
					target: '_blank',
					rel: 'noopener noreferrer',
				}, __( 'Complete your score for free', 'citability-score' ) )
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
					}, __( '+ Add', 'citability-score' ) )
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
				{ title: __( 'Help AI understand this page', 'citability-score' ), initialOpen: false },
				el( SelectControl, {
					label: __( 'What is this page about?', 'citability-score' ),
					value: schema,
					options: SCHEMA_TYPES,
					onChange: ( v ) => {
						setSchema( v );
						if ( v ) {
							loadTemplate( v );
						}
					},
				} ),
				jsonld && el(
					'div',
					{ style: { display: 'flex', gap: 8, marginBottom: 8 } },
					el( Button, { variant: 'primary', isBusy: savingJsonld, onClick: saveJsonld }, __( 'Save to <head>', 'citability-score' ) ),
					el( Button, { variant: 'tertiary', isDestructive: true, onClick: removeJsonld }, __( 'Remove', 'citability-score' ) )
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
				}, showRaw ? __( 'Hide JSON', 'citability-score' ) : __( 'Show JSON (advanced)', 'citability-score' ) ),
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
				__( 'Citability Score', 'citability-score' )
			),
			el(
				PluginSidebar,
				{
					name: 'citability-sidebar',
					title: __( 'Citability Score', 'citability-score' ),
					icon: 'chart-area',
				},
				renderScore(),
				renderUpgrade(),
				renderCitationRate(),
				renderJsonldWizard(),
				el( 'p', { className: 'citability-powered' },
					__( 'powered by', 'citability-score' ) + ' ',
					el( 'a', {
						href: utmLink( PLATFORM_URL, 'powered_by' ),
						target: '_blank',
						rel: 'noopener noreferrer',
					}, 'CitationRate' )
				)
			)
		);
	}

	registerPlugin( 'citability-score', { render: CitabilitySidebar } );
} )( window.wp );
