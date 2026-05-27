<?php
/**
 * JSON-LD Wizard — schemi assistiti per tipo di pagina (Article, FAQPage, HowTo,
 * Recipe, NewsArticle) e per settore (Product, Event, Restaurant, Hotel, MedicalClinic,
 * ProfessionalService, FinancialService, EducationalOrganization, RealEstateAgent,
 * TouristAttraction, LocalBusiness, Organization, SoftwareApplication).
 *
 * Costruisce JSON-LD validi, fa auto-populate dai dati del post, salva in post_meta
 * e l'output viene reso da Plugin::render_jsonld() agganciato a wp_head.
 *
 * @package CitabilityScore
 */

namespace Citability_Score;

defined( 'ABSPATH' ) || exit;

class Json_Ld_Wizard {

	const SUPPORTED_TYPES = array(
		// Tipi di pagina / contenuto
		'Article',
		'BlogPosting',
		'NewsArticle',
		'FAQPage',
		'HowTo',
		'Recipe',
		'Review',
		'VideoObject',
		'Service',
		'Course',
		'JobPosting',
		// Entità di settore (mappate dai 15 settori CS, label in lingua utente lato UI)
		'Product',
		'SoftwareApplication',
		'Event',
		'Restaurant',
		'Hotel',
		'MedicalClinic',
		'ProfessionalService',
		'FinancialService',
		'EducationalOrganization',
		'RealEstateAgent',
		'TouristAttraction',
		'LocalBusiness',
		'Organization',
	);

	public static function register() {
		// Hook futuri (es. salvataggio asincrono). Per ora il save passa dal REST.
	}

	public static function autopopulate_defaults( $post_id, $type ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}
		$author     = get_userdata( (int) $post->post_author );
		$image      = get_the_post_thumbnail_url( $post_id, 'large' );
		$permalink  = get_permalink( $post_id );
		$site_name  = get_bloginfo( 'name' );

		// Date: get_post_time() può ritornare false su draft/auto-draft.
		$published = get_post_time( 'c', true, $post );
		if ( false === $published || empty( $published ) ) {
			$published = current_time( 'c' );
		}
		$modified = get_post_modified_time( 'c', true, $post );
		if ( false === $modified || empty( $modified ) ) {
			$modified = $published;
		}

		// Headline: fallback a primo H1/H2 nel contenuto, poi placeholder data.
		$headline = trim( (string) $post->post_title );
		if ( '' === $headline ) {
			$content_text = wp_strip_all_tags( (string) $post->post_content );
			$first_line   = strtok( $content_text, "\n" );
			$first_line   = trim( (string) $first_line );
			if ( '' !== $first_line ) {
				$headline = mb_substr( $first_line, 0, 110 );
			} else {
				$headline = sprintf(
					/* translators: %s: data odierna */
					// translators: %s replaced at runtime with dynamic values.
					__( 'Article of %s', 'citationrate-ai-visibility' ),
					date_i18n( get_option( 'date_format' ) )
				);
			}
		}

		$base = array(
			'@context' => 'https://schema.org',
			'@type'    => $type,
		);

