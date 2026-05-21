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
		'coherence'   => array( 'P1', 'P2', 'P3', 'P4', 'P5' ),
		'identity'    => array( 'P12', 'P13', 'P14', 'P15' ),
		'content'     => array( 'P30', 'P31', 'P32', 'P33', 'P34', 'P35' ),
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
