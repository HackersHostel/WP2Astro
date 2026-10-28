<?php
defined( 'ABSPATH' ) || exit;

/**
 * Phased conversion job driven by AJAX, so each request stays short.
 * State: wp-content/uploads/wp-to-astro/{job}/state.json, files: …/{job}/site/
 */
class WPTA_Job {

	const ENTRY_BATCH = 20;
	const MEDIA_BATCH = 100;
	const BLOB_BATCH  = 25;

	private array $s;
	private array $log = array();

	public static function init(): void {
		add_action( 'wp_ajax_wpta_start', array( __CLASS__, 'ajax_start' ) );
		add_action( 'wp_ajax_wpta_step', array( __CLASS__, 'ajax_step' ) );
		add_action( 'admin_post_wpta_download', array( __CLASS__, 'download' ) );
	}

	/* -------------------------------------------------------------- Paths */

	public static function root(): string {
		return wp_get_upload_dir()['basedir'] . '/wp-to-astro';
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

	/* --------------------------------------------------------------- AJAX */

	private static function guard(): void {
		check_ajax_referer( 'wpta' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}
	}

	public static function ajax_start(): void {
		self::guard();
		self::cleanup_old();
		$id = strtolower( wp_generate_password( 12, false ) );
		$job = new self( array( 'id' => $id ) );
		wp_mkdir_p( $job->dir() . '/site' );
		file_put_contents( self::root() . '/index.php', "<?php // Silence.\n" );
		file_put_contents( self::root() . '/.htaccess', "Require all denied\nDeny from all\n" );
		$job->save();
		wp_send_json_success( array( 'job' => $id, 'log' => array( 'Job ' . $id . ' created.' ) ) );
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
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		try {
			$job->step();
		} catch ( Throwable $e ) {
			$job->s['phase'] = 'error';
			$job->say( 'Error: ' . $e->getMessage() );
		}
		$job->save();
		wp_send_json_success( $job->status() );
	}

	public static function download(): void {
		$id = sanitize_key( wp_unslash( $_GET['job'] ?? '' ) );
		if ( ! current_user_can( 'manage_options' ) || ! self::valid_id( $id ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ?? '' ), 'wpta_download_' . $id ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$zip = self::root() . '/' . $id . '/site.zip';
		if ( ! is_readable( $zip ) ) {
			wp_die( 'Zip not found.', 404 );
		}
		$job = self::load( $id );
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . ( $job ? $job->s['site']['slug'] : 'site' ) . '-astro.zip"' );
		header( 'Content-Length: ' . filesize( $zip ) );
		readfile( $zip );
		exit;
	}

	/* -------------------------------------------------------------- State */

	public function __construct( array $state ) {
		$this->s = array_merge(
			array(
				'phase'   => 'init',
				'created' => time(),
				'ids'     => array(),
				'cursor'  => 0,
				'media'   => array(),
				'flagged' => array(),
				'paths'   => array(),
				'report'  => array( 'entries' => 0, 'media' => 0, 'media_failed' => array(), 'cleaned' => array(), 'cleanup_failed' => array(), 'layout' => array(), 'unconverted' => array() ),
				'files'   => array(),
				'blobs'   => array(),
				'repo'    => null,
				'result'  => array(),
			),
			$state
		);
	}

	public static function load( string $id ): ?self {
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

	private function status(): array {
		return array(
			'phase'    => $this->s['phase'],
			'done'     => in_array( $this->s['phase'], array( 'done', 'error' ), true ),
			'error'    => 'error' === $this->s['phase'],
			'progress' => $this->progress(),
			'log'      => $this->log,
			'result'   => $this->s['result'],
		);
	}

	private function progress(): int {
		$order = array( 'init' => 0, 'content' => 5, 'media' => 45, 'ai_layout' => 60, 'ai_cleanup' => 70, 'package' => 85, 'github' => 88, 'done' => 100, 'error' => 100 );
		$p     = $order[ $this->s['phase'] ] ?? 0;
		if ( 'content' === $this->s['phase'] && $this->s['ids'] ) {
			$p += (int) ( 40 * $this->s['cursor'] / count( $this->s['ids'] ) );
		}
		if ( 'github' === $this->s['phase'] && $this->s['files'] ) {
			$p += (int) ( 11 * count( $this->s['blobs'] ) / count( $this->s['files'] ) );
		}
		return min( 100, $p );
	}

	private function write( string $rel, string $contents ): void {
		$path = $this->site_dir() . '/' . $rel;
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, $contents );
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
		}
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

		$this->s['ids']   = array_map( 'intval', WPTA_Exporter::entry_ids() );
		$this->s['phase'] = 'content';
		$this->say( 'Found ' . count( $this->s['ids'] ) . ' published posts and pages.' );
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
			$this->s['paths'][ $path ] = $id;
			$this->write( $path, $e['markdown'] );
			foreach ( $e['media'] as $m ) {
				$media[ $m ] = true;
			}
			foreach ( $e['flags'] as $flag ) {
				if ( 0 === strpos( $flag, 'shortcodes:' ) ) {
					$this->s['flagged'][] = array( 'path' => $path, 'title' => $e['title'], 'shortcodes' => explode( ',', substr( $flag, 11 ) ) );
				}
			}
			++$this->s['report']['entries'];
		}
		$this->s['cursor'] += count( $batch );
		$this->s['media']   = array_keys( $media );
		$this->say( sprintf( 'Converted %d / %d entries to Markdown.', $this->s['cursor'], count( $this->s['ids'] ) ) );

