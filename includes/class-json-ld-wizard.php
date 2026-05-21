<?php
/**
 * JSON-LD Wizard — 5 schemi assistiti (Article, FAQPage, HowTo, Recipe, LocalBusiness).
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
		'Article',
		'BlogPosting',
		'FAQPage',
		'HowTo',
		'Recipe',
		'LocalBusiness',
		'Restaurant',
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
					__( 'Articolo del %s', 'citability-score' ),
					date_i18n( get_option( 'date_format' ) )
				);
			}
		}

		$base = array(
			'@context' => 'https://schema.org',
			'@type'    => $type,
		);

		switch ( $type ) {
			case 'Article':
			case 'BlogPosting':
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
				$base['mainEntity'] = array(
					array(
						'@type'          => 'Question',
						'name'           => '',
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => '',
						),
					),
				);
				break;

			case 'HowTo':
				$base = array_merge(
					$base,
					array(
						'name'  => $headline,
						'step'  => array(
							array(
								'@type' => 'HowToStep',
								'text'  => __( 'Descrivi il primo passo…', 'citability-score' ),
							),
						),
					)
				);
				break;

			case 'Recipe':
				$base = array_merge(
					$base,
					array(
						'name'              => $headline,
						'author'            => array(
							'@type' => 'Person',
							'name'  => $author ? $author->display_name : '',
						),
						'image'             => $image ? array( $image ) : array(),
						'recipeIngredient'  => array( __( 'Ingrediente 1', 'citability-score' ) ),
						'recipeInstructions' => array(
							array(
								'@type' => 'HowToStep',
								'text'  => __( 'Primo passo della ricetta…', 'citability-score' ),
							),
						),
					)
				);
				break;

			case 'LocalBusiness':
			case 'Restaurant':
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
		}

		return $base;
	}

	public static function validate( $data ) {
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'invalid', __( 'Payload non valido.', 'citability-score' ) );
		}
		if ( empty( $data['@context'] ) ) {
			$data['@context'] = 'https://schema.org';
		}
		if ( empty( $data['@type'] ) ) {
			return new \WP_Error( 'invalid_type', __( 'Manca @type.', 'citability-score' ) );
		}
		$type = is_array( $data['@type'] ) ? reset( $data['@type'] ) : $data['@type'];
		if ( ! in_array( $type, self::SUPPORTED_TYPES, true ) ) {
			return new \WP_Error( 'unsupported_type', sprintf( __( 'Tipo non supportato: %s', 'citability-score' ), $type ) );
		}

		$errors = self::required_fields_for( $type, $data );
		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'missing_fields', sprintf( __( 'Campi obbligatori mancanti: %s', 'citability-score' ), implode( ', ', $errors ) ) );
		}

		return $data;
	}

	private static function required_fields_for( $type, $data ) {
		$required = array(
			'Article'       => array( 'headline', 'author', 'datePublished' ),
			'BlogPosting'   => array( 'headline', 'author', 'datePublished' ),
			'FAQPage'       => array( 'mainEntity' ),
			'HowTo'         => array( 'name', 'step' ),
			'Recipe'        => array( 'name', 'recipeIngredient', 'recipeInstructions' ),
			'LocalBusiness' => array( 'name', 'address' ),
			'Restaurant'    => array( 'name', 'address' ),
			'Organization'  => array( 'name', 'url' ),
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
