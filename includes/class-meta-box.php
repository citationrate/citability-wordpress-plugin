<?php
/**
 * Meta box fallback per Classic Editor (read-only sintetico).
 *
 * @package CitabilityScore
 */

namespace Citability_Score;

defined( 'ABSPATH' ) || exit;

class Meta_Box {

	public static function register() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add' ) );
	}

	public static function add() {
		add_meta_box(
			'citationrate-ai-visibility-box',
			__( 'Citability Score', 'citationrate-ai-visibility' ),
			array( __CLASS__, 'render' ),
			array( 'post', 'page' ),
			'side',
			'high'
		);
	}

	public static function render( $post ) {
		$result = On_Page_Scorer::score_post( $post->ID );
		if ( is_wp_error( $result ) ) {
			echo '<p>' . esc_html( $result->get_error_message() ) . '</p>';
			return;
		}
		$score = (int) $result['score'];
		$band  = $result['band'];
		?>
		<div class="citability-meta-box">
			<div class="citationrate-ai-visibility citability-band-<?php echo esc_attr( $band ); ?>">
				<strong><?php echo esc_html( $score ); ?></strong> / 100
			</div>
			<p class="description">
				<?php echo esc_html__( 'Lite on-page estimate. Open the Block Editor for the full breakdown and the JSON-LD wizard.', 'citationrate-ai-visibility' ); ?>
			</p>
		</div>
		<?php
	}
}
