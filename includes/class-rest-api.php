<?php
/**
 * REST API endpoints per il widget Gutenberg.
 *
 * @package CitabilityScore
 */

namespace Citability_Score;

defined( 'ABSPATH' ) || exit;

class Rest_Api {

	const NS = 'citability/v1';

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			self::NS,
			'/score/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_score' ),
				'args'                => array(
					'post_id' => array(
						'validate_callback' => static function ( $v ) {
							return is_numeric( $v );
						},
					),
				),
				'permission_callback' => array( __CLASS__, 'can_edit_post' ),
			)
		);

		register_rest_route(
			self::NS,
			'/jsonld/(?P<post_id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_jsonld' ),
					'permission_callback' => array( __CLASS__, 'can_edit_post' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'put_jsonld' ),
					'permission_callback' => array( __CLASS__, 'can_edit_post' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_jsonld' ),
					'permission_callback' => array( __CLASS__, 'can_edit_post' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/jsonld/(?P<post_id>\d+)/template',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_jsonld_template' ),
				'args'                => array(
					'type' => array( 'required' => true ),
				),
				'permission_callback' => array( __CLASS__, 'can_edit_post' ),
			)
		);
	}

	public static function can_edit_post( $request ) {
		$post_id = (int) $request['post_id'];
		return current_user_can( 'edit_post', $post_id );
	}

	public static function get_score( $request ) {
		$result = On_Page_Scorer::score_post( (int) $request['post_id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	public static function get_jsonld( $request ) {
		$post_id = (int) $request['post_id'];
		return rest_ensure_response( array( 'data' => Json_Ld_Wizard::get( $post_id ) ) );
	}

	public static function put_jsonld( $request ) {
		$post_id = (int) $request['post_id'];
		$data    = $request->get_json_params();
		$payload = isset( $data['data'] ) ? $data['data'] : $data;
		$result  = Json_Ld_Wizard::save( $post_id, $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'data' => $result, 'saved' => true ) );
	}

	public static function delete_jsonld( $request ) {
		$post_id = (int) $request['post_id'];
		delete_post_meta( $post_id, CITABILITY_SCORE_META_JSONLD );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public static function get_jsonld_template( $request ) {
		$post_id = (int) $request['post_id'];
		$type    = sanitize_text_field( $request['type'] );
		$tpl     = Json_Ld_Wizard::autopopulate_defaults( $post_id, $type );
		return rest_ensure_response( array( 'data' => $tpl ) );
	}
}
