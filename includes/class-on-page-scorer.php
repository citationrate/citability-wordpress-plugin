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
			'P1'  => __( 'Title length', 'citationrate-ai-visibility' ),
			'P2'  => __( 'Short page description', 'citationrate-ai-visibility' ),
			'P3'  => __( 'A single main heading', 'citationrate-ai-visibility' ),
			'P4'  => __( 'Subheadings that split the text', 'citationrate-ai-visibility' ),
			'P5'  => __( 'Keyword in the title', 'citationrate-ai-visibility' ),
			'P12' => __( 'Visible author', 'citationrate-ai-visibility' ),
			'P13' => __( 'Publication date', 'citationrate-ai-visibility' ),
			'P14' => __( 'Short author bio', 'citationrate-ai-visibility' ),
			'P15' => __( 'Business details for AI', 'citationrate-ai-visibility' ),
			'P30' => __( 'Content length', 'citationrate-ai-visibility' ),
			'P31' => __( 'Readability', 'citationrate-ai-visibility' ),
			'P32' => __( 'Links to your other pages', 'citationrate-ai-visibility' ),
			'P33' => __( 'Links to external sources', 'citationrate-ai-visibility' ),
			'P34' => __( 'Image descriptions', 'citationrate-ai-visibility' ),
			'P35' => __( 'Questions and answers section', 'citationrate-ai-visibility' ),
			'P40' => __( 'Secure connection', 'citationrate-ai-visibility' ),
			'P41' => __( 'Official page address', 'citationrate-ai-visibility' ),
			'P52' => __( 'Sources cited in the text', 'citationrate-ai-visibility' ),
			'L1'  => __( 'Preview when shared on social', 'citationrate-ai-visibility' ),
			'L2'  => __( 'Preview on X (Twitter)', 'citationrate-ai-visibility' ),
			'L3'  => __( 'Site language', 'citationrate-ai-visibility' ),
			'L4'  => __( 'Page visible to search engines', 'citationrate-ai-visibility' ),
			'L5'  => __( 'Versions in other languages', 'citationrate-ai-visibility' ),
			'L6'  => __( 'Complete article card for AI', 'citationrate-ai-visibility' ),
			'L7'  => __( 'Public author profile', 'citationrate-ai-visibility' ),
			'L8'  => __( 'Table of contents', 'citationrate-ai-visibility' ),
			'L9'  => __( 'Opening that answers right away', 'citationrate-ai-visibility' ),
			'L10' => __( 'Bulleted lists', 'citationrate-ai-visibility' ),
			'L11' => __( 'Image dimensions specified', 'citationrate-ai-visibility' ),
			'L12' => __( 'Sentences in active voice', 'citationrate-ai-visibility' ),
			'L13' => __( 'Short sentences', 'citationrate-ai-visibility' ),
			'L14' => __( 'Reference to the current year', 'citationrate-ai-visibility' ),
			'L15' => __( 'Short paragraphs', 'citationrate-ai-visibility' ),
		);
	}

	public static function score_post( $post_id, $overrides = array() ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'not_found', __( 'Post not found.', 'citationrate-ai-visibility' ) );
		}

		// Live scoring: override the saved fields with the current editor
		// content. Clone first so we never mutate the cached WP_Post instance.
		if ( ! empty( $overrides ) ) {
			$post = clone $post;
			foreach ( array( 'post_content', 'post_title', 'post_excerpt' ) as $field ) {
				if ( isset( $overrides[ $field ] ) ) {
					$post->$field = $overrides[ $field ];
				}
			}
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
				'post_id'        => (int) $post_id,
				'computed_at'    => time(),
				'engine'         => 'lite-v1',
				'params_checked' => count( $results ),
				'params_total'   => 56,
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
		// Reusing the WordPress core hook to render shortcodes/blocks before scoring.
		$content_html = apply_filters( 'the_content', $content_raw ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
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
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( 'Title %d characters, within the optimal range (30-65).', 'citationrate-ai-visibility' ), $len ) );
		}
		if ( $len >= 20 && $len <= 80 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 1, sprintf( __( 'Title %d characters, acceptable but outside the ideal range (30-65).', 'citationrate-ai-visibility' ), $len ) );
		}
		// translators: %1$d, %2$s replaced at runtime with dynamic values.
		return self::pass( 0, sprintf( __( 'Title %1$d characters: too %2$s.', 'citationrate-ai-visibility' ), $len, $len < 20 ? __( 'short', 'citationrate-ai-visibility' ) : __( 'long', 'citationrate-ai-visibility' ) ) );
	}

	private static function check_meta_description( $ctx ) {
		$len = mb_strlen( $ctx['meta_desc'] );
		if ( 0 === $len ) {
			return self::pass( 0, __( 'Page description missing.', 'citationrate-ai-visibility' ) );
		}
		if ( $len >= 120 && $len <= 160 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( 'Description %d characters, optimal length.', 'citationrate-ai-visibility' ), $len ) );
		}
		// translators: %d replaced at runtime with dynamic values.
		return self::pass( 1, sprintf( __( 'Description %d characters, outside the ideal length (120-160).', 'citationrate-ai-visibility' ), $len ) );
	}

	private static function check_h1_unique( $ctx ) {
		// In WP il <h1> della singola è quasi sempre il titolo gestito dal tema.
		// Penalizziamo solo se il contenuto contiene più <h1> espliciti.
		if ( ! $ctx['dom'] ) {
			return self::pass( 2, __( 'No duplicate main heading in the body.', 'citationrate-ai-visibility' ) );
		}
		$h1s = $ctx['dom']->getElementsByTagName( 'h1' );
		if ( $h1s->length === 0 ) {
			return self::pass( 2, __( 'No duplicate main heading in the body (the theme provides the title).', 'citationrate-ai-visibility' ) );
		}
		if ( $h1s->length === 1 ) {
			return self::pass( 1, __( 'One main heading in the body: risk of a duplicate if the theme repeats it.', 'citationrate-ai-visibility' ) );
		}
		// translators: %d replaced at runtime with dynamic values.
		return self::pass( 0, sprintf( __( '%d main headings in the text: use subheadings for sections.', 'citationrate-ai-visibility' ), $h1s->length ) );
	}

	private static function check_heading_hierarchy( $ctx ) {
		if ( ! $ctx['dom'] ) {
			return self::pass( 0, __( 'No headings found in the content.', 'citationrate-ai-visibility' ) );
		}
		$h2 = $ctx['dom']->getElementsByTagName( 'h2' )->length;
		$h3 = $ctx['dom']->getElementsByTagName( 'h3' )->length;
		if ( $h2 >= 2 ) {
			// translators: %1$d, %2$d replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( 'Good subheading structure (%1$d main, %2$d secondary).', 'citationrate-ai-visibility' ), $h2, $h3 ) );
		}
		if ( $h2 === 1 || $h3 > 0 ) {
			return self::pass( 1, __( 'Few subheadings: add 2-3 to structure the content.', 'citationrate-ai-visibility' ) );
		}
		return self::pass( 0, __( 'No subheadings: the text is one block, hard for AI to cite.', 'citationrate-ai-visibility' ) );
	}

	private static function check_focus_keyword_in_title( $ctx ) {
		if ( empty( $ctx['focus_kw'] ) ) {
			return self::pass( null, __( 'Main keyword not set (requires Yoast or RankMath).', 'citationrate-ai-visibility' ) );
		}
		$found = false !== mb_stripos( $ctx['title'], $ctx['focus_kw'] );
		return $found
			? self::pass( 2, __( 'Main keyword present in the title.', 'citationrate-ai-visibility' ) )
			// translators: %s replaced at runtime with dynamic values.
			: self::pass( 0, sprintf( __( 'Main keyword "%s" not present in the title.', 'citationrate-ai-visibility' ), $ctx['focus_kw'] ) );
	}

	private static function check_author_byline( $ctx ) {
		if ( ! $ctx['author'] ) {
			return self::pass( 0, __( 'Author not detected.', 'citationrate-ai-visibility' ) );
		}
		// Heuristic: presupponiamo che il tema mostri l'autore; segnaliamo se è "admin".
		if ( strtolower( $ctx['author']->user_login ) === 'admin' ) {
			return self::pass( 1, __( 'Author "admin": a real name builds more authority.', 'citationrate-ai-visibility' ) );
		}
		// translators: %s replaced at runtime with dynamic values.
		return self::pass( 2, sprintf( __( 'Author: %s.', 'citationrate-ai-visibility' ), $ctx['author']->display_name ) );
	}

	private static function check_dates_visible( $ctx ) {
		$pub = get_post_time( 'U', true, $ctx['post'] );
		$mod = get_post_modified_time( 'U', true, $ctx['post'] );
		if ( ! $pub ) {
			return self::pass( 0, __( 'Publication date missing.', 'citationrate-ai-visibility' ) );
		}
		if ( $mod && abs( $mod - $pub ) > DAY_IN_SECONDS ) {
			return self::pass( 2, __( 'Different publish and update dates: a great freshness signal.', 'citationrate-ai-visibility' ) );
		}
		return self::pass( 1, __( 'Publication date present, no update signal.', 'citationrate-ai-visibility' ) );
	}

	private static function check_author_bio( $ctx ) {
		$bio_len = mb_strlen( $ctx['author_bio'] );
		if ( $bio_len >= 80 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( 'Author bio: %d characters, enough for authority.', 'citationrate-ai-visibility' ), $bio_len ) );
		}
		if ( $bio_len > 0 ) {
			return self::pass( 1, __( 'Author bio present but too short (target ≥80 characters).', 'citationrate-ai-visibility' ) );
		}
		return self::pass( 0, __( 'Author bio empty: update the WordPress user profile.', 'citationrate-ai-visibility' ) );
	}

	private static function check_organization_schema( $ctx ) {
		foreach ( $ctx['jsonld_blocks'] as $b ) {
			$types = self::extract_types( $b );
			foreach ( $types as $t ) {
				if ( in_array( $t, array( 'Organization', 'LocalBusiness', 'Restaurant', 'NewsMediaOrganization' ), true ) ) {
					// translators: %s replaced at runtime with dynamic values.
					return self::pass( 2, sprintf( __( 'Business details present (%s).', 'citationrate-ai-visibility' ), $t ) );
				}
			}
		}
		return self::pass( 0, __( 'Business details missing. Add them from the "Help AI understand this page" box.', 'citationrate-ai-visibility' ) );
	}

	private static function check_word_count( $ctx ) {
		$wc = $ctx['word_count'];
		if ( $wc >= 1200 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( 'Optimal length: %d words.', 'citationrate-ai-visibility' ), $wc ) );
		}
		if ( $wc >= 600 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 1, sprintf( __( 'Acceptable length: %d words (target ≥1200).', 'citationrate-ai-visibility' ), $wc ) );
		}
		// translators: %d replaced at runtime with dynamic values.
		return self::pass( 0, sprintf( __( 'Only %d words: content too short to be cited.', 'citationrate-ai-visibility' ), $wc ) );
	}

	private static function check_reading_level( $ctx ) {
		$text = $ctx['content_text'];
		if ( strlen( $text ) < 200 ) {
			return self::pass( null, __( 'Text too short to measure readability.', 'citationrate-ai-visibility' ) );
		}
		// Flesch-Vacca approssimato per italiano.
		$sentences = max( 1, preg_match_all( '/[.!?]+/u', $text ) );
		$words     = max( 1, str_word_count( $text ) );
		$syllables = self::count_syllables_it( $text );
		$asl       = $words / $sentences;
		$asw       = $syllables / $words;
		$flesch    = 217 - ( 1.3 * $asl ) - ( 60 * $asw );

		if ( $flesch >= 60 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( 'Readability %d: easy for an AI to paraphrase.', 'citationrate-ai-visibility' ), round( $flesch ) ) );
		}
		if ( $flesch >= 40 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 1, sprintf( __( 'Readability %d: slightly complex.', 'citationrate-ai-visibility' ), round( $flesch ) ) );
		}
		// translators: %d replaced at runtime with dynamic values.
		return self::pass( 0, sprintf( __( 'Readability %d: too complex.', 'citationrate-ai-visibility' ), round( $flesch ) ) );
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
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( '%d internal links: great structure.', 'citationrate-ai-visibility' ), $count ) );
		}
		if ( $count >= 1 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 1, sprintf( __( '%d internal links (target ≥3).', 'citationrate-ai-visibility' ), $count ) );
		}
		return self::pass( 0, __( 'No internal links: related content increases citability.', 'citationrate-ai-visibility' ) );
	}

	private static function check_outbound_auth_links( $ctx ) {
		$count = self::count_links_by_host( $ctx, false );
		if ( $count >= 2 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( '%d outbound links to external sources: a good expertise signal.', 'citationrate-ai-visibility' ), $count ) );
		}
		if ( $count === 1 ) {
			return self::pass( 1, __( '1 outbound link: add at least one more authoritative source.', 'citationrate-ai-visibility' ) );
		}
		return self::pass( 0, __( 'No outbound links: AI rewards content that cites sources.', 'citationrate-ai-visibility' ) );
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
			return self::pass( null, __( 'No images in the content.', 'citationrate-ai-visibility' ) );
		}
		$imgs = $ctx['dom']->getElementsByTagName( 'img' );
		if ( $imgs->length === 0 ) {
			return self::pass( null, __( 'No images in the content.', 'citationrate-ai-visibility' ) );
		}
		$missing = 0;
		foreach ( $imgs as $img ) {
			$alt = trim( $img->getAttribute( 'alt' ) );
			if ( '' === $alt ) {
				++$missing;
			}
		}
		if ( 0 === $missing ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( 'All %d images have alt text.', 'citationrate-ai-visibility' ), $imgs->length ) );
		}
		$ratio = 1 - ( $missing / $imgs->length );
		if ( $ratio >= 0.6 ) {
			// translators: %1$d, %2$d replaced at runtime with dynamic values.
			return self::pass( 1, sprintf( __( '%1$d of %2$d images without alt text: complete the rest.', 'citationrate-ai-visibility' ), $missing, $imgs->length ) );
		}
		// translators: %1$d, %2$d replaced at runtime with dynamic values.
		return self::pass( 0, sprintf( __( '%1$d of %2$d images without alt text: add descriptions.', 'citationrate-ai-visibility' ), $missing, $imgs->length ) );
	}

	private static function check_faq_pattern( $ctx ) {
		// 1) JSON-LD FAQPage salvato.
		foreach ( $ctx['jsonld_blocks'] as $b ) {
			if ( in_array( 'FAQPage', self::extract_types( $b ), true ) ) {
				return self::pass( 2, __( 'Structured questions and answers present.', 'citationrate-ai-visibility' ) );
			}
		}
		// 2) Pattern naturale Q&A (almeno 2 domande nel testo).
		$questions = preg_match_all( '/\?\s/u', $ctx['content_text'] );
		if ( $questions >= 3 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 1, sprintf( __( '%d questions in the text: turn them into a Q&A section from the "Help AI understand this page" box.', 'citationrate-ai-visibility' ), $questions ) );
		}
		return self::pass( 0, __( 'No Q&A pattern. Q&A greatly increases AI citability.', 'citationrate-ai-visibility' ) );
	}

	private static function check_https( $ctx ) {
		return is_ssl()
			? self::pass( 2, __( 'Secure connection active.', 'citationrate-ai-visibility' ) )
			: self::pass( 0, __( 'Connection not secure: a minimum requirement for AI.', 'citationrate-ai-visibility' ) );
	}

	private static function check_canonical( $ctx ) {
		// In WP il canonical è gestito da rel_canonical(); diamo per buono se non hai disabilitato.
		if ( has_action( 'wp_head', 'rel_canonical' ) ) {
			return self::pass( 2, __( 'Official page address handled by WordPress.', 'citationrate-ai-visibility' ) );
		}
		return self::pass( 1, __( 'Official page address not detected: check your theme or SEO plugin.', 'citationrate-ai-visibility' ) );
	}

	private static function check_citations( $ctx ) {
		// Heuristic: link <a> verso domini esterni + presenza marcatori tipici ("fonte:", "secondo", "(", riferimenti).
		$ext_links = self::count_links_by_host( $ctx, false );
		$markers   = preg_match_all( '/\b(fonte|secondo|stud(?:i|io)|ricerca|report)\b/iu', $ctx['content_text'] );
		if ( $ext_links >= 2 && $markers >= 2 ) {
			return self::pass( 2, __( 'Content cites external sources with attribution phrases.', 'citationrate-ai-visibility' ) );
		}
		if ( $ext_links >= 1 || $markers >= 1 ) {
			return self::pass( 1, __( 'Weak citations: add explicit attributions to authoritative sources.', 'citationrate-ai-visibility' ) );
		}
		return self::pass( 0, __( 'No explicit citations: AI looks for verifiable content.', 'citationrate-ai-visibility' ) );
	}

	// ===== L1 — Open Graph (proxy: SEO plugin attivo + featured image). =====
	private static function check_open_graph( $ctx ) {
		$has_seo  = self::has_seo_plugin();
		$has_thumb = has_post_thumbnail( $ctx['post']->ID );
		if ( $has_seo && $has_thumb ) {
			return self::pass( 2, __( 'Social preview generated by your SEO plugin with a featured image.', 'citationrate-ai-visibility' ) );
		}
		if ( $has_seo || $has_thumb ) {
			return self::pass( 1, __( 'Social preview incomplete: you need both an SEO plugin and a featured image.', 'citationrate-ai-visibility' ) );
		}
		return self::pass( 0, __( 'No social preview: shared content won\'t show a rich preview.', 'citationrate-ai-visibility' ) );
	}

	// ===== L2 — Twitter Card (proxy: plugin SEO attivo). =====
	private static function check_twitter_card( $ctx ) {
		if ( self::has_seo_plugin() ) {
			return has_post_thumbnail( $ctx['post']->ID )
				? self::pass( 2, __( 'X preview generated by the SEO plugin with an image.', 'citationrate-ai-visibility' ) )
				: self::pass( 1, __( 'X preview without an image: set a featured image.', 'citationrate-ai-visibility' ) );
		}
		return self::pass( 0, __( 'No SEO plugin detected: the X preview is not generated.', 'citationrate-ai-visibility' ) );
	}

	// ===== L3 — Html lang attribute. =====
	private static function check_html_lang( $ctx ) {
		$lang = get_bloginfo( 'language' );
		if ( ! empty( $lang ) ) {
			// translators: %s replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( 'Site language set to %s.', 'citationrate-ai-visibility' ), $lang ) );
		}
		return self::pass( 0, __( 'Site language not set: AI engines can\'t tell the content language.', 'citationrate-ai-visibility' ) );
	}

	// ===== L4 — Robots indexable. =====
	private static function check_robots_indexable( $ctx ) {
		$post_id = $ctx['post']->ID;
		$yoast_noindex = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
		if ( '1' === (string) $yoast_noindex ) {
			return self::pass( 0, __( 'Page hidden from search engines (Yoast setting): it won\'t appear in results.', 'citationrate-ai-visibility' ) );
		}
		$rm_robots = get_post_meta( $post_id, 'rank_math_robots', true );
		if ( is_array( $rm_robots ) && in_array( 'noindex', $rm_robots, true ) ) {
			return self::pass( 0, __( 'Page hidden from search engines (RankMath setting): it won\'t appear in results.', 'citationrate-ai-visibility' ) );
		}
		if ( 'publish' !== $ctx['post']->post_status ) {
			return self::pass( 1, __( 'Page still a draft: no engine will show it until it is published.', 'citationrate-ai-visibility' ) );
		}
		return self::pass( 2, __( 'Page visible to search engines.', 'citationrate-ai-visibility' ) );
	}

	// ===== L5 — Hreflang / sito multilingua. =====
	private static function check_hreflang( $ctx ) {
		if ( self::has_multilang_plugin() ) {
			return self::pass( 2, __( 'Multilingual plugin active: other-language versions are linked automatically.', 'citationrate-ai-visibility' ) );
		}
		// Controllo neutrale: se il sito è monolingua, è OK (non penalizzante).
		return self::pass( null, __( 'Single-language site: no other-language links needed.', 'citationrate-ai-visibility' ) );
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
				return self::pass( 2, __( 'Complete article card (author, publisher, image).', 'citationrate-ai-visibility' ) );
			}
			if ( $filled >= 1 ) {
				return self::pass( 1, __( 'Article card incomplete: add author, publisher and image.', 'citationrate-ai-visibility' ) );
			}
			return self::pass( 0, __( 'Article card present but without author, publisher or image.', 'citationrate-ai-visibility' ) );
		}
		return self::pass( 0, __( 'No article card saved: add one from the "Help AI understand this page" box.', 'citationrate-ai-visibility' ) );
	}

	// ===== L7 — Author external profile (sito web utente WP). =====
	private static function check_author_external_profile( $ctx ) {
		if ( ! $ctx['author'] ) {
			return self::pass( 0, __( 'Author not detected.', 'citationrate-ai-visibility' ) );
		}
		$url = trim( (string) $ctx['author']->user_url );
		if ( '' === $url ) {
			return self::pass( 0, __( 'Author profile without a website: add a URL in the user settings to strengthen authority.', 'citationrate-ai-visibility' ) );
		}
		// Valutiamo se è un dominio diverso da quello del sito (più utile per autorevolezza).
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( $host && $host !== $ctx['home_host'] ) {
			// translators: %s replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( 'Author profile linked to %s.', 'citationrate-ai-visibility' ), $host ) );
		}
		return self::pass( 1, __( 'Author profile linked to the same site: an external profile (LinkedIn, personal site) is better.', 'citationrate-ai-visibility' ) );
	}

	// ===== L8 — Indice navigabile (heuristic: se molti H2 servirebbe un TOC). =====
	private static function check_toc( $ctx ) {
		if ( ! $ctx['dom'] ) {
			return self::pass( null, __( 'Empty content.', 'citationrate-ai-visibility' ) );
		}
		$h2 = $ctx['dom']->getElementsByTagName( 'h2' )->length;
		if ( $h2 < 3 ) {
			return self::pass( null, __( 'Short article: a table of contents isn\'t needed.', 'citationrate-ai-visibility' ) );
		}
		$content = $ctx['content_raw'];
		$has_toc = false !== stripos( $content, 'wp-block-table-of-contents' )
			|| false !== stripos( $content, '[toc' )
			|| false !== stripos( $content, 'wp-block-rank-math-toc' )
			|| false !== stripos( $content, 'class="ez-toc' );
		if ( $has_toc ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( 'Table of contents present with %d sections.', 'citationrate-ai-visibility' ), $h2 ) );
		}
		// translators: %d replaced at runtime with dynamic values.
		return self::pass( 0, sprintf( __( '%d sections but no table of contents: add one.', 'citationrate-ai-visibility' ), $h2 ) );
	}

	// ===== L9 — Apertura del contenuto (primo paragrafo informativo). =====
	private static function check_first_paragraph( $ctx ) {
		if ( ! $ctx['dom'] ) {
			return self::pass( 0, __( 'Empty content: add an opening.', 'citationrate-ai-visibility' ) );
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
			return self::pass( 0, __( 'No paragraph found.', 'citationrate-ai-visibility' ) );
		}
		$len = mb_strlen( $first_p );
		if ( $len < 80 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 0, sprintf( __( 'Opening too short (%d characters): start with a clear summary.', 'citationrate-ai-visibility' ), $len ) );
		}
		if ( $len > 400 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 1, sprintf( __( 'Opening very long (%d characters): trim it to 1-2 dense sentences.', 'citationrate-ai-visibility' ), $len ) );
		}
		// Se c'è una focus keyword, controlliamo che sia nel primo paragrafo.
		if ( ! empty( $ctx['focus_kw'] ) && false === mb_stripos( $first_p, $ctx['focus_kw'] ) ) {
			return self::pass( 1, __( 'The opening doesn\'t contain the main keyword.', 'citationrate-ai-visibility' ) );
		}
		return self::pass( 2, __( 'Informative opening of suitable length.', 'citationrate-ai-visibility' ) );
	}

	// ===== L10 — Elenchi e bullet. =====
	private static function check_lists( $ctx ) {
		if ( ! $ctx['dom'] || $ctx['word_count'] < 300 ) {
			return self::pass( null, __( 'Short article: lists aren\'t needed.', 'citationrate-ai-visibility' ) );
		}
		$ul = $ctx['dom']->getElementsByTagName( 'ul' )->length;
		$ol = $ctx['dom']->getElementsByTagName( 'ol' )->length;
		$lists = $ul + $ol;
		if ( $lists >= 2 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( 'Good use of lists: %d lists present.', 'citationrate-ai-visibility' ), $lists ) );
		}
		if ( 1 === $lists ) {
			return self::pass( 1, __( 'Only one list: add another to make the content easier to scan.', 'citationrate-ai-visibility' ) );
		}
		return self::pass( 0, __( 'No bulleted/numbered lists: AI prefers structured content.', 'citationrate-ai-visibility' ) );
	}

	// ===== L11 — Immagini con dimensioni esplicite (CLS-friendly). =====
	private static function check_image_dimensions( $ctx ) {
		if ( ! $ctx['dom'] ) {
			return self::pass( null, __( 'No images in the content.', 'citationrate-ai-visibility' ) );
		}
		$imgs = $ctx['dom']->getElementsByTagName( 'img' );
		if ( $imgs->length === 0 ) {
			return self::pass( null, __( 'No images in the content.', 'citationrate-ai-visibility' ) );
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
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( 'All %d images have explicit dimensions.', 'citationrate-ai-visibility' ), $imgs->length ) );
		}
		$ratio = 1 - ( $missing / $imgs->length );
		if ( $ratio >= 0.6 ) {
			// translators: %1$d, %2$d replaced at runtime with dynamic values.
			return self::pass( 1, sprintf( __( '%1$d of %2$d images without dimensions: complete the rest.', 'citationrate-ai-visibility' ), $missing, $imgs->length ) );
		}
		// translators: %1$d, %2$d replaced at runtime with dynamic values.
		return self::pass( 0, sprintf( __( '%1$d of %2$d images without dimensions: they hurt the visual experience.', 'citationrate-ai-visibility' ), $missing, $imgs->length ) );
	}

	// ===== L12 — Forma passiva (italiano, euristico). =====
	private static function check_passive_voice( $ctx ) {
		$text = $ctx['content_text'];
		if ( strlen( $text ) < 200 ) {
			return self::pass( null, __( 'Text too short to analyze passive voice.', 'citationrate-ai-visibility' ) );
		}
		$sentences = max( 1, preg_match_all( '/[.!?]+/u', $text ) );
		// Pattern italiano: ausiliare + participio passato (es. "è stato pubblicato", "viene utilizzato").
		$passive = preg_match_all( '/\b(è|sono|era|erano|fu|furono|sarà|saranno|viene|vengono|veniva|venivano|venne|vennero|venuto|venuti|stato|stati|state|state)\s+[a-zàèéìòù]+(at[oiea]|ut[oiea]|it[oiea])\b/iu', $text );
		$ratio = $passive / $sentences;
		if ( $ratio <= 0.10 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( 'Direct style: only %d%% of sentences in passive voice.', 'citationrate-ai-visibility' ), round( $ratio * 100 ) ) );
		}
		if ( $ratio <= 0.20 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 1, sprintf( __( '%d%% of sentences in passive voice: try rewriting some in active voice.', 'citationrate-ai-visibility' ), round( $ratio * 100 ) ) );
		}
		// translators: %d replaced at runtime with dynamic values.
		return self::pass( 0, sprintf( __( '%d%% of sentences in passive voice: too many to be cited by AI.', 'citationrate-ai-visibility' ), round( $ratio * 100 ) ) );
	}

	// ===== L13 — Lunghezza media delle frasi. =====
	private static function check_sentence_length( $ctx ) {
		$text = $ctx['content_text'];
		if ( strlen( $text ) < 200 ) {
			return self::pass( null, __( 'Text too short to measure sentence length.', 'citationrate-ai-visibility' ) );
		}
		$sentences = max( 1, preg_match_all( '/[.!?]+/u', $text ) );
		$words     = max( 1, str_word_count( $text ) );
		$asl       = $words / $sentences;
		if ( $asl <= 20 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( 'Short sentences (avg %d words): easy to quote verbatim.', 'citationrate-ai-visibility' ), round( $asl ) ) );
		}
		if ( $asl <= 28 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 1, sprintf( __( 'Medium sentences (%d words): break up the longer ones.', 'citationrate-ai-visibility' ), round( $asl ) ) );
		}
		// translators: %d replaced at runtime with dynamic values.
		return self::pass( 0, sprintf( __( 'Sentences too long (%d words): an AI will struggle to extract short answers.', 'citationrate-ai-visibility' ), round( $asl ) ) );
	}

	// ===== L14 — Anno corrente nel testo (segnale di attualità). =====
	private static function check_current_year_mention( $ctx ) {
		$current = (int) current_time( 'Y' );
		$previous = $current - 1;
		$text = $ctx['content_text'];
		if ( preg_match( '/\b' . $current . '\b/', $text ) ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( 'The year %d is mentioned in the text.', 'citationrate-ai-visibility' ), $current ) );
		}
		if ( preg_match( '/\b' . $previous . '\b/', $text ) ) {
			// translators: %1$d, %2$d replaced at runtime with dynamic values.
			return self::pass( 1, sprintf( __( 'Year %1$d cited but not %2$d: update the time references.', 'citationrate-ai-visibility' ), $previous, $current ) );
		}
		return self::pass( 0, __( 'No reference to the current year: AI penalizes content that seems outdated.', 'citationrate-ai-visibility' ) );
	}

	// ===== L15 — Lunghezza dei paragrafi (no muri di testo). =====
	private static function check_paragraph_length( $ctx ) {
		if ( ! $ctx['dom'] ) {
			return self::pass( null, __( 'Empty content.', 'citationrate-ai-visibility' ) );
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
			return self::pass( null, __( 'No paragraph in the content.', 'citationrate-ai-visibility' ) );
		}
		if ( 0 === $too_long ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 2, sprintf( __( 'Well-sized paragraphs (%d total).', 'citationrate-ai-visibility' ), $total ) );
		}
		if ( $too_long <= 2 ) {
			// translators: %d replaced at runtime with dynamic values.
			return self::pass( 1, sprintf( __( '%d paragraphs too long: break up the walls of text.', 'citationrate-ai-visibility' ), $too_long ) );
		}
		// translators: %d replaced at runtime with dynamic values.
		return self::pass( 0, sprintf( __( '%d paragraphs too long: the content is hard to scan.', 'citationrate-ai-visibility' ), $too_long ) );
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
				'label'    => isset( $labels[ $pid ] ) ? $labels[ $pid ] : __( 'Check', 'citationrate-ai-visibility' ),
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
