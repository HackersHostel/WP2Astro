<?php
defined( 'ABSPATH' ) || exit;

/**
 * Phased conversion job. Each step is short, so it survives max_execution_time whether it is
 * driven by AJAX (Convert button), WP-Cron (auto-sync) or WP-CLI.
 * State: uploads/wp2astro/{job}/state.json, files: …/{job}/site/, AI cache: uploads/wp2astro/cache/
 */
class WPTA_Job {

	const ENTRY_BATCH = 20;
	const MEDIA_BATCH = 100;
	const BLOB_BATCH  = 25;

	/** Exception code of create() when another run holds the lock. */
	const BUSY = 409;

	/** AI cache entries not used for this long are removed; a cache hit refreshes the file's time. */
	const CACHE_TTL = 30 * DAY_IN_SECONDS;

	/** Left as they are in the repo on background syncs, so an AI or hand-tuned layout is never replaced by surprise. */
	const AUTO_KEEP = array( 'src/layouts/BaseLayout.astro' );

	private array $s;
	private array $log = array();

	public static function init(): void {
		add_action( 'wp_ajax_wpta_start', array( __CLASS__, 'ajax_start' ) );
		add_action( 'wp_ajax_wpta_step', array( __CLASS__, 'ajax_step' ) );
		add_action( 'wp_ajax_wpta_runs', array( __CLASS__, 'ajax_runs' ) );
		add_action( 'wp_ajax_wpta_stop', array( __CLASS__, 'ajax_stop' ) );
		add_action( 'wp_ajax_wpta_clear', array( __CLASS__, 'ajax_clear' ) );
		add_action( 'admin_post_wpta_download', array( __CLASS__, 'download' ) );
	}

	/* -------------------------------------------------------------- Paths */

	public static function root(): string {
		return wp_get_upload_dir()['basedir'] . '/wp2astro';
	}

	public static function cache_dir(): string {
		return self::root() . '/cache';
	}

	private function dir(): string {
		return self::root() . '/' . $this->s['id'];
	}

	private function site_dir(): string {
		return $this->dir() . '/site';
	}

	private static function valid_id( string $id ): bool {
		return (bool) preg_match( '/^[a-z0-9]{12}$/', $id );
	}

	public function id(): string {
		return $this->s['id'];
	}

	public function done(): bool {
		return in_array( $this->s['phase'], array( 'done', 'error' ), true );
	}

	/* ------------------------------------------------------------ Create */

	/**
	 * Start a run. Throws a RuntimeException with code BUSY when another run holds the lock.
	 *
	 * @param string $trigger manual | auto | cli
	 * @param array  $opts    ai (bool), github (bool), zip (bool), force (bool: take the lock from a run that looks alive)
	 */
	public static function create( string $trigger, array $opts = array() ): self {
		self::cleanup_old();
		$s    = WPTA_Settings::get();
		$opts = array_merge(
			array(
				'ai'     => true,
				'github' => '' !== $s['github_token'],
				'zip'    => 'auto' !== $trigger,
				'keep'   => 'auto' === $trigger ? self::AUTO_KEEP : array(),
				// Reuse Markdown of unchanged posts. The button rebuilds everything, since shortcodes can show
				// changing content (latest posts…); auto-sync and `wp wp2astro convert --cached` reuse it.
				'cached' => 'auto' === $trigger,
			),
			$opts
		);
		$id = strtolower( wp_generate_password( 12, false ) );
		if ( ! WPTA_Sync::acquire( $id, ! empty( $opts['force'] ) ) ) {
			throw new RuntimeException( 'Another conversion is running right now (job ' . WPTA_Sync::running() . '). Wait for it to finish.', self::BUSY );
		}
		// This run holds the lock, so any run still marked as running died or was taken over.
		foreach ( WPTA_Sync::runs() as $rid => $r ) {
			if ( 'running' === ( $r['status'] ?? '' ) ) {
				WPTA_Sync::record( (string) $rid, array( 'status' => 'error', 'finished' => time(), 'error' => 'Stopped: it didn’t finish (its tab was closed, or a newer run took over). Nothing was pushed.' ) );
			}
		}
		$job = new self( array( 'id' => $id, 'trigger' => $trigger, 'opts' => $opts ) );
		wp_mkdir_p( $job->dir() . '/site' );
		wp_mkdir_p( self::cache_dir() );
		file_put_contents( self::root() . '/index.php', "<?php // Silence.\n" );
		file_put_contents( self::root() . '/.htaccess', "Require all denied\nDeny from all\n" );
		$job->save();

		$llm = $opts['ai'] ? WPTA_LLM::from_settings() : null;
		WPTA_Sync::record(
			$id,
			array(
				'trigger' => $trigger,
				'started' => time(),
				'status'  => 'running',
				'model'   => $llm ? $llm->label() : '',
			)
		);
		return $job;
	}

	/* --------------------------------------------------------------- AJAX */

	private static function guard(): void {
		check_ajax_referer( 'wpta' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}
	}

