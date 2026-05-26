<?php
/**
 * Admin settings page (placeholder per ora, da espandere nella fase 5).
 *
 * @package CitabilityScore
 */

namespace Citability_Score;

defined( 'ABSPATH' ) || exit;

class Admin_Page {

	const OPTION_KEY = 'citability_score_settings';

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
	}

	public static function menu() {
		add_options_page(
			__( 'Citability Score', 'citability-score' ),
			__( 'Citability Score', 'citability-score' ),
			'manage_options',
			'citability-score',
			array( __CLASS__, 'render' )
		);
	}

	public static function settings() {
		register_setting(
			'citability_score',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(
					'api_key'        => '',
					'default_schema' => 'Article',
				),
			)
		);
	}

	public static function sanitize( $input ) {
		return array(
			'api_key'        => isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '',
			'default_schema' => isset( $input['default_schema'] ) ? sanitize_text_field( $input['default_schema'] ) : 'Article',
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$opts = get_option( self::OPTION_KEY, array() );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Citability Score', 'citability-score' ); ?></h1>
			<p><?php echo esc_html__( 'Optimize your site content to be cited by AI models (ChatGPT, Gemini, Claude, Perplexity).', 'citability-score' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'citability_score' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="citability_api_key"><?php echo esc_html__( 'CitationRate API key (optional)', 'citability-score' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="citability_api_key"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]"
								value="<?php echo esc_attr( $opts['api_key'] ?? '' ); ?>"
								class="regular-text"
								placeholder="cr_..."
							/>
							<p class="description">
								<?php echo esc_html__( 'Connect a CitationRate account to unlock the full audit (50+ parameters, backlinks, real AI citation rate). Without a key the plugin runs in lite mode.', 'citability-score' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="citability_default_schema"><?php echo esc_html__( 'Default JSON-LD schema', 'citability-score' ); ?></label>
						</th>
						<td>
							<select id="citability_default_schema" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_schema]">
								<?php foreach ( Json_Ld_Wizard::SUPPORTED_TYPES as $t ) : ?>
									<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $opts['default_schema'] ?? 'Article', $t ); ?>>
										<?php echo esc_html( $t ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
