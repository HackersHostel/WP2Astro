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

	/** Left as they are in the repo on background syncs, so an AI or hand-tuned layout is never replaced by surprise. */
	const AUTO_KEEP = array( 'src/layouts/BaseLayout.astro' );

	private array $s;
	private array $log = array();

	public static function init(): void {
		add_action( 'wp_ajax_wpta_start', array( __CLASS__, 'ajax_start' ) );
		add_action( 'wp_ajax_wpta_step', array( __CLASS__, 'ajax_step' ) );
		add_action( 'wp_ajax_wpta_runs', array( __CLASS__, 'ajax_runs' ) );
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
	 * @param string $trigger manual | auto | cli
	 * @param array  $opts    ai (bool), github (bool), zip (bool)
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
			),
			$opts
		);
		$id  = strtolower( wp_generate_password( 12, false ) );
		$job = new self( array( 'id' => $id, 'trigger' => $trigger, 'opts' => $opts ) );
		wp_mkdir_p( $job->dir() . '/site' );
		wp_mkdir_p( self::cache_dir() );
		file_put_contents( self::root() . '/index.php', "<?php // Silence.\n" );
		file_put_contents( self::root() . '/.htaccess', "Require all denied\nDeny from all\n" );
		$job->save();

		$llm = $opts['ai'] ? WPTA_LLM::from_settings() : null;
		WPTA_Sync::lock( $id );
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
		$busy = WPTA_Sync::running();
		if ( $busy && empty( $_POST['force'] ) ) {
			wp_send_json_error( array( 'message' => 'Another conversion is running right now (job ' . $busy . '). Wait for it to finish.', 'busy' => true ) );
		}
		$job = self::create( 'manual' );
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
		$job->run_step();
		wp_send_json_success( $job->status() );
	}

	public static function ajax_runs(): void {
		self::guard();
		wp_send_json_success( array( 'runs' => array_values( WPTA_Sync::runs() ) ) );
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
				'report'  => array( 'entries' => 0, 'types' => array(), 'media' => 0, 'media_failed' => array(), 'cleaned' => array(), 'cleanup_failed' => array(), 'layout' => array(), 'unconverted' => array(), 'seo' => '' ),
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
		$order = array( 'init' => 0, 'content' => 5, 'media' => 45, 'ai_layout' => 60, 'ai_cleanup' => 70, 'package' => 82, 'github' => 86, 'github_upload' => 88, 'github_commit' => 98, 'done' => 100, 'error' => 100 );
		$p     = $order[ $this->s['phase'] ] ?? 0;
		if ( 'content' === $this->s['phase'] && $this->s['ids'] ) {
			$p += (int) ( 40 * $this->s['cursor'] / count( $this->s['ids'] ) );
		}
		if ( 'github_upload' === $this->s['phase'] && ! empty( $this->s['plan']['upload'] ) ) {
			$p += (int) ( 10 * count( $this->s['uploaded'] ) / count( $this->s['plan']['upload'] ) );
		}
		return min( 100, $p );
	}

	private function write( string $rel, string $contents ): void {
		$path = $this->site_dir() . '/' . $rel;
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, $contents );
	}

	/* ------------------------------------------------------------- Running */

	/** One step, with errors turned into an error phase. Saves state. */
	public function run_step(): void {
		if ( $this->done() ) {
			return;
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		WPTA_Sync::lock( $this->id() );
		try {
			$this->step();
		} catch ( Throwable $e ) {
			$this->s['phase'] = 'error';
			$this->say( 'Error: ' . $e->getMessage() );
			$this->s['result']['error'] = $e->getMessage();
		}
		if ( $this->done() ) {
			$this->finish();
		}
		$this->save();
	}

	/** Run steps for up to $seconds (cron and CLI). */
	public function run_for( int $seconds, ?callable $on_log = null ): void {
		$until = microtime( true ) + $seconds;
		do {
			$this->run_step();
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
				'finished' => time(),
				'status'   => 'error' === $this->s['phase'] ? 'error' : 'done',
				'entries'  => $this->s['report']['entries'],
				'files'    => count( $this->s['files'] ),
				'changed'  => $r['changed'] ?? null,
				'deleted'  => $r['deleted'] ?? null,
				'kept'     => $r['kept'] ?? array(),
				'repo'     => $r['repo'] ?? '',
				'commit'   => $r['commit'] ?? '',
				'zip'      => $r['zip'] ?? '',
				'error'    => $r['error'] ?? '',
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

		$sheets = WPTA_Exporter::copy_theme( $this->site_dir(), $home );
		$this->say( 'Copied theme assets (' . wp_get_theme()->get( 'Name' ) . '), ' . count( $sheets ) . ' stylesheet(s).' );

		$site            = WPTA_Exporter::site_data( $sheets );
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

		$this->s['ids']   = array_map( 'intval', WPTA_Exporter::entry_ids() );
		$this->s['phase'] = 'content';
		$types            = array_diff( WPTA_Exporter::post_types(), array( 'post', 'page' ) );
		$this->say( 'Found ' . count( $this->s['ids'] ) . ' published entries' . ( $types ? ' (including custom types: ' . implode( ', ', $types ) . ')' : '' ) . '.' );
	}

	private function phase_content(): void {
		$batch = array_slice( $this->s['ids'], $this->s['cursor'], self::ENTRY_BATCH );
		$media = array_flip( $this->s['media'] );
		foreach ( $batch as $id ) {
			$e    = WPTA_Exporter::entry( $id );
			$path = $e['path'];
			if ( isset( $this->s['paths'][ $path ] ) ) {
				$path = preg_replace( '/\.md$/', '-' . $id . '.md', $path );
			}
			$this->s['paths'][ $path ] = $e['permalink'];
			$this->write( $path, $e['markdown'] );
			foreach ( $e['media'] as $m ) {
				$media[ $m ] = true;
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
			$redirects = WPTA_Exporter::redirects( $this->s['redirects'], array_values( $this->s['paths'] ), $this->s['site'] );
			$this->write( 'redirects.json', wp_json_encode( $redirects, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
			$this->write( 'public/_redirects', WPTA_Exporter::redirects_file( $redirects ) );
			$this->s['report']['redirects'] = count( $redirects['paths'] ) + count( $redirects['query'] );
			$this->s['cursor']              = 0;
			$this->s['phase']               = 'media';
			if ( $this->s['flagged'] ) {
				$n = count( $this->s['flagged'] );
				$this->say( $n . ( 1 === $n ? ' entry contains' : ' entries contain' ) . ' dead shortcodes.' );
			}
		}
	}

	private function phase_media(): void {
		$batch = array_slice( $this->s['media'], $this->s['cursor'], self::MEDIA_BATCH );
		foreach ( $batch as $rel ) {
			$err = WPTA_Exporter::copy_media( $rel, $this->site_dir() );
			if ( '' === $err ) {
				++$this->s['report']['media'];
			} else {
				$this->s['report']['media_failed'][] = $rel . ' — ' . $err;
			}
		}
		$this->s['cursor'] += count( $batch );
		$this->say( sprintf( 'Copied media %d / %d.', $this->s['cursor'], count( $this->s['media'] ) ) );
		if ( $this->s['cursor'] >= count( $this->s['media'] ) ) {
			$this->s['cursor'] = 0;
			$this->s['phase']  = 'ai_layout';
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
						file_put_contents( $this->site_dir() . '/' . $f['path'], $cached );
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
			file_put_contents( $file, $out['doc'] );
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

		$files = array();
		$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->site_dir(), FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( $file->isFile() ) {
				$rel           = ltrim( str_replace( '\\', '/', substr( $file->getPathname(), strlen( $this->site_dir() ) ) ), '/' );
				$files[ $rel ] = WPTA_GitHub::blob_sha( (string) file_get_contents( $file->getPathname() ) );
			}
		}
		ksort( $files );
		$this->s['files'] = $files;

		if ( $this->s['opts']['zip'] ) {
			$this->zip( array_keys( $files ) );
		}

		$this->s['cursor'] = 0;
		$this->s['phase']  = $this->s['opts']['github'] && WPTA_Settings::github_connected() ? 'github' : 'done';
		if ( 'done' === $this->s['phase'] ) {
			$this->say( 'Done.' );
		}
	}

	private function zip( array $files ): void {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->say( 'ZipArchive is not available on this server; skipping the zip.' );
			return;
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $this->dir() . '/site.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return;
		}
		$prefix = $this->s['site']['slug'] . '-astro/';
		foreach ( $files as $rel ) {
			$zip->addFile( $this->site_dir() . '/' . $rel, $prefix . $rel );
		}
		$zip->close();
		$this->s['result']['zip'] = add_query_arg(
			array(
				'action'   => 'wpta_download',
				'job'      => $this->s['id'],
				'_wpnonce' => wp_create_nonce( 'wpta_download_' . $this->s['id'] ),
			),
			admin_url( 'admin-post.php' )
		);
		$this->say( 'Packaged ' . count( $files ) . ' files into a zip.' );
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
		$manifest = WPTA_Sync::manifest( $this->repo_key() );
		$plan     = WPTA_Sync::plan( $this->s['files'], $remote['files'], $manifest, (array) $this->s['opts']['keep'] );

		$this->s['remote'] = array( 'head' => $remote['head'] );
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
		foreach ( array_slice( $todo, 0, self::BLOB_BATCH ) as $rel ) {
			$sha = $gh->blob( $this->s['repo'], (string) file_get_contents( $this->site_dir() . '/' . $rel ) );
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

		$commit = $this->github()->commit( $this->s['repo'], $plan['tree'], $message, $this->s['remote']['head'] );
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
		$md .= "\n- Media files copied: " . $r['media'] . "\n";
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
	}

	private static function rrmdir( string $dir ): void {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
		}
		rmdir( $dir );
	}
}
