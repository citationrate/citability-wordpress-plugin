<?php
/**
 * Plugin bootstrap.
 *
 * @package CitabilityScore
 */

namespace Citability_Score;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function boot() {
		load_plugin_textdomain( 'citability-score', false, dirname( plugin_basename( CITABILITY_SCORE_FILE ) ) . '/languages' );

		Rest_Api::register();
		Admin_Page::register();
		Meta_Box::register();
		Json_Ld_Wizard::register();

		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_action( 'wp_head', array( __CLASS__, 'render_jsonld' ), 5 );
	}

	public static function activate() {
		add_option( 'citability_score_onboarded', 0 );
	}

	public static function deactivate() {
		// no-op for now; uninstall.php handles full cleanup.
	}

	public static function enqueue_editor_assets() {
		$handle = 'citability-score-editor';
		$src    = CITABILITY_SCORE_URL . 'assets/js/editor-sidebar.js';
		$deps   = array(
			'wp-plugins',
			'wp-edit-post',
			'wp-element',
			'wp-components',
			'wp-data',
			'wp-api-fetch',
			'wp-i18n',
		);

		wp_enqueue_script( $handle, $src, $deps, CITABILITY_SCORE_VERSION, true );
		wp_set_script_translations( $handle, 'citability-score' );

		wp_enqueue_style(
			'citability-score-editor',
			CITABILITY_SCORE_URL . 'assets/css/widget.css',
			array(),
			CITABILITY_SCORE_VERSION
		);
	}

	public static function render_jsonld() {
		if ( ! is_singular() ) {
			return;
		}
		$json = get_post_meta( get_the_ID(), CITABILITY_SCORE_META_JSONLD, true );
		if ( empty( $json ) ) {
			return;
		}
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) || JSON_ERROR_NONE !== json_last_error() ) {
			return;
		}
		echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	}
}
