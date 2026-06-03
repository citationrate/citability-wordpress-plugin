<?php
/**
 * Cleanup on plugin uninstall.
 *
 * @package CitabilityScore
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'citability_score_settings' );
delete_option( 'citability_score_onboarded' );
delete_option( 'citability_score_site_id' );

$citability_score_posts = get_posts(
	array(
		'post_type'   => 'any',
		'post_status' => 'any',
		'fields'      => 'ids',
		// One-time cleanup on uninstall; a meta_key query is acceptable here.
		'meta_key'    => '_citability_jsonld', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'numberposts' => -1,
	)
);
foreach ( $citability_score_posts as $citability_score_pid ) {
	delete_post_meta( $citability_score_pid, '_citability_jsonld' );
}
