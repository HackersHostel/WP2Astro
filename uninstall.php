<?php
/**
 * Remove WP2Astro's settings, tokens, history and job files. The GitHub repo is not touched.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'wpta_settings', 'wpta_chatgpt', 'wpta_sync_manifest', 'wpta_runs', 'wpta_running', 'wpta_last_layout_log', 'wpta_changed', 'wpta_setup_done', 'wpta_cdn_files' ) as $wpta_option ) {
	delete_option( $wpta_option );
}
delete_site_transient( 'wpta_release' );
wp_clear_scheduled_hook( 'wpta_auto_sync' );
wp_clear_scheduled_hook( 'wpta_run_job' );

require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
( new WP_Filesystem_Direct( null ) )->rmdir( wp_get_upload_dir()['basedir'] . '/wp2astro', true );