		if ( $this->s['cursor'] >= count( $this->s['ids'] ) ) {
			$this->s['cursor'] = 0;
			$this->s['phase']  = 'media';
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
		$settings = WPTA_Settings::get();
		$llm      = WPTA_LLM::from_settings();
		$this->s['phase'] = 'ai_cleanup';
		if ( ! $llm || ! $settings['ai_layout'] ) {
			$this->s['report']['layout'][] = 'AI layout off; default layout used.';
			$this->say( 'AI layout skipped; using the default layout.' );
			return;
		}
		$this->say( 'Asking ' . $llm->model() . ' to rebuild the theme layout…' );
		$home = (string) @file_get_contents( $this->dir() . '/home.html' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$res  = WPTA_AI::layout( $llm, $home, $this->s['site'], WPTA_AI::theme_source() );
		foreach ( $res['log'] as $line ) {
			$this->say( $line );
		}
		$this->s['report']['layout'] = $res['log'];
		if ( $res['code'] ) {
			$this->write( 'src/layouts/BaseLayout.astro', $res['code'] );
		}
	}

	private function phase_ai_cleanup(): void {
		$settings = WPTA_Settings::get();
		$llm      = WPTA_LLM::from_settings();
		if ( ! $llm || ! $settings['ai_cleanup'] || $this->s['cursor'] >= count( $this->s['flagged'] ) ) {
			if ( ( ! $llm || ! $settings['ai_cleanup'] ) && $this->s['flagged'] ) {
				foreach ( $this->s['flagged'] as $f ) {
					$this->s['report']['unconverted'][] = $f;
				}
				$this->say( 'AI cleanup off; dead shortcodes listed in CONVERSION_REPORT.md.' );
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
			file_put_contents( $file, $out );
			$this->s['report']['cleaned'][] = $f['path'];
			$this->say( 'Cleaned shortcodes in "' . $f['title'] . '".' );
		}
		++$this->s['cursor'];
	}

	private function phase_package(): void {
		$this->write( 'CONVERSION_REPORT.md', $this->report() );

		$files = array();
		$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->site_dir(), FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( $file->isFile() ) {
				$files[] = ltrim( str_replace( '\\', '/', substr( $file->getPathname(), strlen( $this->site_dir() ) ) ), '/' );
			}
		}
		sort( $files );
		$this->s['files'] = $files;

		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();
			if ( true === $zip->open( $this->dir() . '/site.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
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
		} else {
			$this->say( 'ZipArchive is not available on this server; skipping the zip.' );
		}

		$this->s['cursor'] = 0;
		$this->s['phase']  = WPTA_Settings::get()['github_token'] ? 'github' : 'done';
		if ( 'done' === $this->s['phase'] ) {
			$this->say( 'Done.' );
		}
	}

	private function phase_github(): void {
		$settings = WPTA_Settings::get();
		$gh       = new WPTA_GitHub( $settings['github_token'] );

		if ( ! $this->s['repo'] ) {
			$repo = $gh->ensure_repo( $settings['repo_name'], (bool) $settings['repo_private'], 'Astro version of ' . $this->s['site']['title'] . ', converted from WordPress with WP to Astro.' );
			if ( is_wp_error( $repo ) ) {
				throw new RuntimeException( $repo->get_error_message() );
			}
			$this->s['repo'] = $repo;
			$this->say( 'Using GitHub repo ' . $repo['owner'] . '/' . $repo['repo'] . '.' );
			return;
		}

		$todo = array_values( array_diff( $this->s['files'], array_keys( $this->s['blobs'] ) ) );
		if ( $todo ) {
			foreach ( array_slice( $todo, 0, self::BLOB_BATCH ) as $rel ) {
				$sha = $gh->blob( $this->s['repo'], (string) file_get_contents( $this->site_dir() . '/' . $rel ) );
				if ( is_wp_error( $sha ) ) {
					throw new RuntimeException( $rel . ': ' . $sha->get_error_message() );
				}
				$this->s['blobs'][ $rel ] = $sha;
			}
			$this->say( sprintf( 'Uploaded %d / %d files to GitHub.', count( $this->s['blobs'] ), count( $this->s['files'] ) ) );
			return;
		}

		$url = $gh->commit( $this->s['repo'], $this->s['blobs'], 'Convert WordPress site to Astro (' . gmdate( 'Y-m-d H:i' ) . ' UTC)' );
		if ( is_wp_error( $url ) ) {
			throw new RuntimeException( $url->get_error_message() );
		}
		$this->s['result']['repo']   = $this->s['repo']['url'];
		$this->s['result']['commit'] = $url;
		$this->s['phase']            = 'done';
		$this->say( 'Committed to ' . $this->s['repo']['url'] );
		$this->say( 'Done.' );
	}

	private function report(): string {
		$r    = $this->s['report'];
		$llm  = WPTA_LLM::from_settings();
		$md   = "# Conversion report\n\n";
		$md  .= '- Source: ' . home_url( '/' ) . "\n";
		$md  .= '- Converted: ' . gmdate( 'Y-m-d H:i' ) . " UTC\n";
		$md  .= '- Model: ' . ( $llm ? $llm->model() : 'none' ) . "\n";
		$md  .= '- Entries: ' . $r['entries'] . "\n";
		$md  .= '- Media files copied: ' . $r['media'] . "\n\n";

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
		$md .= "## Not converted (by design)\n\n- Comments, forms, search and other features that need a server.\n- Plugin-generated pages and widgets.\n";
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
