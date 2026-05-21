<?php
/**
 * On-Page Citability Scorer (lite, ~18 PID).
 *
 * Calcola un sottoinsieme dei 56 PID del Citability Engine v5 che possono essere
 * verificati on-page senza chiamate esterne (no DFS, no LLM, no OPR).
 *
 * @package CitabilityScore
 */

namespace Citability_Score;

defined( 'ABSPATH' ) || exit;

class On_Page_Scorer {

	const MACROS = array(
		'coherence'   => array( 'P1', 'P2', 'P3', 'P4', 'P5', 'L3', 'L4', 'L5', 'L8' ),
		'identity'    => array( 'P12', 'P13', 'P14', 'P15', 'L1', 'L2', 'L6', 'L7' ),
		'content'     => array( 'P30', 'P31', 'P32', 'P33', 'P34', 'P35', 'L9', 'L10', 'L11', 'L12', 'L13', 'L14', 'L15' ),
		'performance' => array( 'P40', 'P41' ),
		'reputation'  => array( 'P52' ),
	);

	/**
	 * Label user-facing per ogni controllo. I codici interni (P1, P2…) non
	 * devono mai essere esposti nell'UI: usiamo invece nomi descrittivi.
	 */
	public static function labels() {
		return array(
			'P1'  => __( 'Lunghezza titolo', 'citability-score' ),
			'P2'  => __( 'Meta description', 'citability-score' ),
			'P3'  => __( 'Struttura del titolo H1', 'citability-score' ),
			'P4'  => __( 'Gerarchia degli heading', 'citability-score' ),
			'P5'  => __( 'Parola chiave nel titolo', 'citability-score' ),
			'P12' => __( 'Autore visibile', 'citability-score' ),
			'P13' => __( 'Data di pubblicazione', 'citability-score' ),
			'P14' => __( 'Bio autore', 'citability-score' ),
			'P15' => __( 'Schema Organization', 'citability-score' ),
			'P30' => __( 'Lunghezza del contenuto', 'citability-score' ),
			'P31' => __( 'Leggibilità', 'citability-score' ),
			'P32' => __( 'Link interni', 'citability-score' ),
			'P33' => __( 'Link a fonti esterne', 'citability-score' ),
			'P34' => __( 'Descrizioni delle immagini', 'citability-score' ),
			'P35' => __( 'Sezione domande e risposte', 'citability-score' ),
			'P40' => __( 'Connessione sicura', 'citability-score' ),
			'P41' => __( 'URL canonico', 'citability-score' ),
			'P52' => __( 'Citazioni di fonti', 'citability-score' ),
			'L1'  => __( 'Tag Open Graph', 'citability-score' ),
			'L2'  => __( 'Twitter Card', 'citability-score' ),
			'L3'  => __( 'Lingua del sito', 'citability-score' ),
			'L4'  => __( 'Indicizzazione', 'citability-score' ),
			'L5'  => __( 'Versione multilingua', 'citability-score' ),
			'L6'  => __( 'Markup Article completo', 'citability-score' ),
			'L7'  => __( 'Profilo autore esterno', 'citability-score' ),
			'L8'  => __( 'Indice navigabile', 'citability-score' ),
			'L9'  => __( 'Apertura del contenuto', 'citability-score' ),
			'L10' => __( 'Elenchi e bullet', 'citability-score' ),
			'L11' => __( 'Dimensioni delle immagini', 'citability-score' ),
			'L12' => __( 'Forma passiva', 'citability-score' ),
			'L13' => __( 'Lunghezza delle frasi', 'citability-score' ),
			'L14' => __( 'Anno corrente nel testo', 'citability-score' ),
			'L15' => __( 'Lunghezza dei paragrafi', 'citability-score' ),
		);
	}

