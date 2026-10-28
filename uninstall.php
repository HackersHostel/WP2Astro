<?php
/**
 * Remove WP2Astro's settings, tokens, history and job files. The GitHub repo is not touched.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'wpta_settings', 'wpta_chatgpt', 'wpta_sync_manifest', 'wpta_runs', 'wpta_running', 'wpta_last_layout_log' ) as $option ) {
	delete_option( $option );
}
delete_site_transient( 'wpta_release' );
wp_clear_scheduled_hook( 'wpta_auto_sync' );
wp_clear_scheduled_hook( 'wpta_run_job' );

$dir = wp_get_upload_dir()['basedir'] . '/wp2astro';
if ( is_dir( $dir ) ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) {
		$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
	}
	rmdir( $dir );
}
