<?php
/**
 * Plugin Name:       CitationRate AI Visibility
 * Plugin URI:        https://citationrate.com/citability-score/
 * Description:       AI citability score for every page (LLMs, AI Overviews) plus a guided JSON-LD wizard. Optimize your content to be cited by ChatGPT, Gemini, Claude and Perplexity.
 * Version:           0.3.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            CitationRate
 * Author URI:        https://citationrate.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       citationrate-ai-visibility
 * Domain Path:       /languages
 *
 * @package CitabilityScore
 */

defined( 'ABSPATH' ) || exit;

define( 'CITABILITY_SCORE_VERSION', '0.3.2' );
define( 'CITABILITY_SCORE_FILE', __FILE__ );
define( 'CITABILITY_SCORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'CITABILITY_SCORE_URL', plugin_dir_url( __FILE__ ) );
define( 'CITABILITY_SCORE_META_JSONLD', '_citability_jsonld' );

require_once CITABILITY_SCORE_PATH . 'includes/class-plugin.php';
require_once CITABILITY_SCORE_PATH . 'includes/class-on-page-scorer.php';
require_once CITABILITY_SCORE_PATH . 'includes/class-json-ld-wizard.php';
require_once CITABILITY_SCORE_PATH . 'includes/class-rest-api.php';
require_once CITABILITY_SCORE_PATH . 'includes/class-admin-page.php';
require_once CITABILITY_SCORE_PATH . 'includes/class-meta-box.php';

add_action( 'plugins_loaded', array( 'Citability_Score\\Plugin', 'boot' ) );

register_activation_hook( __FILE__, array( 'Citability_Score\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Citability_Score\\Plugin', 'deactivate' ) );
