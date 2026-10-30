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
	const CHANGED  = 'wpta_changed';
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

	/**
	 * Tree entries for a commit on top of the current head tree (base_tree): only paths that change. Deleted paths
	 * get a null sha. Everything else, including files someone else added, stays as it is. Pure (unit-tested).
	 *
	 * @param array $plan From plan().
	 * @return array<int, array{path:string, mode:string, type:string, sha:?string}>
	 */
	public static function tree_changes( array $plan ): array {
		$out = array();
		foreach ( $plan['changed'] as $path ) {
			$out[ $path ] = array( 'path' => $path, 'mode' => '100644', 'type' => 'blob', 'sha' => $plan['tree'][ $path ] );
		}
		foreach ( $plan['deleted'] as $path ) {
			$out[ $path ] = array( 'path' => $path, 'mode' => '100644', 'type' => 'blob', 'sha' => null );
		}
		ksort( $out );
		return array_values( $out );
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

	/** A lock nobody has touched for this long belongs to a run that died; the next start takes it over. */
	const STALE = 10 * MINUTE_IN_SECONDS;

	/** Id of a job that is running right now (touched in the last 10 minutes), or ''. */
	public static function running(): string {
		$lock = self::read_lock();
		if ( ! $lock || $lock['time'] < time() - self::STALE ) {
			return '';
		}
		return $lock['id'];
	}

	/**
	 * Take the conversion lock for job $id. Atomic: the lock is a row that only one INSERT can create, so two starts
	 * at the same moment (two tabs, or the button while auto-sync fires) can't both win. A stale lock is taken over.
	 *
	 * @param bool $force Take the lock even from a run that looks alive ("Start anyway").
	 */
	public static function acquire( string $id, bool $force = false ): bool {
		global $wpdb;
		for ( $try = 0; $try < 2; $try++ ) {
			$won = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", self::LOCK, maybe_serialize( array( 'id' => $id, 'time' => time() ) ) ) );
			self::forget();
			if ( 1 === (int) $won ) {
				return true;
			}
			$raw  = self::read_raw();
			$lock = self::read_lock();
			if ( $lock && $lock['id'] === $id ) {
				return true;
			}
			if ( null !== $raw && $lock && $lock['time'] >= time() - self::STALE && ! $force ) {
				return false;
			}
			// Stale, unreadable or forced: delete exactly the row we read, so a lock someone took meanwhile survives.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK, (string) $raw ) );
			self::forget();
		}
		return false;
	}

	/** Whether job $id still holds the lock (another run may have taken over a stale or forced lock). */
	public static function holds( string $id ): bool {
		$lock = self::read_lock();
		return $lock && $lock['id'] === $id;
	}

	/** Refresh the lock's time while job $id works, so it never looks stale. */
	public static function heartbeat( string $id ): void {
		global $wpdb;
		$raw = self::read_raw();
		if ( null === $raw || ! self::holds( $id ) ) {
			return;
		}
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", maybe_serialize( array( 'id' => $id, 'time' => time() ) ), self::LOCK, $raw ) );
		self::forget();
	}

	public static function unlock( string $id ): void {
		global $wpdb;
		$raw = self::read_raw();
		if ( null !== $raw && self::holds( $id ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK, $raw ) );
			self::forget();
		}
	}

	/** The lock row as stored, read past the object cache. */
	private static function read_raw(): ?string {
		global $wpdb;
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) );
		return null === $raw ? null : (string) $raw;
	}

	/** @return array{id:string, time:int}|null */
	private static function read_lock(): ?array {
		$lock = maybe_unserialize( (string) self::read_raw() );
		return is_array( $lock ) && isset( $lock['id'] ) ? array( 'id' => (string) $lock['id'], 'time' => (int) ( $lock['time'] ?? 0 ) ) : null;
	}

	/** The lock is written with SQL, so drop any cached copy get_option() might hold. */
	private static function forget(): void {
		wp_cache_delete( self::LOCK, 'options' );
		$none = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $none ) && isset( $none[ self::LOCK ] ) ) {
			unset( $none[ self::LOCK ] );
			wp_cache_set( 'notoptions', $none, 'options' );
		}
	}

	/* ----------------------------------------------------------- Auto-sync */

	private static function watched( WP_Post $post ): bool {
		return in_array( $post->post_type, WPTA_Exporter::post_types(), true );
	}

	public static function on_status( string $new, string $old, WP_Post $post ): void {
		if ( ( 'publish' === $new || 'publish' === $old ) && $new !== $old && self::watched( $post ) ) {
			self::touch();
			self::schedule();
		}
	}

	public static function on_update( int $id, WP_Post $after, WP_Post $before ): void {
		if ( 'publish' === $after->post_status && 'publish' === $before->post_status && self::watched( $after ) ) {
			self::touch();
			self::schedule();
		}
	}

	/** Remember when published content last changed, so the dashboard can say a sync is due. */
	private static function touch(): void {
		update_option( self::CHANGED, time(), false );
	}

	/** When published content last changed (0 if never seen). */
	public static function changed(): int {
		return (int) get_option( self::CHANGED, 0 );
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
		try {
			$job = WPTA_Job::create( 'auto' );
		} catch ( RuntimeException $e ) {
			// Busy (or the job folder couldn't be made): try again later.
			wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::HOOK );
			return;
		}
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
