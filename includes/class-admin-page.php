<?php
/**
 * Admin settings page (placeholder per ora, da espandere nella fase 5).
 *
 * @package CitabilityScore
 */

namespace Citability_Score;

defined( 'ABSPATH' ) || exit;

class Admin_Page {

	const OPTION_KEY  = 'citability_score_settings';
	const SITE_ID_KEY = 'citability_score_site_id';

	/**
	 * Random per-install id used only to de-duplicate installs in anonymous
	 * usage stats. Generated lazily, never linkable to a person or domain.
	 */
	public static function site_id() {
		$id = get_option( self::SITE_ID_KEY, '' );
		if ( empty( $id ) ) {
			$id = wp_generate_uuid4();
			update_option( self::SITE_ID_KEY, $id, false );
		}
		return $id;
	}

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
	}

	public static function menu() {
		add_options_page(
			__( 'CitationRate AI Visibility', 'citationrate-ai-visibility' ),
			__( 'CitationRate AI Visibility', 'citationrate-ai-visibility' ),
			'manage_options',
			'citationrate-ai-visibility',
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
					'share_usage'    => 0,
				),
			)
		);
	}

	public static function sanitize( $input ) {
		$schema = isset( $input['default_schema'] ) ? sanitize_text_field( $input['default_schema'] ) : 'Article';
		if ( ! in_array( $schema, Json_Ld_Wizard::SUPPORTED_TYPES, true ) ) {
			$schema = 'Article';
		}
		return array(
			'api_key'        => isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '',
			'default_schema' => $schema,
			'share_usage'    => empty( $input['share_usage'] ) ? 0 : 1,
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$opts = get_option( self::OPTION_KEY, array() );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'CitationRate AI Visibility', 'citationrate-ai-visibility' ); ?></h1>
			<p><?php echo esc_html__( 'Optimize your site content to be cited by AI models (ChatGPT, Gemini, Claude, Perplexity).', 'citationrate-ai-visibility' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'citability_score' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="citability_api_key"><?php echo esc_html__( 'CitationRate API key (optional)', 'citationrate-ai-visibility' ); ?></label>
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
								<?php echo esc_html__( 'Connect a CitationRate account to unlock the full audit (50+ parameters, backlinks, real AI citation rate). Without a key the plugin runs in lite mode.', 'citationrate-ai-visibility' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="citability_default_schema"><?php echo esc_html__( 'Default JSON-LD schema', 'citationrate-ai-visibility' ); ?></label>
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
					<tr>
						<th scope="row"><?php echo esc_html__( 'Usage statistics', 'citationrate-ai-visibility' ); ?></th>
						<td>
							<label for="citability_share_usage">
								<input
									type="checkbox"
									id="citability_share_usage"
									name="<?php echo esc_attr( self::OPTION_KEY ); ?>[share_usage]"
									value="1"
									<?php checked( ! empty( $opts['share_usage'] ) ); ?>
								/>
								<?php echo esc_html__( 'Help improve the plugin by sharing anonymous usage statistics', 'citationrate-ai-visibility' ); ?>
							</label>
							<p class="description">
								<?php echo esc_html__( 'Optional and off by default. When enabled, the plugin sends a random, anonymous install ID together with coarse usage events (e.g. which content type you pick in the wizard, your score band as a range, and which buttons you click). It never sends your IP address, your page URLs or any page content, and you can turn it off at any time.', 'citationrate-ai-visibility' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
