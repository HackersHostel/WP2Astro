<?php
/**
 * Plugin Name:       WP2Astro
 * Plugin URI:        https://github.com/HackersHostel/wp2astro
 * Description:       Turn this WordPress site into a fast Astro site with the same URLs, push it to GitHub and keep it in sync. Works with your ChatGPT plan, OpenRouter (Claude, GPT, free models) or Claude Code.
 * Version:           0.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Hackers Hostel
 * Author URI:        https://hackershostel.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp2astro
 * Update URI:        https://github.com/HackersHostel/wp2astro
 */

defined( 'ABSPATH' ) || exit;

define( 'WPTA_VERSION', '0.2.0' );
define( 'WPTA_FILE', __FILE__ );
define( 'WPTA_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPTA_URL', plugin_dir_url( __FILE__ ) );

require_once WPTA_DIR . 'includes/class-wpta-secrets.php';
require_once WPTA_DIR . 'includes/class-wpta-settings.php';
require_once WPTA_DIR . 'includes/class-wpta-llm.php';
require_once WPTA_DIR . 'includes/class-wpta-chatgpt.php';
require_once WPTA_DIR . 'includes/class-wpta-connect.php';
require_once WPTA_DIR . 'includes/class-wpta-markdown.php';
require_once WPTA_DIR . 'includes/class-wpta-scaffold.php';
require_once WPTA_DIR . 'includes/class-wpta-agent-kit.php';
require_once WPTA_DIR . 'includes/class-wpta-exporter.php';
require_once WPTA_DIR . 'includes/class-wpta-ai.php';
require_once WPTA_DIR . 'includes/class-wpta-github.php';
require_once WPTA_DIR . 'includes/class-wpta-sync.php';
require_once WPTA_DIR . 'includes/class-wpta-job.php';
require_once WPTA_DIR . 'includes/class-wpta-admin.php';
require_once WPTA_DIR . 'includes/class-wpta-updater.php';

add_action( 'plugins_loaded', function () {
	WPTA_Admin::init();
	WPTA_Connect::init();
	WPTA_Job::init();
	WPTA_Sync::init();
	WPTA_Updater::init();
} );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once WPTA_DIR . 'includes/class-wpta-cli.php';
	WP_CLI::add_command( 'wp2astro', 'WPTA_CLI' );
}

register_deactivation_hook( __FILE__, array( 'WPTA_Sync', 'deactivate' ) );