		// Auto-extraction from the saved page content; reusing the WordPress core hook.
		$content_html = apply_filters( 'the_content', (string) $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$dom          = self::content_dom( $content_html );
		$desc         = self::clean_text( $post->post_excerpt );
		if ( '' === $desc ) {
			$desc = self::first_paragraph( $dom );
		}
		$ul_items = self::list_items( $dom, 'ul' );
		$ol_items = self::list_items( $dom, 'ol' );
		$to_steps = static function ( $items ) {
			$out = array();
			foreach ( $items as $t ) {
				$out[] = array( '@type' => 'HowToStep', 'text' => $t );
			}
			return $out;
		};

		switch ( $type ) {
			case 'Article':
			case 'BlogPosting':
			case 'NewsArticle':
				$base = array_merge(
					$base,
					array(
						'headline'      => $headline,
						'datePublished' => $published,
						'dateModified'  => $modified,
						'author'        => array(
							'@type' => 'Person',
							'name'  => $author ? $author->display_name : '',
						),
						'publisher'     => array(
							'@type' => 'Organization',
							'name'  => $site_name,
						),
						'image'         => $image ? array( $image ) : array(),
						'mainEntityOfPage' => $permalink,
					)
				);
				break;

			case 'FAQPage':
				$pairs = self::faq_pairs( $dom );
				if ( empty( $pairs ) ) {
					$pairs = array(
						array(
							'@type'          => 'Question',
							'name'           => '',
							'acceptedAnswer' => array(
								'@type' => 'Answer',
								'text'  => '',
							),
						),
					);
				}
				$base['mainEntity'] = $pairs;
				break;

			case 'HowTo':
				$steps = $to_steps( $ol_items ? $ol_items : $ul_items );
				if ( empty( $steps ) ) {
					$steps = array(
						array(
							'@type' => 'HowToStep',
							'text'  => '',
						),
					);
				}
				$base = array_merge(
					$base,
					array(
						'name' => $headline,
						'step' => $steps,
					)
				);
				break;

			case 'Recipe':
				$ingredients  = $ul_items ? $ul_items : array( '' );
				$instructions = $to_steps( $ol_items );
				if ( empty( $instructions ) ) {
					$instructions = array(
						array(
							'@type' => 'HowToStep',
							'text'  => '',
						),
					);
				}
				$base = array_merge(
					$base,
					array(
						'name'               => $headline,
						'author'             => array(
							'@type' => 'Person',
							'name'  => $author ? $author->display_name : '',
						),
						'image'              => $image ? array( $image ) : array(),
						'recipeIngredient'   => $ingredients,
						'recipeInstructions' => $instructions,
					)
				);
				break;

			case 'LocalBusiness':
			case 'Restaurant':
			case 'Hotel':
			case 'MedicalClinic':
			case 'ProfessionalService':
			case 'FinancialService':
			case 'RealEstateAgent':
			case 'TouristAttraction':
				$base = array_merge(
					$base,
					array(
						'name'        => $site_name,
						'url'         => home_url(),
						'address'     => array(
							'@type'           => 'PostalAddress',
							'streetAddress'   => '',
							'addressLocality' => '',
							'addressRegion'   => '',
							'postalCode'      => '',
							'addressCountry'  => 'IT',
						),
						'telephone'   => '',
						'priceRange'  => '',
					)
				);
				break;

			case 'Organization':
			case 'EducationalOrganization':
				$base = array_merge(
					$base,
					array(
						'name'  => $site_name,
						'url'   => home_url(),
						'logo'  => '',
						'sameAs' => array(),
					)
				);
				break;

			case 'Product':
				$base = array_merge(
					$base,
					array(
						'name'        => $headline,
						'image'       => $image ? array( $image ) : array(),
						'description' => $desc,
						'brand'       => array(
							'@type' => 'Brand',
							'name'  => $site_name,
						),
						'offers'      => array(
							'@type'         => 'Offer',
							'price'         => '',
							'priceCurrency' => 'EUR',
							'availability'  => 'https://schema.org/InStock',
							'url'           => $permalink,
						),
					)
				);
				break;

			case 'SoftwareApplication':
				$base = array_merge(
					$base,
					array(
						'name'                => $headline,
						'applicationCategory' => '',
						'operatingSystem'     => '',
						'offers'              => array(
							'@type'         => 'Offer',
							'price'         => '0',
							'priceCurrency' => 'EUR',
						),
					)
				);
				break;

			case 'Event':
				$base = array_merge(
					$base,
					array(
						'name'        => $headline,
						'startDate'   => '',
						'endDate'     => '',
						'eventStatus' => 'https://schema.org/EventScheduled',
						'location'    => array(
							'@type'   => 'Place',
							'name'    => '',
							'address' => array(
								'@type'           => 'PostalAddress',
								'addressLocality' => '',
								'addressCountry'  => 'IT',
							),
						),
						'image'       => $image ? array( $image ) : array(),
					)
				);
				break;

			case 'Review':
				$base = array_merge(
					$base,
					array(
						'name'         => $headline,
						'reviewBody'   => $desc,
						'itemReviewed' => array(
							'@type' => 'Thing',
							'name'  => '',
						),
						'reviewRating' => array(
							'@type'       => 'Rating',
							'ratingValue' => '',
							'bestRating'  => '5',
							'worstRating' => '1',
						),
						'author'       => array(
							'@type' => 'Person',
							'name'  => $author ? $author->display_name : '',
						),
					)
				);
				break;

			case 'VideoObject':
				$base = array_merge(
					$base,
					array(
						'name'         => $headline,
						'description'  => '',
						'thumbnailUrl' => $image ? array( $image ) : array(),
						'uploadDate'   => $published,
						'contentUrl'   => '',
						'embedUrl'     => '',
					)
				);
				break;

			case 'Service':
				$base = array_merge(
					$base,
					array(
						'name'        => $headline,
						'serviceType' => '',
						'description' => $desc,
						'provider'    => array(
							'@type' => 'Organization',
							'name'  => $site_name,
						),
						'areaServed'  => '',
					)
				);
				break;

			case 'Course':
				$base = array_merge(
					$base,
					array(
						'name'        => $headline,
						'description' => $desc,
						'provider'    => array(
							'@type'  => 'Organization',
							'name'   => $site_name,
							'sameAs' => home_url(),
						),
					)
				);
				break;

			case 'JobPosting':
				$base = array_merge(
					$base,
					array(
						'title'              => $headline,
						'description'        => '',
						'datePosted'         => $published,
						'employmentType'     => '',
						'hiringOrganization' => array(
							'@type' => 'Organization',
							'name'  => $site_name,
							'sameAs' => home_url(),
						),
						'jobLocation'        => array(
							'@type'   => 'Place',
							'address' => array(
								'@type'           => 'PostalAddress',
								'addressLocality' => '',
								'addressCountry'  => 'IT',
							),
						),
					)
				);
				break;
		}

		return $base;
	}

