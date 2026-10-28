<?php
defined( 'ABSPATH' ) || exit;

/**
 * Convert and sync from the command line — also how coding agents (Claude Code, Codex) drive WP2Astro.
 */
class WPTA_CLI {

	/**
	 * Convert the site to Astro and push it to GitHub (when connected).
	 *
	 * ## OPTIONS
	 *
	 * [--[no-]ai]
	 * : Use the configured AI model for the layout and shortcode cleanup. Default: on.
	 *
	 * [--[no-]github]
	 * : Push to GitHub when connected. Default: on.
	 *
	 * [--zip=<path>]
	 * : Also copy the project zip to this path.
	 *
	 * [--force]
	 * : Start even if another conversion looks like it is running.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wp2astro convert
	 *     wp wp2astro convert --no-ai --no-github --zip=./site.zip
	 *
	 * @when after_wp_load
	 */
	public function convert( array $args, array $assoc ): void {
		$busy = WPTA_Sync::running();
		if ( $busy && empty( $assoc['force'] ) ) {
			WP_CLI::error( "Another conversion is running (job {$busy}). Use --force to start anyway." );
		}
		$zip = $assoc['zip'] ?? '';
		$job = WPTA_Job::create(
			'cli',
			array(
				'ai'     => (bool) WP_CLI\Utils\get_flag_value( $assoc, 'ai', true ),
				'github' => WP_CLI\Utils\get_flag_value( $assoc, 'github', true ) && WPTA_Settings::github_connected(),
				'zip'    => '' !== $zip,
			)
		);
		WP_CLI::log( 'Job ' . $job->id() );
		$job->run_for( PHP_INT_MAX, fn( $line, $pct ) => WP_CLI::log( sprintf( '[%3d%%] %s', $pct, $line ) ) );

		$status = $job->status();
		if ( $status['error'] ) {
			WP_CLI::error( 'Conversion failed: ' . ( $status['result']['error'] ?? 'see the log above' ) );
		}
		if ( '' !== $zip ) {
			$src = WPTA_Job::root() . '/' . $job->id() . '/site.zip';
			if ( is_readable( $src ) && copy( $src, $zip ) ) {
				WP_CLI::log( 'Zip: ' . realpath( $zip ) );
			}
		}
		if ( ! empty( $status['result']['commit'] ) ) {
			WP_CLI::log( 'Commit: ' . $status['result']['commit'] );
		}
		WP_CLI::success( 'Astro project ready in ' . WPTA_Job::root() . '/' . $job->id() . '/site' );
	}

	/**
	 * Show the AI provider, GitHub connection and recent runs.
	 *
	 * @when after_wp_load
	 */
	public function status( array $args, array $assoc ): void {
		$s   = WPTA_Settings::get();
		$p   = WPTA_Settings::provider();
		$llm = WPTA_LLM::from_settings();
		WP_CLI::log( 'AI:      ' . ( $llm ? $llm->label() : $p['label'] . ' (not ready)' ) );
		WP_CLI::log( 'GitHub:  ' . ( $s['github_token'] ? ( $s['github_login'] ?: 'connected' ) . '/' . $s['repo_name'] : 'not connected' ) );
		WP_CLI::log( 'Auto-sync: ' . ( $s['auto_sync'] ? 'on' : 'off' ) );
		$rows = array();
		foreach ( WPTA_Sync::runs() as $r ) {
			$rows[] = array(
				'started' => wp_date( 'Y-m-d H:i', $r['started'] ?? 0 ),
				'trigger' => $r['trigger'] ?? '',
				'status'  => $r['status'] ?? '',
				'entries' => $r['entries'] ?? '',
				'changed' => $r['changed'] ?? '',
				'commit'  => $r['commit'] ?? ( $r['error'] ?? '' ),
			);
		}
		if ( $rows ) {
			WP_CLI\Utils\format_items( 'table', $rows, array_keys( $rows[0] ) );
		}
	}
}
