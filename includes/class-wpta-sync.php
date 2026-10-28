<?php
defined( 'ABSPATH' ) || exit;

/**
 * Smart sync: push only what changed, never overwrite edits made in the repo,
 * keep a run history, and optionally re-sync in the background when content changes.
 */
class WPTA_Sync {

	const MANIFEST = 'wpta_sync_manifest';
	const RUNS     = 'wpta_runs';
	const LOCK     = 'wpta_running';
	const HOOK     = 'wpta_auto_sync';
	const RUN_HOOK = 'wpta_run_job';
	const DELAY    = 120;
	const MAX_RUNS = 20;

	public static function init(): void {
		add_action( 'transition_post_status', array( __CLASS__, 'on_status' ), 10, 3 );
		add_action( 'post_updated', array( __CLASS__, 'on_update' ), 10, 3 );
		add_action( self::HOOK, array( __CLASS__, 'auto_run' ) );
		add_action( self::RUN_HOOK, array( __CLASS__, 'cron_step' ) );
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::RUN_HOOK );
	}

	/* ---------------------------------------------------------------- Plan */

	/**
	 * Decide the next tree. Pure function (unit-tested).
	 *
	 * @param array<string,string> $local    path => sha of the freshly generated site.
	 * @param array<string,string> $remote   path => sha on the branch head.
	 * @param array<string,string> $manifest path => sha we pushed last time (empty on the first sync).
	 * @param string[]             $keep     Paths to leave as they are in the repo when present there.
	 * @return array{tree:array<string,string>, upload:string[], changed:string[], deleted:string[], kept:string[], noop:bool}
	 */
	public static function plan( array $local, array $remote, array $manifest, array $keep = array() ): array {
		$tree    = array();
		$changed = array();
		$kept    = array();
		$deleted = array();
		$first   = ! $manifest;

		foreach ( $local as $path => $sha ) {
			$rs = $remote[ $path ] ?? null;
			$ms = $manifest[ $path ] ?? null;
			if ( null !== $rs && $rs !== $sha && (
				in_array( $path, $keep, true )
				|| ( null !== $ms && $rs !== $ms )    // Edited in the repo since our last push.
				|| ( null === $ms && ! $first )      // Added in the repo by someone else.
			) ) {
				$tree[ $path ] = $rs;
				$kept[]        = $path;
				continue;
			}
			$tree[ $path ] = $sha;
			if ( $rs !== $sha ) {
				$changed[] = $path;
			}
		}

		foreach ( $remote as $path => $rs ) {
			if ( isset( $local[ $path ] ) ) {
				continue;
			}
			if ( isset( $manifest[ $path ] ) && $manifest[ $path ] === $rs ) {
				$deleted[] = $path; // We created it, nobody touched it, and WordPress no longer has it.
			} else {
				$tree[ $path ] = $rs; // Someone's own file (or an edited one): leave it alone.
			}
		}

		$have   = array_flip( $remote );
		$upload = array();
		$seen   = array();
		foreach ( $changed as $path ) {
			$sha = $tree[ $path ];
			if ( ! isset( $have[ $sha ] ) && ! isset( $seen[ $sha ] ) ) {
				$upload[]     = $path;
				$seen[ $sha ] = true;
			}
		}

		ksort( $tree );
		$sorted_remote = $remote;
		ksort( $sorted_remote );
		return array(
			'tree'    => $tree,
			'upload'  => $upload,
			'changed' => $changed,
			'deleted' => $deleted,
			'kept'    => $kept,
			'noop'    => $tree === $sorted_remote,
		);
	}

	/** Manifest to remember after a successful push: our shas for files we own, previous shas for kept ones. */
	public static function next_manifest( array $local, array $plan, array $manifest ): array {
		$out = array();
		foreach ( $local as $path => $sha ) {
			$out[ $path ] = in_array( $path, $plan['kept'], true ) ? ( $manifest[ $path ] ?? $sha ) : $sha;
		}
		return $out;
	}

	public static function manifest( string $repo ): array {
		$all = get_option( self::MANIFEST, array() );
		return is_array( $all[ $repo ]['files'] ?? null ) ? $all[ $repo ]['files'] : array();
	}

	public static function save_manifest( string $repo, array $files, string $commit ): void {
		$all          = get_option( self::MANIFEST, array() );
		$all          = is_array( $all ) ? $all : array();
		$all[ $repo ] = array( 'files' => $files, 'commit' => $commit, 'time' => time() );
		if ( false === get_option( self::MANIFEST, false ) ) {
			add_option( self::MANIFEST, $all, '', false );
		} else {
			update_option( self::MANIFEST, $all, false );
		}
	}

	/* ------------------------------------------------------------- History */

	public static function runs(): array {
		$runs = get_option( self::RUNS, array() );
		return is_array( $runs ) ? $runs : array();
	}

	public static function record( string $id, array $data ): void {
		$runs        = self::runs();
		$runs[ $id ] = array_merge( $runs[ $id ] ?? array( 'id' => $id ), $data );
		uasort( $runs, fn( $a, $b ) => ( $b['started'] ?? 0 ) <=> ( $a['started'] ?? 0 ) );
		$runs = array_slice( $runs, 0, self::MAX_RUNS, true );
		if ( false === get_option( self::RUNS, false ) ) {
			add_option( self::RUNS, $runs, '', false );
		} else {
			update_option( self::RUNS, $runs, false );
		}
	}

	/* ---------------------------------------------------------------- Lock */

	/** Id of a job that is running right now (touched in the last 10 minutes), or ''. */
	public static function running(): string {
		$lock = get_option( self::LOCK );
		if ( ! is_array( $lock ) || ( $lock['time'] ?? 0 ) < time() - 10 * MINUTE_IN_SECONDS ) {
			return '';
		}
		return (string) $lock['id'];
	}

	public static function lock( string $id ): void {
		update_option( self::LOCK, array( 'id' => $id, 'time' => time() ), false );
	}

	public static function unlock( string $id ): void {
		if ( self::running() === $id ) {
			delete_option( self::LOCK );
		}
	}

	/* ----------------------------------------------------------- Auto-sync */

	private static function watched( WP_Post $post ): bool {
		return in_array( $post->post_type, WPTA_Exporter::post_types(), true );
	}

	public static function on_status( string $new, string $old, WP_Post $post ): void {
		if ( ( 'publish' === $new || 'publish' === $old ) && $new !== $old && self::watched( $post ) ) {
			self::schedule();
		}
	}

	public static function on_update( int $id, WP_Post $after, WP_Post $before ): void {
		if ( 'publish' === $after->post_status && 'publish' === $before->post_status && self::watched( $after ) ) {
			self::schedule();
		}
	}

	/** Debounced: one sync a couple of minutes after the last burst of edits. */
	public static function schedule(): void {
		$s = WPTA_Settings::get();
		if ( ! $s['auto_sync'] || '' === $s['github_token'] || wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		wp_schedule_single_event( time() + self::DELAY, self::HOOK );
	}

	public static function auto_run(): void {
		$s = WPTA_Settings::get();
		if ( ! $s['auto_sync'] || '' === $s['github_token'] ) {
			return;
		}
		if ( self::running() ) {
			wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::HOOK );
			return;
		}
		$job = WPTA_Job::create( 'auto' );
		self::cron_step( $job->id() );
	}

	/** Run a background job for ~20 s per cron tick until it is done. */
	public static function cron_step( string $id ): void {
		$job = WPTA_Job::load( $id );
		if ( ! $job ) {
			return;
		}
		$job->run_for( 20 );
		if ( ! $job->done() ) {
			wp_schedule_single_event( time() + 5, self::RUN_HOOK, array( $id ) );
		}
	}
}