	private static function content_dom( $html ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return null;
		}
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		return $dom;
	}

	private static function clean_text( $s ) {
		return trim( preg_replace( '/\s+/', ' ', (string) $s ) );
	}

	private static function list_items( $dom, $tag ) {
		$out = array();
		if ( ! $dom ) {
			return $out;
		}
		$lists = $dom->getElementsByTagName( $tag );
		if ( 0 === $lists->length ) {
			return $out;
		}
		foreach ( $lists->item( 0 )->getElementsByTagName( 'li' ) as $li ) {
			$t = self::clean_text( $li->textContent );
			if ( '' !== $t ) {
				$out[] = $t;
			}
		}
		return $out;
	}

	private static function first_paragraph( $dom ) {
		if ( ! $dom ) {
			return '';
		}
		foreach ( $dom->getElementsByTagName( 'p' ) as $p ) {
			$t = self::clean_text( $p->textContent );
			if ( '' !== $t ) {
				return $t;
			}
		}
		return '';
	}

	private static function faq_pairs( $dom ) {
		$pairs = array();
		if ( ! $dom ) {
			return $pairs;
		}
		foreach ( array( 'h2', 'h3' ) as $htag ) {
			foreach ( $dom->getElementsByTagName( $htag ) as $h ) {
				$q = self::clean_text( $h->textContent );
				if ( '' === $q ) {
					continue;
				}
				$ans  = '';
				$node = $h->nextSibling;
				while ( $node ) {
					if ( XML_ELEMENT_NODE === $node->nodeType ) {
						if ( preg_match( '/^h[1-6]$/i', $node->nodeName ) ) {
							break;
						}
						$t = self::clean_text( $node->textContent );
						if ( '' !== $t ) {
							$ans = $t;
							break;
						}
					}
					$node = $node->nextSibling;
				}
				$pairs[] = array(
					'@type'          => 'Question',
					'name'           => $q,
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $ans,
					),
				);
			}
		}
		return $pairs;
	}

	public static function validate( $data ) {
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'invalid', __( 'Invalid payload.', 'citationrate-ai-visibility' ) );
		}
		if ( empty( $data['@context'] ) ) {
			$data['@context'] = 'https://schema.org';
		}
		if ( empty( $data['@type'] ) ) {
			return new \WP_Error( 'invalid_type', __( 'Missing @type.', 'citationrate-ai-visibility' ) );
		}
		$type = is_array( $data['@type'] ) ? reset( $data['@type'] ) : $data['@type'];
		if ( ! in_array( $type, self::SUPPORTED_TYPES, true ) ) {
			// translators: %s replaced at runtime with dynamic values.
			return new \WP_Error( 'unsupported_type', sprintf( __( 'Unsupported type: %s', 'citationrate-ai-visibility' ), $type ) );
		}

		$errors = self::required_fields_for( $type, $data );
		if ( ! empty( $errors ) ) {
			// translators: %s replaced at runtime with dynamic values.
			return new \WP_Error( 'missing_fields', sprintf( __( 'Missing required fields: %s', 'citationrate-ai-visibility' ), implode( ', ', $errors ) ) );
		}

		return $data;
	}

	private static function required_fields_for( $type, $data ) {
		$required = array(
			'Article'                 => array( 'headline', 'author', 'datePublished' ),
			'BlogPosting'             => array( 'headline', 'author', 'datePublished' ),
			'NewsArticle'             => array( 'headline', 'author', 'datePublished' ),
			'FAQPage'                 => array( 'mainEntity' ),
			'HowTo'                   => array( 'name', 'step' ),
			'Recipe'                  => array( 'name', 'recipeIngredient', 'recipeInstructions' ),
			'Product'                 => array( 'name', 'offers' ),
			'SoftwareApplication'     => array( 'name', 'applicationCategory' ),
			'Event'                   => array( 'name', 'startDate', 'location' ),
			'Review'                  => array( 'itemReviewed', 'reviewRating', 'author' ),
			'VideoObject'             => array( 'name', 'uploadDate' ),
			'Service'                 => array( 'name', 'provider' ),
			'Course'                  => array( 'name', 'provider' ),
			'JobPosting'              => array( 'title', 'datePosted', 'hiringOrganization' ),
			'LocalBusiness'           => array( 'name', 'address' ),
			'Restaurant'              => array( 'name', 'address' ),
			'Hotel'                   => array( 'name', 'address' ),
			'MedicalClinic'           => array( 'name', 'address' ),
			'ProfessionalService'     => array( 'name', 'address' ),
			'FinancialService'        => array( 'name', 'address' ),
			'RealEstateAgent'         => array( 'name', 'address' ),
			'TouristAttraction'       => array( 'name' ),
			'EducationalOrganization' => array( 'name', 'url' ),
			'Organization'            => array( 'name', 'url' ),
		);
		$missing = array();
		foreach ( $required[ $type ] ?? array() as $f ) {
			if ( empty( $data[ $f ] ) ) {
				$missing[] = $f;
			}
		}
		return $missing;
	}

	public static function save( $post_id, $data ) {
		$validated = self::validate( $data );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		$json = wp_json_encode( $validated, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		update_post_meta( $post_id, CITABILITY_SCORE_META_JSONLD, $json );
		return $validated;
	}

	public static function get( $post_id ) {
		$raw = get_post_meta( $post_id, CITABILITY_SCORE_META_JSONLD, true );
		if ( empty( $raw ) ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : null;
	}
}