	public static function score_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'not_found', __( 'Post non trovato.', 'citability-score' ) );
		}

		$context = self::build_context( $post );

		$results = array(
			'P1'  => self::check_title_length( $context ),
			'P2'  => self::check_meta_description( $context ),
			'P3'  => self::check_h1_unique( $context ),
			'P4'  => self::check_heading_hierarchy( $context ),
			'P5'  => self::check_focus_keyword_in_title( $context ),
			'P12' => self::check_author_byline( $context ),
			'P13' => self::check_dates_visible( $context ),
			'P14' => self::check_author_bio( $context ),
			'P15' => self::check_organization_schema( $context ),
			'P30' => self::check_word_count( $context ),
			'P31' => self::check_reading_level( $context ),
			'P32' => self::check_internal_links( $context ),
			'P33' => self::check_outbound_auth_links( $context ),
			'P34' => self::check_image_alt( $context ),
			'P35' => self::check_faq_pattern( $context ),
			'P40' => self::check_https( $context ),
			'P41' => self::check_canonical( $context ),
			'P52' => self::check_citations( $context ),
			'L1'  => self::check_open_graph( $context ),
			'L2'  => self::check_twitter_card( $context ),
			'L3'  => self::check_html_lang( $context ),
			'L4'  => self::check_robots_indexable( $context ),
			'L5'  => self::check_hreflang( $context ),
			'L6'  => self::check_article_schema_complete( $context ),
			'L7'  => self::check_author_external_profile( $context ),
			'L8'  => self::check_toc( $context ),
			'L9'  => self::check_first_paragraph( $context ),
			'L10' => self::check_lists( $context ),
			'L11' => self::check_image_dimensions( $context ),
			'L12' => self::check_passive_voice( $context ),
			'L13' => self::check_sentence_length( $context ),
			'L14' => self::check_current_year_mention( $context ),
			'L15' => self::check_paragraph_length( $context ),
		);

		$macro_scores = array();
		foreach ( self::MACROS as $macro => $pids ) {
			$sum = 0;
			$max = 0;
			foreach ( $pids as $pid ) {
				if ( isset( $results[ $pid ] ) && null !== $results[ $pid ]['value'] ) {
					$sum += (int) $results[ $pid ]['value'];
					$max += 2;
				}
			}
			$macro_scores[ $macro ] = $max > 0 ? (int) round( ( $sum / $max ) * 100 ) : 0;
		}

		$total_sum = 0;
		$total_max = 0;
		foreach ( $results as $r ) {
			if ( null !== $r['value'] ) {
				$total_sum += (int) $r['value'];
				$total_max += 2;
			}
		}
		$score = $total_max > 0 ? (int) round( ( $total_sum / $total_max ) * 100 ) : 0;

		return array(
			'score'       => $score,
			'band'        => self::band( $score ),
			'macros'      => $macro_scores,
			'suggestions' => self::build_suggestions( $results ),
			'meta'        => array(
				'post_id'     => (int) $post_id,
				'computed_at' => time(),
				'engine'      => 'lite-v1',
			),
		);
	}

	private static function band( $score ) {
		if ( $score <= 30 ) {
			return 'red';
		}
		if ( $score <= 50 ) {
			return 'yellow';
		}
		if ( $score <= 70 ) {
			return 'blue';
		}
		if ( $score <= 85 ) {
			return 'sage';
		}
		return 'green';
	}

	private static function build_context( $post ) {
		$content_raw  = (string) $post->post_content;
		$content_html = apply_filters( 'the_content', $content_raw );
		$title        = (string) $post->post_title;
		$excerpt      = (string) ( $post->post_excerpt ? $post->post_excerpt : '' );

		// Yoast/RankMath meta description fallbacks.
		$meta_desc = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
		if ( empty( $meta_desc ) ) {
			$meta_desc = get_post_meta( $post->ID, 'rank_math_description', true );
		}
		if ( empty( $meta_desc ) ) {
			$meta_desc = $excerpt;
		}

		$focus_kw = get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true );
		if ( empty( $focus_kw ) ) {
			$focus_kw = get_post_meta( $post->ID, 'rank_math_focus_keyword', true );
		}

		$author    = get_userdata( (int) $post->post_author );
		$author_bio = $author ? get_user_meta( $author->ID, 'description', true ) : '';

		$dom = self::load_dom( $content_html );

		$jsonld_blocks = self::extract_jsonld( $post->ID );

		return array(
			'post'           => $post,
			'title'          => $title,
			'meta_desc'      => (string) $meta_desc,
			'focus_kw'       => (string) $focus_kw,
			'content_raw'    => $content_raw,
			'content_html'   => $content_html,
			'content_text'   => wp_strip_all_tags( $content_html ),
			'word_count'     => str_word_count( wp_strip_all_tags( $content_html ) ),
			'author'         => $author,
			'author_bio'     => (string) $author_bio,
			'dom'            => $dom,
			'jsonld_blocks'  => $jsonld_blocks,
			'home_host'      => wp_parse_url( home_url(), PHP_URL_HOST ),
		);
	}

	private static function load_dom( $html ) {
		if ( empty( $html ) ) {
			return null;
		}
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		return $dom;
	}

	private static function extract_jsonld( $post_id ) {
		$blocks = array();
		$saved  = get_post_meta( $post_id, CITABILITY_SCORE_META_JSONLD, true );
		if ( ! empty( $saved ) ) {
			$decoded = json_decode( $saved, true );
			if ( is_array( $decoded ) ) {
				$blocks[] = $decoded;
			}
		}
		return $blocks;
	}

	// ----- Singoli check (ognuno ritorna value 0|1|2|null + reason). -----

	private static function check_title_length( $ctx ) {
		$len = mb_strlen( $ctx['title'] );
		if ( $len >= 30 && $len <= 65 ) {
			return self::pass( 2, sprintf( __( 'Titolo %d caratteri, nel range ottimale (30-65).', 'citability-score' ), $len ) );
		}
		if ( $len >= 20 && $len <= 80 ) {
			return self::pass( 1, sprintf( __( 'Titolo %d caratteri, accettabile ma fuori dal range ideale (30-65).', 'citability-score' ), $len ) );
		}
		return self::pass( 0, sprintf( __( 'Titolo %d caratteri: troppo %s.', 'citability-score' ), $len, $len < 20 ? __( 'corto', 'citability-score' ) : __( 'lungo', 'citability-score' ) ) );
	}

	private static function check_meta_description( $ctx ) {
		$len = mb_strlen( $ctx['meta_desc'] );
		if ( 0 === $len ) {
			return self::pass( 0, __( 'Meta description assente.', 'citability-score' ) );
		}
		if ( $len >= 120 && $len <= 160 ) {
			return self::pass( 2, sprintf( __( 'Meta description %d caratteri, nel range ottimale.', 'citability-score' ), $len ) );
		}
		return self::pass( 1, sprintf( __( 'Meta description %d caratteri, fuori dal range ideale (120-160).', 'citability-score' ), $len ) );
	}

	private static function check_h1_unique( $ctx ) {
		// In WP il <h1> della singola è quasi sempre il titolo gestito dal tema.
		// Penalizziamo solo se il contenuto contiene più <h1> espliciti.
		if ( ! $ctx['dom'] ) {
			return self::pass( 2, __( 'Nessun H1 duplicato nel corpo.', 'citability-score' ) );
		}
		$h1s = $ctx['dom']->getElementsByTagName( 'h1' );
		if ( $h1s->length === 0 ) {
			return self::pass( 2, __( 'Nessun H1 duplicato nel corpo (il tema fornisce il titolo).', 'citability-score' ) );
		}
		if ( $h1s->length === 1 ) {
			return self::pass( 1, __( 'Un H1 nel corpo: rischio di doppio H1 se il tema lo ripete.', 'citability-score' ) );
		}
		return self::pass( 0, sprintf( __( '%d tag H1 nel corpo: usa H2/H3 per le sezioni.', 'citability-score' ), $h1s->length ) );
	}

	private static function check_heading_hierarchy( $ctx ) {
		if ( ! $ctx['dom'] ) {
			return self::pass( 0, __( 'Nessun heading rilevato nel contenuto.', 'citability-score' ) );
		}
		$h2 = $ctx['dom']->getElementsByTagName( 'h2' )->length;
		$h3 = $ctx['dom']->getElementsByTagName( 'h3' )->length;
		if ( $h2 >= 2 ) {
			return self::pass( 2, sprintf( __( 'Gerarchia heading buona: %d H2, %d H3.', 'citability-score' ), $h2, $h3 ) );
		}
		if ( $h2 === 1 || $h3 > 0 ) {
			return self::pass( 1, __( 'Pochi heading: aggiungi 2-3 H2 per strutturare il contenuto.', 'citability-score' ) );
		}
		return self::pass( 0, __( 'Nessun H2: il contenuto è un blocco unico, difficile da citare per gli LLM.', 'citability-score' ) );
	}

	private static function check_focus_keyword_in_title( $ctx ) {
		if ( empty( $ctx['focus_kw'] ) ) {
			return self::pass( null, __( 'Focus keyword non impostata (richiede Yoast o RankMath).', 'citability-score' ) );
		}
		$found = false !== mb_stripos( $ctx['title'], $ctx['focus_kw'] );
		return $found
			? self::pass( 2, __( 'Focus keyword presente nel titolo.', 'citability-score' ) )
			: self::pass( 0, sprintf( __( 'Focus keyword "%s" non presente nel titolo.', 'citability-score' ), $ctx['focus_kw'] ) );
	}

	private static function check_author_byline( $ctx ) {
		if ( ! $ctx['author'] ) {
			return self::pass( 0, __( 'Autore non rilevato.', 'citability-score' ) );
		}
		// Heuristic: presupponiamo che il tema mostri l'autore; segnaliamo se è "admin".
		if ( strtolower( $ctx['author']->user_login ) === 'admin' ) {
			return self::pass( 1, __( 'Autore "admin": meglio un nome reale per costruire E-E-A-T.', 'citability-score' ) );
		}
		return self::pass( 2, sprintf( __( 'Autore: %s.', 'citability-score' ), $ctx['author']->display_name ) );
	}

	private static function check_dates_visible( $ctx ) {
		$pub = get_post_time( 'U', true, $ctx['post'] );
		$mod = get_post_modified_time( 'U', true, $ctx['post'] );
		if ( ! $pub ) {
			return self::pass( 0, __( 'Data di pubblicazione assente.', 'citability-score' ) );
		}
		if ( $mod && abs( $mod - $pub ) > DAY_IN_SECONDS ) {
			return self::pass( 2, __( 'Pubblicazione e ultimo aggiornamento differenti: ottimo segnale di freschezza.', 'citability-score' ) );
		}
		return self::pass( 1, __( 'Data di pubblicazione presente, manca segnale di aggiornamento.', 'citability-score' ) );
	}

	private static function check_author_bio( $ctx ) {
		$bio_len = mb_strlen( $ctx['author_bio'] );
		if ( $bio_len >= 80 ) {
			return self::pass( 2, sprintf( __( 'Bio autore: %d caratteri, sufficiente per E-E-A-T.', 'citability-score' ), $bio_len ) );
		}
		if ( $bio_len > 0 ) {
			return self::pass( 1, __( 'Bio autore presente ma troppo breve (target ≥80 caratteri).', 'citability-score' ) );
		}
		return self::pass( 0, __( 'Bio autore vuota: aggiorna il profilo utente WordPress.', 'citability-score' ) );
	}

	private static function check_organization_schema( $ctx ) {
		foreach ( $ctx['jsonld_blocks'] as $b ) {
			$types = self::extract_types( $b );
			foreach ( $types as $t ) {
				if ( in_array( $t, array( 'Organization', 'LocalBusiness', 'Restaurant', 'NewsMediaOrganization' ), true ) ) {
					return self::pass( 2, sprintf( __( 'Schema %s presente.', 'citability-score' ), $t ) );
				}
			}
		}
		return self::pass( 0, __( 'Schema Organization/LocalBusiness assente. Usa il wizard JSON-LD.', 'citability-score' ) );
	}

	private static function check_word_count( $ctx ) {
		$wc = $ctx['word_count'];
		if ( $wc >= 1200 ) {
			return self::pass( 2, sprintf( __( 'Lunghezza ottimale: %d parole.', 'citability-score' ), $wc ) );
		}
		if ( $wc >= 600 ) {
			return self::pass( 1, sprintf( __( 'Lunghezza accettabile: %d parole (target ≥1200).', 'citability-score' ), $wc ) );
		}
		return self::pass( 0, sprintf( __( 'Solo %d parole: contenuto troppo corto per essere citato.', 'citability-score' ), $wc ) );
	}

	private static function check_reading_level( $ctx ) {
		$text = $ctx['content_text'];
		if ( strlen( $text ) < 200 ) {
			return self::pass( null, __( 'Testo troppo corto per calcolare leggibilità.', 'citability-score' ) );
		}
		// Flesch-Vacca approssimato per italiano.
		$sentences = max( 1, preg_match_all( '/[.!?]+/u', $text ) );
		$words     = max( 1, str_word_count( $text ) );
		$syllables = self::count_syllables_it( $text );
		$asl       = $words / $sentences;
		$asw       = $syllables / $words;
		$flesch    = 217 - ( 1.3 * $asl ) - ( 60 * $asw );

		if ( $flesch >= 60 ) {
			return self::pass( 2, sprintf( __( 'Leggibilità Flesch-IT %d: facile da parafrasare per un LLM.', 'citability-score' ), round( $flesch ) ) );
		}
		if ( $flesch >= 40 ) {
			return self::pass( 1, sprintf( __( 'Leggibilità Flesch-IT %d: leggermente complesso.', 'citability-score' ), round( $flesch ) ) );
		}
		return self::pass( 0, sprintf( __( 'Leggibilità Flesch-IT %d: troppo complesso.', 'citability-score' ), round( $flesch ) ) );
	}

	private static function count_syllables_it( $text ) {
		// Approssimazione: vocali e dittonghi nei testi italiani.
		$text  = mb_strtolower( $text );
		preg_match_all( '/[aeiouàèéìòù]+/u', $text, $m );
		return count( $m[0] );
	}

	private static function check_internal_links( $ctx ) {
		$count = self::count_links_by_host( $ctx, true );
		if ( $count >= 3 ) {
			return self::pass( 2, sprintf( __( '%d link interni: ottima struttura.', 'citability-score' ), $count ) );
		}
		if ( $count >= 1 ) {
			return self::pass( 1, sprintf( __( '%d link interni (target ≥3).', 'citability-score' ), $count ) );
		}
		return self::pass( 0, __( 'Nessun link interno: i contenuti correlati aumentano la citabilità.', 'citability-score' ) );
	}

	private static function check_outbound_auth_links( $ctx ) {
		$count = self::count_links_by_host( $ctx, false );
		if ( $count >= 2 ) {
			return self::pass( 2, sprintf( __( '%d link in uscita verso fonti esterne: buon segnale di expertise.', 'citability-score' ), $count ) );
		}
		if ( $count === 1 ) {
			return self::pass( 1, __( '1 link in uscita: aggiungi almeno una fonte autorevole in più.', 'citability-score' ) );
		}
		return self::pass( 0, __( 'Nessun link in uscita: gli LLM premiano i contenuti che citano fonti.', 'citability-score' ) );
	}

	private static function count_links_by_host( $ctx, $internal ) {
		if ( ! $ctx['dom'] ) {
			return 0;
		}
		$count = 0;
		$home  = $ctx['home_host'];
		foreach ( $ctx['dom']->getElementsByTagName( 'a' ) as $a ) {
			$href = $a->getAttribute( 'href' );
			if ( empty( $href ) || strpos( $href, '#' ) === 0 ) {
				continue;
			}
			$host = wp_parse_url( $href, PHP_URL_HOST );
			if ( empty( $host ) ) {
				if ( $internal ) {
					++$count;
				}
				continue;
			}
			$is_internal = ( $host === $home );
			if ( $internal === $is_internal ) {
				++$count;
			}
		}
		return $count;
	}

	private static function check_image_alt( $ctx ) {
		if ( ! $ctx['dom'] ) {
			return self::pass( null, __( 'Nessuna immagine nel contenuto.', 'citability-score' ) );
		}
		$imgs = $ctx['dom']->getElementsByTagName( 'img' );
		if ( $imgs->length === 0 ) {
			return self::pass( null, __( 'Nessuna immagine nel contenuto.', 'citability-score' ) );
		}
		$missing = 0;
		foreach ( $imgs as $img ) {
			$alt = trim( $img->getAttribute( 'alt' ) );
			if ( '' === $alt ) {
				++$missing;
			}
		}
		if ( 0 === $missing ) {
			return self::pass( 2, sprintf( __( 'Tutte le %d immagini hanno alt text.', 'citability-score' ), $imgs->length ) );
		}
		$ratio = 1 - ( $missing / $imgs->length );
		if ( $ratio >= 0.6 ) {
			return self::pass( 1, sprintf( __( '%d immagini su %d senza alt: completa il resto.', 'citability-score' ), $missing, $imgs->length ) );
		}
		return self::pass( 0, sprintf( __( '%d immagini su %d senza alt: aggiungi descrizioni.', 'citability-score' ), $missing, $imgs->length ) );
	}

	private static function check_faq_pattern( $ctx ) {
		// 1) JSON-LD FAQPage salvato.
		foreach ( $ctx['jsonld_blocks'] as $b ) {
			if ( in_array( 'FAQPage', self::extract_types( $b ), true ) ) {
				return self::pass( 2, __( 'Schema FAQPage presente.', 'citability-score' ) );
			}
		}
		// 2) Pattern naturale Q&A (almeno 2 domande nel testo).
		$questions = preg_match_all( '/\?\s/u', $ctx['content_text'] );
		if ( $questions >= 3 ) {
			return self::pass( 1, sprintf( __( '%d domande nel testo: aggiungi schema FAQPage tramite wizard.', 'citability-score' ), $questions ) );
		}
		return self::pass( 0, __( 'Nessun pattern FAQ. Le FAQ aumentano molto la citabilità AI.', 'citability-score' ) );
	}

	private static function check_https( $ctx ) {
		return is_ssl()
			? self::pass( 2, __( 'Sito su HTTPS.', 'citability-score' ) )
			: self::pass( 0, __( 'Sito non in HTTPS: requisito minimo per indicizzazione AI.', 'citability-score' ) );
	}

	private static function check_canonical( $ctx ) {
		// In WP il canonical è gestito da rel_canonical(); diamo per buono se non hai disabilitato.
		if ( has_action( 'wp_head', 'rel_canonical' ) ) {
			return self::pass( 2, __( 'Canonical URL gestito da WordPress core.', 'citability-score' ) );
		}
		return self::pass( 1, __( 'Canonical non rilevato in wp_head: verifica il tema o il plugin SEO.', 'citability-score' ) );
	}

	private static function check_citations( $ctx ) {
		// Heuristic: link <a> verso domini esterni + presenza marcatori tipici ("fonte:", "secondo", "(", riferimenti).
		$ext_links = self::count_links_by_host( $ctx, false );
		$markers   = preg_match_all( '/\b(fonte|secondo|stud(?:i|io)|ricerca|report)\b/iu', $ctx['content_text'] );
		if ( $ext_links >= 2 && $markers >= 2 ) {
			return self::pass( 2, __( 'Contenuto cita fonti esterne con frasi di attribuzione.', 'citability-score' ) );
		}
		if ( $ext_links >= 1 || $markers >= 1 ) {
			return self::pass( 1, __( 'Citazioni deboli: aggiungi attribuzioni esplicite a fonti autorevoli.', 'citability-score' ) );
		}
		return self::pass( 0, __( 'Nessuna citazione esplicita: gli LLM cercano contenuti verificabili.', 'citability-score' ) );
	}

	// ===== L1 — Open Graph (proxy: SEO plugin attivo + featured image). =====
	private static function check_open_graph( $ctx ) {
		$has_seo  = self::has_seo_plugin();
		$has_thumb = has_post_thumbnail( $ctx['post']->ID );
		if ( $has_seo && $has_thumb ) {
			return self::pass( 2, __( 'Open Graph generato da un plugin SEO e immagine in evidenza impostata.', 'citability-score' ) );
		}
		if ( $has_seo || $has_thumb ) {
			return self::pass( 1, __( 'Open Graph parziale: serve sia un plugin SEO sia un\'immagine in evidenza.', 'citability-score' ) );
		}
		return self::pass( 0, __( 'Nessun Open Graph: i contenuti condivisi non avranno anteprime ricche.', 'citability-score' ) );
	}

	// ===== L2 — Twitter Card (proxy: plugin SEO attivo). =====
	private static function check_twitter_card( $ctx ) {
		if ( self::has_seo_plugin() ) {
			return has_post_thumbnail( $ctx['post']->ID )
				? self::pass( 2, __( 'Twitter Card generata dal plugin SEO con immagine.', 'citability-score' ) )
				: self::pass( 1, __( 'Twitter Card senza immagine: imposta un\'immagine in evidenza.', 'citability-score' ) );
		}
		return self::pass( 0, __( 'Nessun plugin SEO rilevato: la Twitter Card non viene generata.', 'citability-score' ) );
	}

	// ===== L3 — Html lang attribute. =====
	private static function check_html_lang( $ctx ) {
		$lang = get_bloginfo( 'language' );
		if ( ! empty( $lang ) ) {
			return self::pass( 2, sprintf( __( 'Lingua del sito impostata su %s.', 'citability-score' ), $lang ) );
		}
		return self::pass( 0, __( 'Lingua del sito non impostata: i motori AI non riconoscono il linguaggio del contenuto.', 'citability-score' ) );
	}

	// ===== L4 — Robots indexable. =====
	private static function check_robots_indexable( $ctx ) {
		$post_id = $ctx['post']->ID;
		$yoast_noindex = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
		if ( '1' === (string) $yoast_noindex ) {
			return self::pass( 0, __( 'Post marcato come noindex (Yoast): non sarà indicizzato.', 'citability-score' ) );
		}
		$rm_robots = get_post_meta( $post_id, 'rank_math_robots', true );
		if ( is_array( $rm_robots ) && in_array( 'noindex', $rm_robots, true ) ) {
			return self::pass( 0, __( 'Post marcato come noindex (RankMath): non sarà indicizzato.', 'citability-score' ) );
		}
		if ( 'publish' !== $ctx['post']->post_status ) {
			return self::pass( 1, __( 'Post ancora in bozza: nessun motore lo indicizzerà finché non è pubblicato.', 'citability-score' ) );
		}
		return self::pass( 2, __( 'Post indicizzabile dai motori di ricerca.', 'citability-score' ) );
	}

	// ===== L5 — Hreflang / sito multilingua. =====
	private static function check_hreflang( $ctx ) {
		if ( self::has_multilang_plugin() ) {
			return self::pass( 2, __( 'Plugin multilingua attivo: hreflang vengono generati automaticamente.', 'citability-score' ) );
		}
		// Controllo neutrale: se il sito è monolingua, è OK (non penalizzante).
		return self::pass( null, __( 'Sito monolingua: hreflang non necessari.', 'citability-score' ) );
	}

	// ===== L6 — Article schema completo (author + publisher + image). =====
	private static function check_article_schema_complete( $ctx ) {
		$article_types = array( 'Article', 'BlogPosting', 'NewsArticle' );
		foreach ( $ctx['jsonld_blocks'] as $b ) {
			$types = self::extract_types( $b );
			if ( count( array_intersect( $types, $article_types ) ) === 0 ) {
				continue;
			}
			$has_author    = ! empty( $b['author'] );
			$has_publisher = ! empty( $b['publisher'] );
			$has_image     = ! empty( $b['image'] );
			$filled        = (int) $has_author + (int) $has_publisher + (int) $has_image;
			if ( 3 === $filled ) {
				return self::pass( 2, __( 'Markup Article completo (autore, publisher, immagine).', 'citability-score' ) );
			}
			if ( $filled >= 1 ) {
				return self::pass( 1, __( 'Markup Article parziale: completa autore, publisher e immagine.', 'citability-score' ) );
			}
			return self::pass( 0, __( 'Markup Article presente ma senza autore, publisher o immagine.', 'citability-score' ) );
		}
		return self::pass( 0, __( 'Nessun markup Article salvato: usa il wizard JSON-LD.', 'citability-score' ) );
	}

	// ===== L7 — Author external profile (sito web utente WP). =====
	private static function check_author_external_profile( $ctx ) {
		if ( ! $ctx['author'] ) {
			return self::pass( 0, __( 'Autore non rilevato.', 'citability-score' ) );
		}
		$url = trim( (string) $ctx['author']->user_url );
		if ( '' === $url ) {
			return self::pass( 0, __( 'Profilo autore senza sito web: aggiungi un URL nelle impostazioni utente per rafforzare E-E-A-T.', 'citability-score' ) );
		}
		// Valutiamo se è un dominio diverso da quello del sito (più utile per E-E-A-T).
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( $host && $host !== $ctx['home_host'] ) {
			return self::pass( 2, sprintf( __( 'Profilo autore collegato a %s.', 'citability-score' ), $host ) );
		}
		return self::pass( 1, __( 'Profilo autore collegato allo stesso sito: meglio un profilo esterno (LinkedIn, sito personale).', 'citability-score' ) );
	}

	// ===== L8 — Indice navigabile (heuristic: se molti H2 servirebbe un TOC). =====
	private static function check_toc( $ctx ) {
		if ( ! $ctx['dom'] ) {
			return self::pass( null, __( 'Contenuto vuoto.', 'citability-score' ) );
		}
		$h2 = $ctx['dom']->getElementsByTagName( 'h2' )->length;
		if ( $h2 < 3 ) {
			return self::pass( null, __( 'Articolo breve: l\'indice non è necessario.', 'citability-score' ) );
		}
		$content = $ctx['content_raw'];
		$has_toc = false !== stripos( $content, 'wp-block-table-of-contents' )
			|| false !== stripos( $content, '[toc' )
			|| false !== stripos( $content, 'wp-block-rank-math-toc' )
			|| false !== stripos( $content, 'class="ez-toc' );
		if ( $has_toc ) {
			return self::pass( 2, sprintf( __( 'Indice navigabile presente con %d sezioni.', 'citability-score' ), $h2 ) );
		}
		return self::pass( 0, sprintf( __( '%d sezioni H2 ma nessun indice: aggiungi un Table of Contents.', 'citability-score' ), $h2 ) );
	}

	// ===== L9 — Apertura del contenuto (primo paragrafo informativo). =====
	private static function check_first_paragraph( $ctx ) {
		if ( ! $ctx['dom'] ) {
			return self::pass( 0, __( 'Contenuto vuoto: aggiungi un\'apertura.', 'citability-score' ) );
		}
		$first_p = null;
		foreach ( $ctx['dom']->getElementsByTagName( 'p' ) as $p ) {
			$txt = trim( $p->textContent );
			if ( '' !== $txt ) {
				$first_p = $txt;
				break;
			}
		}
		if ( null === $first_p ) {
			return self::pass( 0, __( 'Nessun paragrafo trovato.', 'citability-score' ) );
		}
		$len = mb_strlen( $first_p );
		if ( $len < 80 ) {
			return self::pass( 0, sprintf( __( 'Apertura troppo corta (%d caratteri): inizia con un riassunto chiaro.', 'citability-score' ), $len ) );
		}
		if ( $len > 400 ) {
			return self::pass( 1, sprintf( __( 'Apertura molto lunga (%d caratteri): riducila a 1-2 frasi dense.', 'citability-score' ), $len ) );
		}
		// Se c'è una focus keyword, controlliamo che sia nel primo paragrafo.
		if ( ! empty( $ctx['focus_kw'] ) && false === mb_stripos( $first_p, $ctx['focus_kw'] ) ) {
			return self::pass( 1, __( 'L\'apertura non contiene la parola chiave principale.', 'citability-score' ) );
		}
		return self::pass( 2, __( 'Apertura informativa e di lunghezza adatta.', 'citability-score' ) );
	}

	// ===== L10 — Elenchi e bullet. =====
	private static function check_lists( $ctx ) {
		if ( ! $ctx['dom'] || $ctx['word_count'] < 300 ) {
			return self::pass( null, __( 'Articolo breve: gli elenchi non sono necessari.', 'citability-score' ) );
		}
		$ul = $ctx['dom']->getElementsByTagName( 'ul' )->length;
		$ol = $ctx['dom']->getElementsByTagName( 'ol' )->length;
		$lists = $ul + $ol;
		if ( $lists >= 2 ) {
			return self::pass( 2, sprintf( __( 'Buon uso di elenchi: %d liste presenti.', 'citability-score' ), $lists ) );
		}
		if ( 1 === $lists ) {
			return self::pass( 1, __( 'Una sola lista: aggiungine un\'altra per rendere il contenuto più scansionabile.', 'citability-score' ) );
		}
		return self::pass( 0, __( 'Nessun elenco puntato/numerato: gli LLM preferiscono contenuti strutturati.', 'citability-score' ) );
	}

	// ===== L11 — Immagini con dimensioni esplicite (CLS-friendly). =====
	private static function check_image_dimensions( $ctx ) {
		if ( ! $ctx['dom'] ) {
			return self::pass( null, __( 'Nessuna immagine nel contenuto.', 'citability-score' ) );
		}
		$imgs = $ctx['dom']->getElementsByTagName( 'img' );
		if ( $imgs->length === 0 ) {
			return self::pass( null, __( 'Nessuna immagine nel contenuto.', 'citability-score' ) );
		}
		$missing = 0;
		foreach ( $imgs as $img ) {
			$w = $img->getAttribute( 'width' );
			$h = $img->getAttribute( 'height' );
			if ( '' === $w || '' === $h ) {
				++$missing;
			}
		}
		if ( 0 === $missing ) {
			return self::pass( 2, sprintf( __( 'Tutte le %d immagini hanno dimensioni esplicite.', 'citability-score' ), $imgs->length ) );
		}
		$ratio = 1 - ( $missing / $imgs->length );
		if ( $ratio >= 0.6 ) {
			return self::pass( 1, sprintf( __( '%d immagini su %d senza dimensioni: completa il resto.', 'citability-score' ), $missing, $imgs->length ) );
		}
		return self::pass( 0, sprintf( __( '%d immagini su %d senza dimensioni: peggiorano l\'esperienza visiva.', 'citability-score' ), $missing, $imgs->length ) );
	}

	// ===== L12 — Forma passiva (italiano, euristico). =====
	private static function check_passive_voice( $ctx ) {
		$text = $ctx['content_text'];
		if ( strlen( $text ) < 200 ) {
			return self::pass( null, __( 'Testo troppo corto per analizzare la forma passiva.', 'citability-score' ) );
		}
		$sentences = max( 1, preg_match_all( '/[.!?]+/u', $text ) );
		// Pattern italiano: ausiliare + participio passato (es. "è stato pubblicato", "viene utilizzato").
		$passive = preg_match_all( '/\b(è|sono|era|erano|fu|furono|sarà|saranno|viene|vengono|veniva|venivano|venne|vennero|venuto|venuti|stato|stati|state|state)\s+[a-zàèéìòù]+(at[oiea]|ut[oiea]|it[oiea])\b/iu', $text );
		$ratio = $passive / $sentences;
		if ( $ratio <= 0.10 ) {
			return self::pass( 2, sprintf( __( 'Stile diretto: solo %d%% delle frasi in forma passiva.', 'citability-score' ), round( $ratio * 100 ) ) );
		}
		if ( $ratio <= 0.20 ) {
			return self::pass( 1, sprintf( __( '%d%% delle frasi in forma passiva: prova a riscriverne qualcuna in attiva.', 'citability-score' ), round( $ratio * 100 ) ) );
		}
		return self::pass( 0, sprintf( __( '%d%% delle frasi in forma passiva: troppe per essere citate dagli LLM.', 'citability-score' ), round( $ratio * 100 ) ) );
	}

	// ===== L13 — Lunghezza media delle frasi. =====
	private static function check_sentence_length( $ctx ) {
		$text = $ctx['content_text'];
		if ( strlen( $text ) < 200 ) {
			return self::pass( null, __( 'Testo troppo corto per misurare la lunghezza delle frasi.', 'citability-score' ) );
		}
		$sentences = max( 1, preg_match_all( '/[.!?]+/u', $text ) );
		$words     = max( 1, str_word_count( $text ) );
		$asl       = $words / $sentences;
		if ( $asl <= 20 ) {
			return self::pass( 2, sprintf( __( 'Frasi brevi (media %d parole): facili da citare in modo testuale.', 'citability-score' ), round( $asl ) ) );
		}
		if ( $asl <= 28 ) {
			return self::pass( 1, sprintf( __( 'Frasi medie (%d parole): spezza quelle più lunghe.', 'citability-score' ), round( $asl ) ) );
		}
		return self::pass( 0, sprintf( __( 'Frasi troppo lunghe (%d parole): un LLM faticherà a estrarre risposte brevi.', 'citability-score' ), round( $asl ) ) );
	}

	// ===== L14 — Anno corrente nel testo (segnale di attualità). =====
	private static function check_current_year_mention( $ctx ) {
		$current = (int) current_time( 'Y' );
		$previous = $current - 1;
		$text = $ctx['content_text'];
		if ( preg_match( '/\b' . $current . '\b/', $text ) ) {
			return self::pass( 2, sprintf( __( 'L\'anno %d è menzionato nel testo.', 'citability-score' ), $current ) );
		}
		if ( preg_match( '/\b' . $previous . '\b/', $text ) ) {
			return self::pass( 1, sprintf( __( 'Citato l\'anno %d ma non %d: aggiorna i riferimenti temporali.', 'citability-score' ), $previous, $current ) );
		}
		return self::pass( 0, __( 'Nessun riferimento all\'anno corrente: gli LLM penalizzano contenuti percepiti come datati.', 'citability-score' ) );
	}

	// ===== L15 — Lunghezza dei paragrafi (no muri di testo). =====
	private static function check_paragraph_length( $ctx ) {
		if ( ! $ctx['dom'] ) {
			return self::pass( null, __( 'Contenuto vuoto.', 'citability-score' ) );
		}
		$total = 0;
		$too_long = 0;
		foreach ( $ctx['dom']->getElementsByTagName( 'p' ) as $p ) {
			$len = mb_strlen( trim( $p->textContent ) );
			if ( 0 === $len ) {
				continue;
			}
			++$total;
			if ( $len > 500 ) {
				++$too_long;
			}
		}
		if ( 0 === $total ) {
			return self::pass( null, __( 'Nessun paragrafo nel contenuto.', 'citability-score' ) );
		}
		if ( 0 === $too_long ) {
			return self::pass( 2, sprintf( __( 'Paragrafi ben dimensionati (%d totali).', 'citability-score' ), $total ) );
		}
		if ( $too_long <= 2 ) {
			return self::pass( 1, sprintf( __( '%d paragrafi troppo lunghi: spezza i muri di testo.', 'citability-score' ), $too_long ) );
		}
		return self::pass( 0, sprintf( __( '%d paragrafi troppo lunghi: il contenuto è difficile da scansionare.', 'citability-score' ), $too_long ) );
	}

	// ===== Helper plugin detection. =====
	private static function has_seo_plugin() {
		return defined( 'WPSEO_VERSION' )
			|| defined( 'RANK_MATH_VERSION' )
			|| defined( 'AIOSEO_VERSION' )
			|| defined( 'SEOPRESS_VERSION' );
	}

	private static function has_multilang_plugin() {
		return defined( 'POLYLANG_VERSION' )
			|| defined( 'ICL_SITEPRESS_VERSION' )
			|| defined( 'TRP_PLUGIN_VERSION' )
			|| defined( 'WEGLOT_VERSION' );
	}

	private static function extract_types( $jsonld ) {
		$types = array();
		if ( isset( $jsonld['@type'] ) ) {
			$types = is_array( $jsonld['@type'] ) ? $jsonld['@type'] : array( $jsonld['@type'] );
		}
		if ( isset( $jsonld['@graph'] ) && is_array( $jsonld['@graph'] ) ) {
			foreach ( $jsonld['@graph'] as $node ) {
				if ( isset( $node['@type'] ) ) {
					$types = array_merge( $types, is_array( $node['@type'] ) ? $node['@type'] : array( $node['@type'] ) );
				}
			}
		}
		return array_unique( $types );
	}

	private static function pass( $value, $reason ) {
		return array(
			'value'  => $value,
			'reason' => $reason,
		);
	}

	private static function build_suggestions( $results ) {
		$labels = self::labels();
		$out    = array();
		foreach ( $results as $pid => $r ) {
			if ( null === $r['value'] || $r['value'] >= 2 ) {
				continue;
			}
			$out[] = array(
				'label'    => isset( $labels[ $pid ] ) ? $labels[ $pid ] : __( 'Controllo', 'citability-score' ),
				'severity' => 0 === $r['value'] ? 'high' : 'medium',
				'message'  => $r['reason'],
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				$rank = array( 'high' => 0, 'medium' => 1 );
				return $rank[ $a['severity'] ] - $rank[ $b['severity'] ];
			}
		);
		return $out;
	}
}