	public static function ajax_start(): void {
		self::guard();
		try {
			$job = self::create( 'manual', array( 'force' => ! empty( $_POST['force'] ) ) );
		} catch ( RuntimeException $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage(), 'busy' => self::BUSY === $e->getCode() ) );
		}
		wp_send_json_success( array( 'job' => $job->id(), 'log' => array( 'Job ' . $job->id() . ' started.' ) ) );
	}

	public static function ajax_step(): void {
		self::guard();
		$id = sanitize_key( wp_unslash( $_POST['job'] ?? '' ) );
		if ( ! self::valid_id( $id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid job.' ), 400 );
		}
		$job = self::load( $id );
		if ( ! $job ) {
			wp_send_json_error( array( 'message' => 'Job not found. Start again.' ), 404 );
		}
		$stepped           = $job->run_step();
		$status            = $job->status();
		$status['waiting'] = ! $stepped; // Another request is stepping this job; ask again in a moment.
		wp_send_json_success( $status );
	}

	public static function ajax_runs(): void {
		self::guard();
		wp_send_json_success( array( 'runs' => array_values( WPTA_Sync::runs() ) ) );
	}

	/** Stop a job between steps. Nothing is pushed: commits are only made in the last step. */
	public static function ajax_stop(): void {
		self::guard();
		$job = self::load( sanitize_key( wp_unslash( $_POST['job'] ?? '' ) ) );
		if ( ! $job ) {
			wp_send_json_error( array( 'message' => 'Job not found.' ), 404 );
		}
		$job->stop();
		wp_send_json_success( $job->status() );
	}

	/** Delete job folders, stored zips, the AI cache and the run history. Settings and the repo stay. */
	public static function ajax_clear(): void {
		self::guard();
		if ( WPTA_Sync::running() ) {
			wp_send_json_error( array( 'message' => 'A sync is running. Clear the data when it is done.' ) );
		}
		if ( is_dir( self::root() ) ) {
			foreach ( glob( self::root() . '/*', GLOB_ONLYDIR ) as $dir ) {
				self::rrmdir( $dir );
			}
		}
		delete_option( WPTA_Sync::RUNS );
		delete_option( 'wpta_last_layout_log' );
		wp_send_json_success( array( 'message' => 'Cleared. The next sync calls the AI again for the layout.' ) );
	}

	public static function download(): void {
		$id = sanitize_key( wp_unslash( $_GET['job'] ?? '' ) );
		if ( ! current_user_can( 'manage_options' ) || ! self::valid_id( $id ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'wpta_download_' . $id ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$zip = self::root() . '/' . $id . '/site.zip';
		if ( ! is_readable( $zip ) ) {
			wp_die( 'This download has expired. Run the conversion again.', 404 );
		}
		$job = self::load( $id );
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . ( $job ? $job->s['site']['slug'] : 'site' ) . '-astro.zip"' );
		header( 'Content-Length: ' . filesize( $zip ) );
		readfile( $zip ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/* -------------------------------------------------------------- State */

	public function __construct( array $state ) {
		$this->s = array_merge(
			array(
				'phase'   => 'init',
				'trigger' => 'manual',
				'opts'    => array( 'ai' => true, 'github' => false, 'zip' => true, 'keep' => array() ),
				'created' => time(),
				'ids'     => array(),
				'cursor'  => 0,
				'media'   => array(),
				'flagged' => array(),
				'paths'   => array(),
				'redirects' => array(),
				'query'   => array(),
				'media_in' => array(),
				'fixups'  => array(),
				'lfs'     => array(),
				'zipping' => array(),
				'ctx'     => '',
				'report'  => array( 'entries' => 0, 'types' => array(), 'media' => 0, 'media_failed' => array(), 'cleaned' => array(), 'cleanup_failed' => array(), 'layout' => array(), 'unconverted' => array(), 'seo' => '', 'reused' => 0 ),
				'files'   => array(),
				'repo'    => null,
				'remote'  => null,
				'plan'    => null,
				'uploaded' => array(),
				'result'  => array(),
			),
			$state
		);
	}

	public static function load( string $id ): ?self {
		if ( ! self::valid_id( $id ) ) {
			return null;
		}
		$file = self::root() . '/' . $id . '/state.json';
		if ( ! is_readable( $file ) ) {
			return null;
		}
		$state = json_decode( (string) file_get_contents( $file ), true );
		return is_array( $state ) ? new self( $state ) : null;
	}

	private function save(): void {
		file_put_contents( $this->dir() . '/state.json', wp_json_encode( $this->s ) );
	}

	private function say( string $line ): void {
		$this->log[] = $line;
	}

	public function take_log(): array {
		$l         = $this->log;
		$this->log = array();
		return $l;
	}

	public function status(): array {
		return array(
			'phase'    => $this->s['phase'],
			'done'     => $this->done(),
			'error'    => 'error' === $this->s['phase'],
			'progress' => $this->progress(),
			'log'      => $this->take_log(),
			'result'   => $this->s['result'],
		);
	}

	private function progress(): int {
		$order = array( 'init' => 0, 'content' => 5, 'media' => 45, 'ai_layout' => 60, 'ai_cleanup' => 70, 'package' => 80, 'zip' => 82, 'github' => 86, 'github_upload' => 88, 'github_commit' => 98, 'done' => 100, 'error' => 100 );
		$p     = $order[ $this->s['phase'] ] ?? 0;
		if ( 'content' === $this->s['phase'] && $this->s['ids'] ) {
			$p += (int) ( 40 * $this->s['cursor'] / count( $this->s['ids'] ) );
		}
		if ( 'media' === $this->s['phase'] && $this->s['media'] ) {
			$p += (int) ( 15 * $this->s['cursor'] / count( $this->s['media'] ) );
		}
		if ( 'zip' === $this->s['phase'] && $this->s['zipping'] ) {
			$p += (int) ( 4 * $this->s['cursor'] / count( $this->s['zipping'] ) );
		}
		if ( 'github_upload' === $this->s['phase'] && ! empty( $this->s['plan']['upload'] ) ) {
			$p += (int) ( 10 * count( $this->s['uploaded'] ) / count( $this->s['plan']['upload'] ) );
		}
		return min( 100, $p );
	}

	/** Write a project file and remember its git blob id, so packaging doesn't read every file again. */
	private function write( string $rel, string $contents ): void {
		$path = $this->site_dir() . '/' . $rel;
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, $contents );
		$this->s['files'][ $rel ] = WPTA_GitHub::blob_sha( $contents );
	}

	/* ------------------------------------------------------------- Running */

	/**
	 * One step, with errors turned into an error phase. Saves state.
	 * Only one request steps a job at a time (two tabs, or a tab and cron): the others return false and change nothing.
	 */
	public function run_step(): bool {
		if ( $this->done() ) {
			return true;
		}
		$guard = $this->step_lock( false );
		if ( ! $guard ) {
			return false;
		}
		try {
			$this->reload(); // Another request may have stepped this job since it was loaded.
			if ( $this->done() ) {
				return true;
			}
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
			if ( ! WPTA_Sync::holds( $this->id() ) ) {
				// A newer run took the lock ("Start anyway", or this one sat idle for 10 minutes). Don't race it.
				$this->s['phase']           = 'error';
				$this->s['result']['error'] = 'Stopped: a newer run took over. Nothing was pushed.';
				$this->say( 'Stopped: a newer run took over.' );
			} else {
				WPTA_Sync::heartbeat( $this->id() );
				try {
					$this->step();
				} catch ( Throwable $e ) {
					$this->s['phase'] = 'error';
					$this->say( 'Error: ' . $e->getMessage() );
					$this->s['result']['error'] = $e->getMessage();
				}
			}
			if ( $this->done() ) {
				$this->finish();
			}
			$this->save();
		} finally {
			flock( $guard, LOCK_UN );
			fclose( $guard );
		}
		return true;
	}

	/** An exclusive lock on this job's folder. $wait: block until it is free (stop), or give up at once (steps). */
	private function step_lock( bool $wait ) {
		$fh = fopen( $this->dir() . '/step.lock', 'c' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			throw new RuntimeException( 'Could not open the job folder.' );
		}
		if ( ! flock( $fh, $wait ? LOCK_EX : LOCK_EX | LOCK_NB ) ) {
			fclose( $fh );
			return false;
		}
		return $fh;
	}

	private function reload(): void {
		$file  = $this->dir() . '/state.json';
		$state = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
		if ( is_array( $state ) ) {
			$this->s = ( new self( $state ) )->s;
		}
	}

	public function stop(): void {
		$guard = $this->step_lock( true );
		try {
			$this->reload();
			if ( $this->done() ) {
				return;
			}
			$this->s['phase']           = 'error';
			$this->s['result']['error'] = 'Stopped. Nothing was pushed.';
			$this->say( 'Stopped by ' . wp_get_current_user()->user_login . '.' );
			$this->finish();
			$this->save();
		} finally {
			flock( $guard, LOCK_UN );
			fclose( $guard );
		}
	}

	/** Run steps for up to $seconds (cron and CLI). */
	public function run_for( int $seconds, ?callable $on_log = null ): void {
		$until = microtime( true ) + $seconds;
		do {
			if ( ! $this->run_step() ) {
				sleep( 1 ); // Another request holds this job's step lock.
			}
			if ( $on_log ) {
				foreach ( $this->take_log() as $line ) {
					$on_log( $line, $this->progress() );
				}
			}
		} while ( ! $this->done() && microtime( true ) < $until );
	}

	private function finish(): void {
		$r = $this->s['result'];
		WPTA_Sync::record(
			$this->id(),
			array(
				'finished'  => time(),
				'status'    => 'error' === $this->s['phase'] ? 'error' : 'done',
				'entries'   => $this->s['report']['entries'],
				'files'     => count( $this->s['files'] ),
				'redirects' => (int) ( $this->s['report']['redirects'] ?? 0 ),
				'media'     => (int) $this->s['report']['media'],
				'seo'       => (string) $this->s['report']['seo'],
				'flagged'   => count( $this->s['flagged'] ),
				'cleaned'   => count( $this->s['report']['cleaned'] ),
				'changed'   => $r['changed'] ?? null,
				'deleted'   => $r['deleted'] ?? null,
				'kept'      => $r['kept'] ?? array(),
				'repo'      => $r['repo'] ?? '',
				'commit'    => $r['commit'] ?? '',
				'zip'       => $r['zip'] ?? '',
				'error'     => $r['error'] ?? '',
			)
		);
		WPTA_Sync::unlock( $this->id() );
	}

	/* ------------------------------------------------------------- Phases */

	private function step(): void {
		switch ( $this->s['phase'] ) {
			case 'init':
				$this->phase_init();
				break;
			case 'content':
				$this->phase_content();
				break;
			case 'media':
				$this->phase_media();
				break;
			case 'ai_layout':
				$this->phase_ai_layout();
				break;
			case 'ai_cleanup':
				$this->phase_ai_cleanup();
				break;
			case 'package':
				$this->phase_package();
				break;
			case 'zip':
				$this->phase_zip();
				break;
			case 'github':
				$this->phase_github();
				break;
			case 'github_upload':
				$this->phase_github_upload();
				break;
			case 'github_commit':
				$this->phase_github_commit();
				break;
		}
	}

	private function llm(): ?WPTA_LLM {
		return $this->s['opts']['ai'] ? WPTA_LLM::from_settings() : null;
	}

	private function phase_init(): void {
		$home = WPTA_Exporter::fetch_home();
		file_put_contents( $this->dir() . '/home.html', $home );
		$this->say( $home ? 'Fetched the rendered homepage.' : 'Could not fetch the homepage over HTTP (loopback); continuing without it.' );

		$sheets  = WPTA_Exporter::copy_theme( $this->site_dir(), $home );
		$scripts = WPTA_Exporter::theme_scripts( $this->site_dir(), $home );
		$this->say( 'Copied theme assets (' . wp_get_theme()->get( 'Name' ) . '), ' . count( $sheets ) . ' stylesheet(s)' . ( $scripts ? ', ' . count( $scripts ) . ' script(s)' : '' ) . '.' );

		$site            = WPTA_Exporter::site_data( $sheets, $scripts );
		$this->s['site'] = $site;
		$this->write( 'src/site.json', wp_json_encode( $site, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
		foreach ( WPTA_Scaffold::files( $site ) as $rel => $contents ) {
			$this->write( $rel, $contents );
		}
		$this->say( 'Wrote the Astro project scaffold.' );

		$seo = WPTA_Exporter::seo_plugin();
		if ( $seo && $site['features']['seo'] ) {
			$this->s['report']['seo'] = $seo;
			$this->say( 'Reading titles and descriptions from ' . $seo . '.' );
		}

		$this->s['media_mode'] = WPTA_Media::mode();
		if ( 'cdn' === $this->s['media_mode'] ) {
			$missing = WPTA_Storage::missing( WPTA_Storage::config() );
			if ( $missing ) {
				throw new RuntimeException( $missing . ' Fill it in under Settings → Media, or pick another option there.' );
			}
		}
		$this->s['ctx']   = self::cache_context( $site );
		$this->s['ids']   = array_map( 'intval', WPTA_Exporter::entry_ids() );
		$this->s['phase'] = 'content';
		$types            = array_diff( WPTA_Exporter::post_types(), array( 'post', 'page' ) );
		$this->say( 'Found ' . count( $this->s['ids'] ) . ' published entries' . ( $types ? ' (including custom types: ' . implode( ', ', $types ) . ')' : '' ) . '.' );
	}

	private function phase_content(): void {
		$batch = array_slice( $this->s['ids'], $this->s['cursor'], self::ENTRY_BATCH );
		$media = array_flip( $this->s['media'] );
		foreach ( $batch as $id ) {
			$e    = $this->entry( (int) $id );
			$path = $e['path'];
			if ( isset( $this->s['paths'][ $path ] ) ) {
				$path = preg_replace( '/\.md$/', '-' . $id . '.md', $path );
			}
			$this->s['paths'][ $path ] = $e['permalink'];
			// ?p=123 and ?page_id=7 links, collected here so the last step doesn't load every post again.
			$this->s['query'][ ( 'page' === $e['type'] ? 'page_id' : 'p' ) . '=' . $id ] = $e['permalink'];
			$this->write( $path, $e['markdown'] );
			foreach ( $e['media'] as $m ) {
				$media[ $m ]                    = true;
				$this->s['media_in'][ $m ][] = $path;
			}
			foreach ( $e['redirects'] as $from => $to ) {
				$this->s['redirects'][ $from ] = $to;
			}
			foreach ( $e['flags'] as $flag ) {
				if ( 0 === strpos( $flag, 'shortcodes:' ) ) {
					$this->s['flagged'][] = array( 'path' => $path, 'title' => $e['title'], 'shortcodes' => explode( ',', substr( $flag, 11 ) ) );
				}
			}
			$this->s['report']['types'][ $e['type'] ] = ( $this->s['report']['types'][ $e['type'] ] ?? 0 ) + 1;
			++$this->s['report']['entries'];
		}
		$this->s['cursor'] += count( $batch );
		$this->s['media']   = array_keys( $media );
		$this->say( sprintf( 'Converted %d / %d entries to Markdown.', $this->s['cursor'], count( $this->s['ids'] ) ) );

		if ( $this->s['cursor'] >= count( $this->s['ids'] ) ) {
			if ( $this->s['report']['reused'] ) {
				$this->say( sprintf( 'Reused %d unchanged entries from the cache.', $this->s['report']['reused'] ) );
			}
			$redirects = WPTA_Exporter::redirects( $this->s['redirects'], array_values( $this->s['paths'] ), $this->s['site'], $this->s['query'] );
			$this->write( 'redirects.json', wp_json_encode( $redirects, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
			$this->write( 'public/_redirects', WPTA_Exporter::redirects_file( $redirects ) );
			$this->write( 'netlify.toml', WPTA_Exporter::netlify_toml( $redirects ) );
			$this->s['report']['redirects'] = count( $redirects['paths'] ) + count( $redirects['query'] );
			$this->s['cursor']              = 0;
			$this->s['phase']               = 'media';
			if ( $this->s['flagged'] ) {
				$n = count( $this->s['flagged'] );
				$this->say( $n . ( 1 === $n ? ' entry contains' : ' entries contain' ) . ' dead shortcodes.' );
			}
		}
	}

	/**
	 * One entry as Markdown. With opts.cached, an entry whose post and output settings haven't changed comes from
	 * uploads/wp2astro/cache/entries/ instead of running the_content again.
	 */
	private function entry( int $id ): array {
		$file = self::cache_dir() . '/entries/' . $id . '.json';
		$key  = md5( get_post_field( 'post_modified_gmt', $id ) . '|' . $this->s['ctx'] );
		if ( ! empty( $this->s['opts']['cached'] ) && is_readable( $file ) ) {
			$hit = json_decode( (string) file_get_contents( $file ), true );
			if ( is_array( $hit ) && ( $hit['key'] ?? '' ) === $key && isset( $hit['entry']['markdown'], $hit['entry']['path'] ) ) {
				touch( $file ); // Keeps it out of the 30-day cleanup.
				++$this->s['report']['reused'];
				return $hit['entry'];
			}
		}
		$e = WPTA_Exporter::entry( $id );
		wp_mkdir_p( dirname( $file ) );
		file_put_contents( $file, wp_json_encode( array( 'key' => $key, 'entry' => $e ) ) );
		return $e;
	}

	/** Everything besides the post itself that changes an entry's Markdown. A change rebuilds every entry. */
	private static function cache_context( array $site ): string {
		$s = WPTA_Settings::get();
		return md5(
			(string) wp_json_encode(
				array(
					WPTA_VERSION,
					array( $s['seo'], $s['cpts'], $s['fields'] ),
					WPTA_Media::mode( $s ),
					WPTA_Media::base( WPTA_Media::mode( $s ) ),
					get_option( 'permalink_structure' ),
					home_url(),
					wp_get_upload_dir()['baseurl'],
					get_stylesheet(),
					get_template(),
					get_option( 'active_plugins' ),
					$site['categories'],
					$site['tags'],
					$site['authors'],
				)
			)
		);
	}

	/**
	 * Media, by mode: copied into the project (repo, lfs), uploaded to object storage (cdn), or left on WordPress.
	 * Files that can't be copied or uploaded keep their WordPress URL and are listed in the report.
	 */
	private function phase_media(): void {
		$mode = $this->s['media_mode'] ?? 'repo';
		if ( 'wordpress' === $mode || ! $this->s['media'] ) {
			$this->s['report']['media'] = 'wordpress' === $mode ? count( $this->s['media'] ) : 0;
			if ( 'wordpress' === $mode && $this->s['media'] ) {
				$this->say( 'Media stays on WordPress: ' . count( $this->s['media'] ) . ' files are linked from ' . WPTA_Media::wordpress_base() . '.' );
			}
			$this->s['phase'] = 'ai_layout';
			return;
		}
		if ( 'lfs' === $mode && 0 === $this->s['cursor'] ) {
			$this->write( '.gitattributes', WPTA_Media::LFS_ATTRIBUTES );
		}
		$max    = WPTA_Media::max_bytes( $mode );
		$cdn    = 'cdn' === $mode ? WPTA_Storage::config() : null;
		$sent   = $cdn ? (array) get_option( 'wpta_cdn_files', array() ) : array();
		$target = $cdn ? WPTA_Storage::target( $cdn ) : '';
		$until  = microtime( true ) + 20;
		$batch  = array_slice( $this->s['media'], $this->s['cursor'], self::MEDIA_BATCH );
		$done   = 0;
		foreach ( $batch as $rel ) {
			++$done;
			$src = WPTA_Exporter::media_file( $rel );
			if ( '' === $src ) {
				$this->s['report']['media_failed'][] = $rel . ' — missing';
			} elseif ( filesize( $src ) > $max ) {
				$this->s['report']['media_failed'][] = $rel . ' — larger than ' . size_format( $max ) . ', linked from WordPress';
				$this->s['fixups'][]                  = $rel;
			} elseif ( $cdn ) {
				$sha = WPTA_GitHub::blob_sha_file( $src );
				if ( ( $sent[ $target ][ $rel ] ?? '' ) !== $sha ) {
					$ok = WPTA_Storage::put( $cdn, ltrim( $rel, '/' ), $src );
					if ( is_wp_error( $ok ) ) {
						$this->s['report']['media_failed'][] = $rel . ' — upload failed (' . $ok->get_error_message() . '), linked from WordPress';
						$this->s['fixups'][]                  = $rel;
						continue;
					}
					$sent[ $target ][ $rel ] = $sha;
				}
				++$this->s['report']['media'];
			} else {
				$dest = $this->site_dir() . '/public' . $rel;
				wp_mkdir_p( dirname( $dest ) );
				if ( ! copy( $src, $dest ) ) {
					$this->s['report']['media_failed'][] = $rel . ' — copy failed';
					continue;
				}
				if ( 'lfs' === $mode ) {
					// The repo gets a small pointer file; the bytes go to GitHub's LFS storage on upload.
					$oid = (string) hash_file( 'sha256', $dest );
					$this->s['lfs'][ 'public' . $rel ]   = array( 'oid' => $oid, 'size' => (int) filesize( $dest ) );
					$this->s['files'][ 'public' . $rel ] = WPTA_GitHub::blob_sha( WPTA_Media::lfs_pointer( $oid, (int) filesize( $dest ) ) );
				} else {
					$this->s['files'][ 'public' . $rel ] = WPTA_GitHub::blob_sha_file( $dest );
				}
				++$this->s['report']['media'];
			}
			if ( microtime( true ) > $until ) {
				break;
			}
		}
		if ( $cdn ) {
			update_option( 'wpta_cdn_files', $sent, false );
		}
		$this->s['cursor'] += $done;
		$this->say( sprintf( '%s media %d / %d.', $cdn ? 'Uploaded' : 'Copied', $this->s['cursor'], count( $this->s['media'] ) ) );
		if ( $this->s['cursor'] >= count( $this->s['media'] ) ) {
			$this->relink_failed_media( $mode );
			$this->s['cursor'] = 0;
			$this->s['phase']  = 'ai_layout';
		}
	}

	/** Point links to media that wasn't copied or uploaded at the file on WordPress, so the page still shows it. */
	private function relink_failed_media( string $mode ): void {
		$wp   = WPTA_Media::wordpress_base();
		$rel  = WPTA_Media::rel_base();
		$base = WPTA_Media::base( $mode );
		foreach ( array_unique( $this->s['fixups'] ) as $m ) {
			$from = ( '' === $base ? '' : $base ) . ( '' === $base ? $m : substr( $m, strlen( $rel ) ) );
			$to   = $wp . substr( $m, strlen( $rel ) );
			foreach ( array_unique( $this->s['media_in'][ $m ] ?? array() ) as $path ) {
				$file = $this->site_dir() . '/' . $path;
				if ( is_readable( $file ) ) {
					$this->write( $path, WPTA_Media::relink( (string) file_get_contents( $file ), $from, $to ) );
				}
			}
		}
		if ( $this->s['fixups'] ) {
			$this->say( count( array_unique( $this->s['fixups'] ) ) . ' media file(s) stay on WordPress; see CONVERSION_REPORT.md.' );
		}
	}

	private function phase_ai_layout(): void {
		$settings         = WPTA_Settings::get();
		$llm              = $this->llm();
		$this->s['phase'] = 'ai_cleanup';

		if ( 'auto' === $this->s['trigger'] ) {
			$code = WPTA_AI::latest_layout();
			if ( $code ) {
				$this->write( 'src/layouts/BaseLayout.astro', $code );
			}
			// Same report lines as the last full run, so the report doesn't change (and commit) on every background sync.
			$this->s['report']['layout'] = (array) get_option( 'wpta_last_layout_log', array( 'Layout left as it is in the repo.' ) );
			return;
		}
		if ( ! $llm || ! $settings['ai_layout'] ) {
			$this->s['report']['layout'] = array( 'AI layout off; default layout used.' );
			update_option( 'wpta_last_layout_log', $this->s['report']['layout'], false );
			$this->say( 'AI layout skipped; using the default layout.' );
			return;
		}
		$this->say( 'Asking ' . $llm->label() . ' to rebuild the theme layout…' );
		$home = is_readable( $this->dir() . '/home.html' ) ? (string) file_get_contents( $this->dir() . '/home.html' ) : '';
		$t    = microtime( true );
		$res  = WPTA_AI::layout( $llm, $home, $this->s['site'], WPTA_AI::theme_source() );
		foreach ( $res['log'] as $line ) {
			$this->say( $line );
		}
		if ( empty( $res['cached'] ) ) {
			$this->say( sprintf( 'Layout step took %.0f s.', microtime( true ) - $t ) );
		}
		$this->s['report']['layout'] = $res['report'] ?? $res['log'];
		update_option( 'wpta_last_layout_log', $this->s['report']['layout'], false );
		if ( $res['code'] ) {
			$this->write( 'src/layouts/BaseLayout.astro', $res['code'] );
		}
	}

	private function phase_ai_cleanup(): void {
		$settings = WPTA_Settings::get();
		$llm      = $this->llm();
		if ( ! $llm || ! $settings['ai_cleanup'] || $this->s['cursor'] >= count( $this->s['flagged'] ) ) {
			if ( ( ! $llm || ! $settings['ai_cleanup'] ) && $this->s['flagged'] ) {
				foreach ( $this->s['flagged'] as $f ) {
					$cached = WPTA_AI::cached_cleanup( (string) file_get_contents( $this->site_dir() . '/' . $f['path'] ) );
					if ( $cached ) {
						$this->write( $f['path'], $cached );
						$this->s['report']['cleaned'][] = $f['path'];
					} else {
						$this->s['report']['unconverted'][] = $f;
					}
				}
				if ( $this->s['report']['unconverted'] ) {
					$this->say( 'AI cleanup off; dead shortcodes listed in CONVERSION_REPORT.md.' );
				}
			}
			$this->s['cursor'] = 0;
			$this->s['phase']  = 'package';
			return;
		}
		$f    = $this->s['flagged'][ $this->s['cursor'] ];
		$file = $this->site_dir() . '/' . $f['path'];
		$out  = WPTA_AI::cleanup( $llm, (string) file_get_contents( $file ), $f['shortcodes'] );
		if ( is_wp_error( $out ) ) {
			$this->s['report']['cleanup_failed'][] = $f['path'] . ' — ' . $out->get_error_message();
			$this->s['report']['unconverted'][]    = $f;
			$this->say( 'Cleanup failed for "' . $f['title'] . '": ' . $out->get_error_message() );
		} else {
			$this->write( $f['path'], $out['doc'] );
			$this->s['report']['cleaned'][] = $f['path'];
			$this->say( ( $out['cached'] ? 'Reused earlier cleanup for "' : 'Cleaned shortcodes in "' ) . $f['title'] . '".' );
		}
		++$this->s['cursor'];
	}

	private function phase_package(): void {
		$this->write( 'CONVERSION_REPORT.md', $this->report() );
		if ( $this->s['site']['features']['agentKit'] ) {
			foreach ( WPTA_Agent_Kit::files( $this->s['site'], $this->s['report'] ) as $rel => $contents ) {
				$this->write( $rel, $contents );
			}
			$this->say( 'Added the agent kit (AGENTS.md, CLAUDE.md, Claude Code skills).' );
		}

		// Files written or copied by this run already have their blob ids; only list the folder and hash the rest
		// (theme assets), so this step stays short on sites with a lot of media.
		$files = array();
		$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->site_dir(), FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( $file->isFile() ) {
				$rel           = ltrim( str_replace( '\\', '/', substr( $file->getPathname(), strlen( $this->site_dir() ) ) ), '/' );
				$files[ $rel ] = $this->s['files'][ $rel ] ?? WPTA_GitHub::blob_sha_file( $file->getPathname() );
			}
		}
		ksort( $files, SORT_STRING );
		$this->s['files'] = $files;

		$this->s['cursor'] = 0;
		if ( $this->s['opts']['zip'] ) {
			$this->s['zipping'] = array_keys( $files );
			$this->s['phase']   = 'zip';
			return;
		}
		$this->after_package();
	}

	private function after_package(): void {
		$this->s['cursor'] = 0;
		$this->s['phase']  = $this->s['opts']['github'] && WPTA_Settings::github_connected() ? 'github' : 'done';
		if ( 'done' === $this->s['phase'] ) {
			$this->say( 'Done.' );
		}
	}

	/** The zip, a few hundred files or ~20 seconds at a time. ZipArchive appends to the archive between steps. */
	private function phase_zip(): void {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->say( 'ZipArchive is not available on this server; skipping the zip.' );
			$this->after_package();
			return;
		}
		$path  = $this->dir() . '/site.zip';
		$zip   = new ZipArchive();
		$flags = 0 === $this->s['cursor'] ? ZipArchive::CREATE | ZipArchive::OVERWRITE : ZipArchive::CREATE;
		if ( true !== $zip->open( $path, $flags ) ) {
			throw new RuntimeException( 'Could not create the zip.' );
		}
		$prefix = $this->s['site']['slug'] . '-astro/';
		$until  = microtime( true ) + 15;
		$bytes  = 0;
		$total  = count( $this->s['zipping'] );
		while ( $this->s['cursor'] < $total ) {
			$rel  = $this->s['zipping'][ $this->s['cursor'] ];
			$file = $this->site_dir() . '/' . $rel;
			$zip->addFile( $file, $prefix . $rel );
			if ( preg_match( '/\.(jpe?g|png|gif|webp|avif|mp4|webm|mp3|m4a|zip|gz|woff2?|pdf)$/i', $rel ) ) {
				$zip->setCompressionName( $prefix . $rel, ZipArchive::CM_STORE ); // Already compressed.
			}
			++$this->s['cursor'];
			$bytes += (int) filesize( $file );
			if ( $bytes > 150 * MB_IN_BYTES || microtime( true ) > $until ) {
				break;
			}
		}
		$zip->close();
		if ( $this->s['cursor'] < $total ) {
			$this->say( sprintf( 'Zipped %d / %d files.', $this->s['cursor'], $total ) );
			return;
		}
		$this->s['result']['zip'] = add_query_arg(
			array(
				'action'   => 'wpta_download',
				'job'      => $this->s['id'],
				'_wpnonce' => wp_create_nonce( 'wpta_download_' . $this->s['id'] ),
			),
			admin_url( 'admin-post.php' )
		);
		$this->say( 'Packaged ' . $total . ' files into a zip.' );
		$this->s['zipping'] = array();
		$this->after_package();
	}

	private function github(): WPTA_GitHub {
		return new WPTA_GitHub( WPTA_Settings::get()['github_token'] );
	}

	private function repo_key(): string {
		return $this->s['repo']['owner'] . '/' . $this->s['repo']['repo'];
	}

	/** Find or create the repo, read what is on it, and plan the smallest commit. */
	private function phase_github(): void {
		$settings = WPTA_Settings::get();
		$gh       = $this->github();

		if ( ! $this->s['repo'] ) {
			$brand = WPTA_Settings::brand()['name'];
			$repo  = $gh->ensure_repo( $settings['repo_name'], (bool) $settings['repo_private'], 'Astro version of ' . $this->s['site']['title'] . ', converted from WordPress with ' . $brand . '.' );
			if ( is_wp_error( $repo ) ) {
				throw new RuntimeException( $repo->get_error_message() );
			}
			$this->s['repo'] = $repo;
			$this->say( 'Using GitHub repo ' . $repo['owner'] . '/' . $repo['repo'] . '.' );
			return;
		}

		$remote = $gh->remote_files( $this->s['repo'] );
		if ( is_wp_error( $remote ) ) {
			throw new RuntimeException( $remote->get_error_message() );
		}
		if ( empty( $remote['complete'] ) ) {
			// The commit replaces the whole tree, so files missing from a truncated listing would be deleted.
			throw new RuntimeException( 'GitHub returned only part of the file list for this repo (it has too many files), so a sync could delete files. Sync stopped; nothing was changed.' );
		}
		$manifest = WPTA_Sync::manifest( $this->repo_key() );
		$plan     = WPTA_Sync::plan( $this->s['files'], $remote['files'], $manifest, (array) $this->s['opts']['keep'] );

		$this->s['remote'] = array( 'head' => $remote['head'], 'tree' => $remote['tree'] ?? null );
		$this->s['plan']   = $plan;
		$this->s['result']['repo']    = $this->s['repo']['url'];
		$this->s['result']['changed'] = count( $plan['changed'] );
		$this->s['result']['deleted'] = count( $plan['deleted'] );
		$this->s['result']['kept']    = $plan['kept'];

		$edited = array_diff( $plan['kept'], (array) $this->s['opts']['keep'] );
		if ( $edited ) {
			$this->say( 'Kept ' . count( $edited ) . ' file(s) edited in the repo: ' . implode( ', ', array_slice( $edited, 0, 5 ) ) . ( count( $edited ) > 5 ? '…' : '' ) );
		}
		if ( $plan['noop'] ) {
			$this->s['phase'] = 'done';
			$this->say( 'GitHub is already up to date; nothing to push.' );
			$this->say( 'Done.' );
			return;
		}
		$this->say( sprintf( '%d file(s) changed, %d removed; uploading %d new file(s).', count( $plan['changed'] ), count( $plan['deleted'] ), count( $plan['upload'] ) ) );
		$this->s['phase'] = 'github_upload';
	}

	private function phase_github_upload(): void {
		$todo = array_values( array_diff( $this->s['plan']['upload'], $this->s['uploaded'] ) );
		$gh   = $this->github();

		// Up to BLOB_BATCH files or ~40 MB per step, so one step never uploads a whole media library.
		$slice = array();
		$bytes = 0;
		foreach ( array_slice( $todo, 0, self::BLOB_BATCH ) as $rel ) {
			$size = (int) filesize( $this->site_dir() . '/' . $rel );
			if ( $slice && $bytes + $size > 40 * MB_IN_BYTES ) {
				break;
			}
			$slice[] = $rel;
			$bytes  += $size;
		}
		$objects = array();
		foreach ( $slice as $rel ) {
			if ( isset( $this->s['lfs'][ $rel ] ) ) {
				$objects[] = $this->s['lfs'][ $rel ] + array( 'file' => $this->site_dir() . '/' . $rel );
			}
		}
		$stored = $gh->lfs_upload( $this->s['repo'], $objects );
		if ( is_wp_error( $stored ) ) {
			throw new RuntimeException( $stored->get_error_message() );
		}
		foreach ( $slice as $rel ) {
			$lfs = $this->s['lfs'][ $rel ] ?? null;
			$sha = $lfs
				? $gh->blob( $this->s['repo'], WPTA_Media::lfs_pointer( $lfs['oid'], $lfs['size'] ) )
				: $gh->blob_file( $this->s['repo'], $this->site_dir() . '/' . $rel );
			if ( is_wp_error( $sha ) ) {
				throw new RuntimeException( $rel . ': ' . $sha->get_error_message() );
			}
			if ( $sha !== $this->s['files'][ $rel ] ) {
				throw new RuntimeException( $rel . ': GitHub stored different bytes than expected.' );
			}
			$this->s['uploaded'][] = $rel;
		}
		$this->say( sprintf( 'Uploaded %d / %d files to GitHub.', count( $this->s['uploaded'] ), count( $this->s['plan']['upload'] ) ) );
		if ( count( $this->s['uploaded'] ) >= count( $this->s['plan']['upload'] ) ) {
			$this->s['phase'] = 'github_commit';
		}
	}

	private function phase_github_commit(): void {
		$plan    = $this->s['plan'];
		$first   = ! WPTA_Sync::manifest( $this->repo_key() );
		$message = $first
			? 'Convert WordPress site to Astro'
			: sprintf( 'Sync from WordPress: %d changed, %d removed', count( $plan['changed'] ), count( $plan['deleted'] ) );
		$message .= "\n\nSource: " . home_url( '/' ) . "\nTrigger: " . $this->s['trigger'] . "\nGenerated by " . WPTA_Settings::brand()['name'] . ' ' . WPTA_VERSION;

		$commit = $this->github()->commit( $this->s['repo'], WPTA_Sync::tree_changes( $plan ), $message, $this->s['remote']['head'], $this->s['remote']['tree'] ?? null );
		if ( is_wp_error( $commit ) ) {
			throw new RuntimeException( $commit->get_error_message() );
		}
		WPTA_Sync::save_manifest( $this->repo_key(), WPTA_Sync::next_manifest( $this->s['files'], $plan, WPTA_Sync::manifest( $this->repo_key() ) ), $commit['sha'] );
		$this->s['result']['commit'] = $commit['url'];
		$this->s['phase']            = 'done';
		$this->say( 'Committed to ' . $this->s['repo']['url'] );
		$this->say( 'Done.' );
	}

	/** No timestamps in here: an unchanged site must produce an unchanged report, or every sync would commit. */
	private function report(): string {
		$r    = $this->s['report'];
		$llm  = $this->llm();
		$md   = "# Conversion report\n\n";
		$md  .= '- Source: ' . home_url( '/' ) . "\n";
		$md  .= '- Model: ' . ( $llm ? $llm->label() : 'none' ) . "\n";
		$md  .= '- Entries: ' . $r['entries'];
		if ( $r['types'] ) {
			$md .= ' (' . implode( ', ', array_map( fn( $t, $n ) => "$n $t", array_keys( $r['types'] ), $r['types'] ) ) . ')';
		}
		$md .= "\n- " . WPTA_Media::report_line( $this->s['media_mode'] ?? 'repo', (int) $r['media'] ) . "\n";
		$md .= '- Redirects kept from WordPress: ' . (int) ( $r['redirects'] ?? 0 ) . "\n";
		if ( $r['seo'] ) {
			$md .= '- SEO titles and descriptions from: ' . $r['seo'] . "\n";
		}
		$md .= "\n";

		$md .= "## Layout\n\n" . ( $r['layout'] ? '- ' . implode( "\n- ", $r['layout'] ) : '- Default layout.' ) . "\n\n";

		if ( $r['cleaned'] ) {
			$md .= "## Shortcodes cleaned by AI (please review)\n\n- " . implode( "\n- ", $r['cleaned'] ) . "\n\n";
		}
		if ( $r['unconverted'] ) {
			$md .= "## Dead shortcodes left in content\n\n";
			foreach ( $r['unconverted'] as $f ) {
				$md .= '- `' . $f['path'] . '` (' . $f['title'] . '): ' . implode( ', ', array_map( fn( $s ) => '[' . $s . ']', $f['shortcodes'] ) ) . "\n";
			}
			$md .= "\n";
		}
		if ( $r['cleanup_failed'] ) {
			$md .= "## AI cleanup failures\n\n- " . implode( "\n- ", $r['cleanup_failed'] ) . "\n\n";
		}
		if ( $r['media_failed'] ) {
			$md .= "## Media not copied\n\n- " . implode( "\n- ", $r['media_failed'] ) . "\n\n";
		}
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			$md .= "## Plain permalinks\n\nWordPress uses plain links (`?p=123`), which a static site can't serve. Entries now live at `/{slug}/`, and `?p=` / `?page_id=` links redirect there when you use `npm start`.\n\n";
		}
		$md .= "## Not converted (by design)\n\n- Comments, forms and other features that need a server.\n- Plugin-generated pages and sidebar widgets.\n";
		return $md;
	}

	/* ------------------------------------------------------------ Cleanup */

	private static function cleanup_old(): void {
		if ( ! is_dir( self::root() ) ) {
			return;
		}
		foreach ( glob( self::root() . '/*', GLOB_ONLYDIR ) as $dir ) {
			$state = $dir . '/state.json';
			if ( is_file( $state ) && filemtime( $state ) < time() - DAY_IN_SECONDS ) {
				self::rrmdir( $dir );
			}
		}
		// The latest layout is what background syncs use, so it stays however old it is.
		foreach ( array_merge( (array) glob( self::cache_dir() . '/*' ), (array) glob( self::cache_dir() . '/entries/*' ) ) as $file ) {
			if ( is_file( $file ) && 'layout-latest.astro' !== basename( $file ) && filemtime( $file ) < time() - self::CACHE_TTL ) {
				unlink( $file );
			}
		}
	}

	private static function rrmdir( string $dir ): void {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
		}
		rmdir( $dir );
	}
}
