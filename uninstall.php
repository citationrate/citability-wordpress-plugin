<?php
/**
 * Cleanup on plugin uninstall.
 *
 * @package CitabilityScore
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'citability_score_settings' );
delete_option( 'citability_score_onboarded' );

$posts = get_posts(
	array(
		'post_type'   => 'any',
		'post_status' => 'any',
		'fields'      => 'ids',
		'meta_key'    => '_citability_jsonld',
		'numberposts' => -1,
	)
);
foreach ( $posts as $pid ) {
	delete_post_meta( $pid, '_citability_jsonld' );
}
