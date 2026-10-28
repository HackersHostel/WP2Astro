<?php
/**
 * Plugin Name:       WP to Astro
 * Description:       Convert this WordPress site into an Astro project. Uses any OpenAI-compatible model (default: Gemma via Ollama) and pushes the result to a new GitHub repo.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Labib
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-to-astro
 */

defined( 'ABSPATH' ) || exit;

define( 'WPTA_VERSION', '0.1.0' );
define( 'WPTA_FILE', __FILE__ );
define( 'WPTA_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPTA_URL', plugin_dir_url( __FILE__ ) );

require_once WPTA_DIR . 'includes/class-wpta-settings.php';
require_once WPTA_DIR . 'includes/class-wpta-llm.php';
require_once WPTA_DIR . 'includes/class-wpta-markdown.php';
require_once WPTA_DIR . 'includes/class-wpta-scaffold.php';
require_once WPTA_DIR . 'includes/class-wpta-exporter.php';
require_once WPTA_DIR . 'includes/class-wpta-ai.php';
require_once WPTA_DIR . 'includes/class-wpta-github.php';
require_once WPTA_DIR . 'includes/class-wpta-job.php';

add_action( 'plugins_loaded', function () {
	WPTA_Settings::init();
	WPTA_Job::init();
} );
