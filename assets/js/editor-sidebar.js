/* global wp */
( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
	const { __ } = wp.i18n;
	const { useState, useEffect, useCallback, createElement: el } = wp.element;
	const { Button, SelectControl, Spinner, PanelBody } = wp.components;
	const { useSelect } = wp.data;
	const apiFetch = wp.apiFetch;

	const SCHEMA_TYPES = [
		{ label: 'Article', value: 'Article' },
		{ label: 'BlogPosting', value: 'BlogPosting' },
		{ label: 'FAQPage', value: 'FAQPage' },
		{ label: 'HowTo', value: 'HowTo' },
		{ label: 'Recipe', value: 'Recipe' },
		{ label: 'LocalBusiness', value: 'LocalBusiness' },
		{ label: 'Restaurant', value: 'Restaurant' },
		{ label: 'Organization', value: 'Organization' },
	];

	const BAND_LABELS = {
		red: __( 'Critico', 'citability-score' ),
		yellow: __( 'Da migliorare', 'citability-score' ),
		blue: __( 'Discreto', 'citability-score' ),
		sage: __( 'Buono', 'citability-score' ),
		green: __( 'Eccellente', 'citability-score' ),
	};

	const MACRO_LABELS = {
		coherence: __( 'Coerenza', 'citability-score' ),
		identity: __( 'Identità', 'citability-score' ),
		content: __( 'Contenuti', 'citability-score' ),
		performance: __( 'Prestazioni', 'citability-score' ),
		reputation: __( 'Reputazione', 'citability-score' ),
	};

	function debounce( fn, ms ) {
		let t;
		return function ( ...args ) {
			clearTimeout( t );
			t = setTimeout( () => fn.apply( this, args ), ms );
		};
	}

	function CitabilitySidebar() {
		const postId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );
		const isSaving = useSelect( ( select ) => select( 'core/editor' ).isSavingPost(), [] );

		const [ result, setResult ] = useState( null );
		const [ loading, setLoading ] = useState( false );
		const [ schema, setSchema ] = useState( 'Article' );
		const [ jsonld, setJsonld ] = useState( null );
		const [ savingJsonld, setSavingJsonld ] = useState( false );
		const [ message, setMessage ] = useState( '' );

		const fetchScore = useCallback( () => {
			if ( ! postId ) return;
			setLoading( true );
			apiFetch( { path: `/citability/v1/score/${ postId }` } )
				.then( ( data ) => setResult( data ) )
				.catch( () => setResult( null ) )
				.finally( () => setLoading( false ) );
		}, [ postId ] );

		const fetchJsonld = useCallback( () => {
			if ( ! postId ) return;
			apiFetch( { path: `/citability/v1/jsonld/${ postId }` } )
				.then( ( r ) => setJsonld( r.data ) )
				.catch( () => setJsonld( null ) );
		}, [ postId ] );

		const debouncedScore = useCallback( debounce( fetchScore, 1500 ), [ fetchScore ] );

		useEffect( () => {
			fetchScore();
			fetchJsonld();
		}, [ fetchScore, fetchJsonld ] );

		useEffect( () => {
			if ( ! isSaving ) {
				debouncedScore();
			}
		}, [ isSaving, debouncedScore ] );

		const loadTemplate = () => {
			apiFetch( {
				path: `/citability/v1/jsonld/${ postId }/template?type=${ encodeURIComponent( schema ) }`,
			} ).then( ( r ) => {
				setJsonld( r.data );
				setMessage( __( 'Template caricato. Compila i campi e salva.', 'citability-score' ) );
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
					setMessage( __( 'JSON-LD salvato. Verrà iniettato nel <head> della pagina.', 'citability-score' ) );
					fetchScore();
				} )
				.catch( ( err ) => {
					setMessage( __( 'Errore: ', 'citability-score' ) + ( err.message || 'unknown' ) );
				} )
				.finally( () => setSavingJsonld( false ) );
		};

		const removeJsonld = () => {
			apiFetch( {
				path: `/citability/v1/jsonld/${ postId }`,
				method: 'DELETE',
			} ).then( () => {
				setJsonld( null );
				setMessage( __( 'JSON-LD rimosso.', 'citability-score' ) );
				fetchScore();
			} );
		};

		const renderScore = () => {
			if ( loading && ! result ) {
				return el( 'div', { className: 'citability-sidebar' }, el( Spinner ) );
			}
			if ( ! result ) {
				return el( 'p', null, __( 'Non riesco a calcolare lo score.', 'citability-score' ) );
			}
			const band = result.band || 'red';
			return el(
				'div',
				{ className: 'citability-sidebar' },
				el(
					'div',
					{ className: `citability-score-card citability-band-${ band }` },
					el( 'span', { className: 'citability-score-number' }, result.score ),
					el( 'div', null,
						el( 'div', { style: { fontSize: 12, opacity: 0.8 } }, __( 'su 100', 'citability-score' ) ),
						el( 'strong', null, BAND_LABELS[ band ] || band )
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
								el( 'span', null, MACRO_LABELS[ m ] || m ),
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
						el( 'h3', { style: { fontSize: 13, margin: '12px 0 6px' } }, __( 'Cosa migliorare', 'citability-score' ) ),
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

		const renderJsonldWizard = () => {
			return el(
				PanelBody,
				{ title: __( 'JSON-LD Wizard', 'citability-score' ), initialOpen: false },
				el( SelectControl, {
					label: __( 'Schema markup', 'citability-score' ),
					value: schema,
					options: SCHEMA_TYPES,
					onChange: setSchema,
				} ),
				el(
					'div',
					{ style: { display: 'flex', gap: 8, marginBottom: 8 } },
					el( Button, { variant: 'secondary', onClick: loadTemplate }, __( 'Carica template', 'citability-score' ) ),
					jsonld && el( Button, { variant: 'primary', isBusy: savingJsonld, onClick: saveJsonld }, __( 'Salva nel <head>', 'citability-score' ) ),
					jsonld && el( Button, { variant: 'tertiary', isDestructive: true, onClick: removeJsonld }, __( 'Rimuovi', 'citability-score' ) )
				),
				message && el( 'p', { style: { fontSize: 12 } }, message ),
				jsonld && el(
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
				renderJsonldWizard()
			)
		);
	}

	registerPlugin( 'citability-score', { render: CitabilitySidebar } );
} )( window.wp );
