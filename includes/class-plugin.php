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
		// Translations are loaded automatically by WordPress for .org-hosted
		// plugins since 4.6 (this plugin requires 6.0), so no load_plugin_textdomain() needed.
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

	/**
	 * Asset version = file mtime when readable, else the plugin version.
	 * This auto-busts the browser cache on every edit (essential for local
	 * dev in wp-now), while still giving a stable per-release version in prod.
	 */
	private static function asset_version( $rel_path ) {
		$abs = CITABILITY_SCORE_PATH . $rel_path;
		$mt  = @filemtime( $abs );
		return $mt ? (string) $mt : CITABILITY_SCORE_VERSION;
	}

	public static function enqueue_editor_assets() {
		$handle = 'citationrate-ai-visibility-editor';
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

		wp_enqueue_script( $handle, $src, $deps, self::asset_version( 'assets/js/editor-sidebar.js' ), true );
		wp_set_script_translations( $handle, 'citationrate-ai-visibility', CITABILITY_SCORE_PATH . 'languages' );

		wp_enqueue_style(
			'citationrate-ai-visibility-editor',
			CITABILITY_SCORE_URL . 'assets/css/widget.css',
			array(),
			self::asset_version( 'assets/css/widget.css' )
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
		// JSON_HEX_TAG escapes < and > to < / > so a value containing
		// "</script>" cannot break out of the inline JSON-LD script tag.
		$encoded = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( false === $encoded ) {
			return;
		}
		// wp_print_inline_script_tag() is a safe output function (recognized by
		// the escaping sniffs); the payload is already neutralized above.
		wp_print_inline_script_tag( $encoded, array( 'type' => 'application/ld+json' ) );
	}
}
