<?php
defined( 'ABSPATH' ) || exit;

/**
 * The WP2Astro screen: a three-step setup on first use, then a dashboard with Overview, Activity and Settings tabs.
 */
class WPTA_Admin {

	const NOTICES = array(
		'saved'        => array( 'success', 'Settings saved.' ),
		'or_connected' => array( 'success', 'OpenRouter is connected. Pick a model and save.' ),
		'or_failed'    => array( 'error', 'OpenRouter didn’t finish connecting. Try again, or paste an API key instead.' ),
		'gh_bad'       => array( 'error', 'GitHub didn’t accept that token, so it wasn’t saved. Check its permissions (Administration and Contents: read & write) and expiry.' ),
	);

	/** '1' once the setup is finished or skipped, '0' to show it again. Missing: shown until the first run. */
	const SETUP = 'wpta_setup_done';

	/** Brand marks, 24×24 (from each company's press kit). */
	const LOGOS = array(
		'github'     => 'M12 0c6.63 0 12 5.276 12 11.79-.001 5.067-3.29 9.567-8.175 11.187-.6.118-.825-.25-.825-.56 0-.398.015-1.665.015-3.242 0-1.105-.375-1.813-.81-2.181 2.67-.295 5.475-1.297 5.475-5.822 0-1.297-.465-2.344-1.23-3.169.12-.295.54-1.503-.12-3.125 0 0-1.005-.324-3.3 1.209a11.32 11.32 0 00-3-.398c-1.02 0-2.04.133-3 .398-2.295-1.518-3.3-1.209-3.3-1.209-.66 1.622-.24 2.83-.12 3.125-.765.825-1.23 1.887-1.23 3.169 0 4.51 2.79 5.527 5.46 5.822-.345.294-.66.81-.765 1.577-.69.31-2.415.81-3.495-.973-.225-.354-.9-1.223-1.845-1.209-1.005.015-.405.56.015.781.51.28 1.095 1.327 1.23 1.666.24.663 1.02 1.93 4.035 1.385 0 .988.015 1.916.015 2.196 0 .31-.225.664-.825.56C3.303 21.374-.003 16.867 0 11.791 0 5.276 5.37 0 12 0z',
		'openai'     => 'M9.205 8.658v-2.26c0-.19.072-.333.238-.428l4.543-2.616c.619-.357 1.356-.523 2.117-.523 2.854 0 4.662 2.212 4.662 4.566 0 .167 0 .357-.024.547l-4.71-2.759a.797.797 0 00-.856 0l-5.97 3.473zm10.609 8.8V12.06c0-.333-.143-.57-.429-.737l-5.97-3.473 1.95-1.118a.433.433 0 01.476 0l4.543 2.617c1.309.76 2.189 2.378 2.189 3.948 0 1.808-1.07 3.473-2.76 4.163zM7.802 12.703l-1.95-1.142c-.167-.095-.239-.238-.239-.428V5.899c0-2.545 1.95-4.472 4.591-4.472 1 0 1.927.333 2.712.928L8.23 5.067c-.285.166-.428.404-.428.737v6.898zM12 15.128l-2.795-1.57v-3.33L12 8.658l2.795 1.57v3.33L12 15.128zm1.796 7.23c-1 0-1.927-.332-2.712-.927l4.686-2.712c.285-.166.428-.404.428-.737v-6.898l1.974 1.142c.167.095.238.238.238.428v5.233c0 2.545-1.974 4.472-4.614 4.472zm-5.637-5.303l-4.544-2.617c-1.308-.761-2.188-2.378-2.188-3.948A4.482 4.482 0 014.21 6.327v5.423c0 .333.143.571.428.738l5.947 3.449-1.95 1.118a.432.432 0 01-.476 0zm-.262 3.9c-2.688 0-4.662-2.021-4.662-4.519 0-.19.024-.38.047-.57l4.686 2.71c.286.167.571.167.856 0l5.97-3.448v2.26c0 .19-.07.333-.237.428l-4.543 2.616c-.619.357-1.356.523-2.117.523zm5.899 2.83a5.947 5.947 0 005.827-4.756C22.287 18.339 24 15.84 24 13.296c0-1.665-.713-3.282-1.998-4.448.119-.5.19-.999.19-1.498 0-3.401-2.759-5.947-5.946-5.947-.642 0-1.26.095-1.88.31A5.962 5.962 0 0010.205 0a5.947 5.947 0 00-5.827 4.757C1.713 5.447 0 7.945 0 10.49c0 1.666.713 3.283 1.998 4.448-.119.5-.19 1-.19 1.499 0 3.401 2.759 5.946 5.946 5.946.642 0 1.26-.095 1.88-.309a5.96 5.96 0 004.162 1.713z',
		'claude'     => 'M4.709 15.955l4.72-2.647.08-.23-.08-.128H9.2l-.79-.048-2.698-.073-2.339-.097-2.266-.122-.571-.121L0 11.784l.055-.352.48-.321.686.06 1.52.103 2.278.158 1.652.097 2.449.255h.389l.055-.157-.134-.098-.103-.097-2.358-1.596-2.552-1.688-1.336-.972-.724-.491-.364-.462-.158-1.008.656-.722.881.06.225.061.893.686 1.908 1.476 2.491 1.833.365.304.145-.103.019-.073-.164-.274-1.355-2.446-1.446-2.49-.644-1.032-.17-.619a2.97 2.97 0 01-.104-.729L6.283.134 6.696 0l.996.134.42.364.62 1.414 1.002 2.229 1.555 3.03.456.898.243.832.091.255h.158V9.01l.128-1.706.237-2.095.23-2.695.08-.76.376-.91.747-.492.584.28.48.685-.067.444-.286 1.851-.559 2.903-.364 1.942h.212l.243-.242.985-1.306 1.652-2.064.73-.82.85-.904.547-.431h1.033l.76 1.129-.34 1.166-1.064 1.347-.881 1.142-1.264 1.7-.79 1.36.073.11.188-.02 2.856-.606 1.543-.28 1.841-.315.833.388.091.395-.328.807-1.969.486-2.309.462-3.439.813-.042.03.049.061 1.549.146.662.036h1.622l3.02.225.79.522.474.638-.079.485-1.215.62-1.64-.389-3.829-.91-1.312-.329h-.182v.11l1.093 1.068 2.006 1.81 2.509 2.33.127.578-.322.455-.34-.049-2.205-1.657-.851-.747-1.926-1.62h-.128v.17l.444.649 2.345 3.521.122 1.08-.17.353-.608.213-.668-.122-1.374-1.925-1.415-2.167-1.143-1.943-.14.08-.674 7.254-.316.37-.729.28-.607-.461-.322-.747.322-1.476.389-1.924.315-1.53.286-1.9.17-.632-.012-.042-.14.018-1.434 1.967-2.18 2.945-1.726 1.845-.414.164-.717-.37.067-.662.401-.589 2.388-3.036 1.44-1.882.93-1.086-.006-.158h-.055L4.132 18.56l-1.13.146-.487-.456.061-.746.231-.243 1.908-1.312-.006.006z',
		'openrouter' => 'M18.654 3.87a5.087 5.087 0 110 10.174L23.7 19.09c.64.641.187 1.737-.72 1.737H8.48a8.479 8.479 0 010-16.958h10.175zM8.479 7.26a5.087 5.087 0 100 10.176 5.087 5.087 0 000-10.175z',
	);

	const LOGO_FILL = array( 'github' => '#181717', 'openai' => '#0d0d0d', 'claude' => '#d97757', 'openrouter' => '#c8ff00' );

	/** Line icons, 24×24, drawn with stroke="currentColor". */
	const ICONS = array(
		'sync'     => '<path d="M21 12a9 9 0 0 1-15.5 6.2L3 16"/><path d="M3 12a9 9 0 0 1 15.5-6.2L21 8"/><path d="M21 3v5h-5M3 21v-5h5"/>',
		'external' => '<path d="M14 3h7v7M21 3l-9 9"/><path d="M19 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5"/>',
		'download' => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
		'upload'   => '<path d="M12 15V3M7 8l5-5 5 5M5 21h14"/>',
		'check'    => '<path d="M20 6L9 17l-5-5"/>',
		'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'alert'    => '<path d="M12 3l9.5 17h-19z"/><path d="M12 10v4M12 17.5v.01"/>',
		'sparkle'  => '<path d="M12 3l1.9 5.8L20 11l-6.1 2.2L12 19l-1.9-5.8L4 11l6.1-2.2z"/>',
		'server'   => '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>',
		'layout'   => '<rect x="3" y="3" width="18" height="7" rx="1.5"/><rect x="3" y="14" width="9" height="7" rx="1.5"/><rect x="16" y="14" width="5" height="7" rx="1.5"/>',
		'lock'     => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
		'rss'      => '<path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/>',
		'file'     => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h6"/>',
		'folder'   => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
		'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		'bolt'     => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
		'stop'     => '<rect x="6" y="6" width="12" height="12" rx="2"/>',
		'copy'     => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
		'home'     => '<path d="M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
		'activity' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
		'sliders'  => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
		'help'     => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .9-1 1.7M12 17h.01"/>',
		'database' => '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
		'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13M9 7V4h6v3"/>',
		'key'      => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3"/>',
		'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'wand'     => '<path d="M15 4V2M15 10V8M11 6H9M21 6h-2M3 21l9-9"/>',
		'commit'   => '<circle cx="12" cy="12" r="3"/><path d="M3 12h6M15 12h6"/>',
		'image'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/>',
		'package'  => '<path d="M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8"/>',
		'terminal' => '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M7 10l3 2.5L7 15M12 15h5"/>',
		'redirect' => '<path d="M4 17V9a4 4 0 0 1 4-4h11"/><path d="M15 1l4 4-4 4"/>',
		'tag'      => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
	);

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_wpta_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_wpta_setup', array( __CLASS__, 'setup' ) );
		add_action( 'wp_ajax_wpta_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_wpta_auto_sync', array( __CLASS__, 'ajax_auto_sync' ) );
		add_action( 'wp_ajax_wpta_storage_test', array( __CLASS__, 'ajax_storage_test' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WPTA_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function url( string $hash = '' ): string {
		return admin_url( 'admin.php?page=' . WPTA_Settings::SLUG ) . ( $hash ? '#' . $hash : '' );
	}

	public static function menu(): void {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="6 6 52 52"><path fill="black" fill-opacity=".55" d="M24.5 51h9.5L45.5 13H36ZM36.5 13H46l10.5 38H47Z"/><path fill="black" d="M7.5 13h6.4l8 38h-6.4ZM15.5 51h6.4l8-38h-6.4ZM23.5 13h6.4l8 38h-6.4ZM31.5 51h6.4l8-38h-6.4ZM40.5 35h9v6h-9Z"/></svg>' );
		$name = WPTA_Settings::brand()['name'];
		$hook = add_menu_page( $name, $name, 'manage_options', WPTA_Settings::SLUG, array( __CLASS__, 'render' ), $icon, 81 );
		add_action( 'load-' . $hook, array( __CLASS__, 'help' ) );
	}

	/** A page of the documentation, e.g. doc( 'getting-started' ). White-label builds move the base with WP2ASTRO_BRAND_DOCS. */
	public static function doc( string $page ): string {
		return untrailingslashit( WPTA_Settings::brand()['docs'] ) . '/' . $page . '.md';
	}

	private static function doc_link( string $page, string $text ): string {
		return '<a href="' . esc_url( self::doc( $page ) ) . '" target="_blank" rel="noopener">' . esc_html( $text ) . ' ↗</a>';
	}

	/** The Help tabs and sidebar of the screen: a few sentences each, with links to the full pages. */
	public static function help(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$brand = WPTA_Settings::brand();
		$name  = esc_html( $brand['name'] );
		$tabs  = array(
			'overview'        => array(
				'Overview',
				"<p>{$name} turns this site into an Astro project with the same URLs and the same look, pushes it to GitHub and keeps it in sync. Nothing heavy runs on your server.</p>
				<p>The first time, a three-step setup asks how AI should help, whether to connect <strong>GitHub</strong>, and what to build, then converts the site. After that the screen has three tabs: <strong>Overview</strong> (status, <strong>Sync now</strong>, connections), <strong>Activity</strong> (recent runs) and <strong>Settings</strong>. Without GitHub you download a zip.</p>",
				array( 'getting-started' => 'Getting started' ),
			),
			'ai'              => array(
				'AI model',
				'<p>AI rebuilds your theme’s header, menu and footer as an Astro layout and replaces shortcodes whose plugins are gone. Without it you still get a working site with a clean default layout.</p>
				<p><strong>Local model</strong> runs an open model such as Gemma on a server you run, for free. <strong>ChatGPT plan</strong> uses your ChatGPT Plus or Pro plan through <strong>Sign in with ChatGPT</strong>. <strong>Claude plan</strong> converts with the default layout and adds the agent kit, so Claude Code can finish the site on your plan. <strong>OpenRouter</strong> is pay as you go, with some free models.</p>',
				array( 'providers' => 'Choose an AI model', 'local-models' => 'Run a local model', 'chatgpt' => 'Use your ChatGPT plan', 'openrouter' => 'Use OpenRouter', 'claude-code' => 'Finish the site with Claude Code' ),
			),
			'github'          => array(
				'GitHub',
				'<p>Click <strong>Connect GitHub</strong>, enter the code at github.com/login/device and approve. Or paste a fine-grained personal access token with <strong>Administration</strong> and <strong>Contents</strong> set to read and write.</p>
				<p>The repository is created on the first run. It is private unless you turn off <strong>Private repository</strong>. Tokens are stored encrypted.</p>',
				array( 'github' => 'Connect GitHub' ),
			),
			'media'           => array(
				'Media',
				'<p>Under <strong>Settings → Media</strong>, choose where images and other uploads go: copied into the repo (files over 25 MB stay on WordPress), stored with Git LFS, left on WordPress, or uploaded to Amazon S3, Cloudflare R2 or Bunny Storage. Click <strong>Test storage</strong> before saving storage settings.</p>',
				array( 'media' => 'Choose where media goes' ),
			),
			'sync'            => array(
				'Sync',
				'<p>After the first run, <strong>Sync now</strong> on the Overview tab commits only the files that changed, removes entries you deleted or unpublished, and keeps every file someone edited in the repo.</p>
				<p>Turn on <strong>Sync to GitHub automatically</strong> to sync a couple of minutes after you publish, update or delete a post. The <strong>Activity</strong> tab lists the last 20 runs with links to each commit.</p>',
				array( 'sync' => 'Keep the site in sync', 'deploy' => 'Deploy the site' ),
			),
			'troubleshooting' => array(
				'Troubleshooting',
				'<p>When a run stops, open <strong>Log</strong> under the progress steps to see the step that failed. <code>CONVERSION_REPORT.md</code> in the repo or zip lists anything that needs a look.</p>
				<p>Common causes: the server can’t load its own homepage, the AI answer was rejected and the default layout was used, the ChatGPT usage limit is reached, or someone pushed to the repo during a sync. In that last case, run the sync again.</p>',
				array( 'troubleshooting' => 'Troubleshooting' ),
			),
		);
		foreach ( $tabs as $id => $tab ) {
			$links = array();
			foreach ( $tab[2] as $page => $text ) {
				$links[] = self::doc_link( $page, $text );
			}
			$screen->add_help_tab(
				array(
					'id'      => 'wpta-' . $id,
					'title'   => $tab[0],
					'content' => $tab[1] . '<p>' . implode( ' · ', $links ) . '</p>',
				)
			);
		}

		$side  = '<p><strong>For more information:</strong></p>';
		$side .= '<p><a href="' . esc_url( untrailingslashit( $brand['docs'] ) ) . '" target="_blank" rel="noopener">Documentation</a></p>';
		if ( ! empty( $brand['forum'] ) ) {
			$side .= '<p><a href="' . esc_url( $brand['forum'] ) . '" target="_blank" rel="noopener">Ask a question</a></p>';
		}
		$side .= '<p><a href="' . esc_url( $brand['support'] ) . '" target="_blank" rel="noopener">Report a problem</a></p>';
		if ( current_user_can( 'install_plugins' ) ) {
			// The plugin details modal: readme.txt and the release notes (or WordPress.org's copy).
			add_thickbox();
			$details = self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . dirname( plugin_basename( WPTA_FILE ) ) . '&section=changelog&TB_iframe=true&width=772&height=550' );
			$side   .= '<p><a href="' . esc_url( $details ) . '" class="thickbox open-plugin-details-modal">Changelog</a></p>';
		}
		$screen->set_help_sidebar( $side );
	}

	public static function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">Open</a>' );
		return $links;
	}

	public static function body_class( string $classes ): string {
		$screen = get_current_screen();
		return $screen && 'toplevel_page_' . WPTA_Settings::SLUG === $screen->id ? $classes . ' wpta-screen' : $classes;
	}

	/* --------------------------------------------------------------- Saving */

	/**
	 * Sanitized changes from the settings form, with a new GitHub token checked against GitHub.
	 *
	 * @return array{0:array, 1:string} Changes and the notice to show.
	 */
	private static function changes_from_form( array $in ): array {
		$changes = WPTA_Settings::from_form( $in );
		$notice  = 'saved';
		if ( isset( $changes['github_token'] ) ) {
			// New token: check it now and remember whose it is.
			$user = ( new WPTA_GitHub( $changes['github_token'] ) )->request( 'GET', '/user' );
			if ( is_wp_error( $user ) ) {
				unset( $changes['github_token'], $changes['github_auth'], $changes['github_login'] );
				$notice = 'gh_bad';
			} else {
				$changes['github_login'] = (string) $user['login'];
			}
		}
		return array( $changes, $notice );
	}

	public static function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'wpta_save' );
		list( $changes, $notice ) = self::changes_from_form( (array) ( $_POST['wpta'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized field by field in from_form().
		WPTA_Settings::update( $changes );
		$hash = sanitize_key( wp_unslash( $_POST['wpta_section'] ?? '' ) );
		wp_safe_redirect( add_query_arg( 'wpta_notice', $notice, admin_url( 'admin.php?page=' . WPTA_Settings::SLUG ) ) . ( $hash ? '#' . $hash : '' ) );
		exit;
	}

	/** The setup's Continue and Convert buttons: save without leaving the page. */
	public static function ajax_save(): void {
		check_ajax_referer( 'wpta' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}
		list( $changes, $notice ) = self::changes_from_form( (array) ( $_POST['wpta'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized field by field in from_form().
		WPTA_Settings::update( $changes );
		if ( 'gh_bad' === $notice ) {
			wp_send_json_error( array( 'message' => self::NOTICES['gh_bad'][1] ) );
		}
		if ( ! empty( $_POST['finish_setup'] ) ) {
			update_option( self::SETUP, '1', false );
		}
		$s  = WPTA_Settings::get();
		$ai = WPTA_LLM::from_settings();
		wp_send_json_success(
			array(
				'ai'      => (bool) $ai,
				'github'  => '' !== $s['github_token'],
				'summary' => array(
					'ai'     => self::ai_summary( $s, $ai ),
					'output' => self::output_summary( $s ),
				),
			)
		);
	}

	/** The Auto-sync switch on the Overview tab. */
	public static function ajax_auto_sync(): void {
		check_ajax_referer( 'wpta' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}
		$on = ! empty( $_POST['on'] );
		if ( $on && ! WPTA_Settings::github_connected() ) {
			wp_send_json_error( array( 'message' => 'Connect GitHub to turn on auto-sync.' ) );
		}
		WPTA_Settings::update( array( 'auto_sync' => $on ? 1 : 0 ) );
		wp_send_json_success( array( 'on' => $on ) );
	}

	/** Settings → Media → Test storage: upload a small file with the values in the form, then fetch it back. */
	public static function ajax_storage_test(): void {
		check_ajax_referer( 'wpta' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}
		$in  = (array) wp_unslash( $_POST['wpta'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized below.
		$s   = WPTA_Settings::get();
		$try = array( 'cdn_provider' => sanitize_key( $in['cdn_provider'] ?? '' ) );
		foreach ( array( 'cdn_endpoint', 'cdn_url' ) as $k ) {
			$try[ $k ] = esc_url_raw( trim( (string) ( $in[ $k ] ?? '' ) ) );
		}
		foreach ( array( 'cdn_region', 'cdn_bucket', 'cdn_key_id' ) as $k ) {
			$try[ $k ] = sanitize_text_field( (string) ( $in[ $k ] ?? '' ) );
		}
		$try['cdn_secret'] = sanitize_text_field( (string) ( $in['cdn_secret'] ?? '' ) ) ?: $s['cdn_secret'];
		$c                 = WPTA_Storage::config( $try );
		$missing           = WPTA_Storage::missing( $c );
		if ( $missing ) {
			wp_send_json_error( array( 'message' => $missing ) );
		}
		$body = 'WP2Astro storage check ' . wp_generate_password( 12, false ) . "\n";
		$ok   = WPTA_Storage::put_bytes( $c, 'wp2astro-check.txt', $body, 'text/plain' );
		if ( is_wp_error( $ok ) ) {
			wp_send_json_error( array( 'message' => 'Upload failed: ' . $ok->get_error_message() ) );
		}
		$get  = wp_remote_get( $c['url'] . '/wp2astro-check.txt', array( 'timeout' => 15 ) );
		$seen = ! is_wp_error( $get ) && 200 === (int) wp_remote_retrieve_response_code( $get ) && trim( (string) wp_remote_retrieve_body( $get ) ) === trim( $body );
		wp_send_json_success(
			array(
				'message' => $seen
					? 'Uploaded a test file and read it back from ' . $c['url'] . '. Save settings to use it.'
					: 'Uploaded a test file, but ' . $c['url'] . '/wp2astro-check.txt didn’t return it yet. Check the public URL (and that the bucket or zone is public), then try again.',
			)
		);
	}

	/** Skip the setup, or show it again (Settings → Plugin data). */
	public static function setup(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'wpta_setup' );
		$again = ! empty( $_GET['again'] );
		update_option( self::SETUP, $again ? '0' : '1', false );
		wp_safe_redirect( self::url( $again ? '' : 'overview' ) );
		exit;
	}

	private static function setup_url( bool $again ): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=wpta_setup' . ( $again ? '&again=1' : '' ) ), 'wpta_setup' );
	}

	private static function in_setup(): bool {
		$done = get_option( self::SETUP, '' );
		return '0' === $done || ( '' === $done && ! WPTA_Sync::runs() );
	}

	public static function assets( string $hook ): void {
		if ( 'toplevel_page_' . WPTA_Settings::SLUG !== $hook ) {
			return;
		}
		$s = WPTA_Settings::get();
		wp_enqueue_style( 'wpta-admin', WPTA_URL . 'assets/admin.css', array(), WPTA_VERSION );
		wp_enqueue_script( 'wpta-admin', WPTA_URL . 'assets/admin.js', array(), WPTA_VERSION, true );
		wp_localize_script(
			'wpta-admin',
			'WPTA',
			array(
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'wpta' ),
				'providers' => WPTA_Settings::providers(),
				'provider'  => $s['provider'],
				'running'   => WPTA_Sync::running(),
				'setup'     => self::in_setup(),
				'ai'        => WPTA_Settings::ai_enabled(),
				'github'    => '' !== $s['github_token'],
				'icons'     => array(
					'github'   => self::logo( 'github', 16 ),
					'download' => self::icon( 'download', 16 ),
					'external' => self::icon( 'external', 16 ),
					'sync'     => self::icon( 'sync', 16 ),
				),
			)
		);
	}

	/* ------------------------------------------------------------ Pure parts */

	/**
	 * What the status card on the Overview tab says. Pure (unit-tested).
	 *
	 * @param array  $runs    WPTA_Sync::runs(), keyed by job id, newest first.
	 * @param int    $changed When published content last changed (WPTA_Sync::changed()).
	 * @param bool   $gh      Whether GitHub is connected.
	 * @param bool   $auto    Whether auto-sync is on.
	 * @param string $running Id of a running job, or ''.
	 * @return array{state:string, badge:string, title:string, text:string, last:?array, restart:bool}
	 *         restart: a run started from this screen holds the lock, and it only moves while that tab is open.
	 */
	public static function hero_state( array $runs, int $changed, bool $gh, bool $auto, string $running ): array {
		$restart = '' !== $running && 'manual' === ( $runs[ $running ]['trigger'] ?? '' );
		$runs    = array_values( $runs );
		$latest = $runs[0] ?? null;
		$last   = null;
		foreach ( $runs as $r ) {
			if ( 'done' === ( $r['status'] ?? '' ) ) {
				$last = $r;
				break;
			}
		}
		$auto = $auto && $gh;
		if ( $restart ) {
			$out = array( 'running', 'Running', 'A run from this screen hasn’t finished', 'It only moves while the tab that started it is open. If you closed or reloaded that tab, click Start again.' );
		} elseif ( '' !== $running ) {
			$out = array( 'running', 'Running', 'A sync is running', 'It runs in the background. Reload this page to see when it’s done.' );
		} elseif ( $latest && 'error' === ( $latest['status'] ?? '' ) ) {
			$out = array( 'failed', 'Last run stopped', 'The last run didn’t finish', (string) ( $latest['error'] ?? '' ) );
		} elseif ( ! $last ) {
			$out = array( 'new', 'Not converted yet', 'Convert your site to Astro', $gh ? 'The first run creates the repository and pushes the site.' : 'You get a zip to deploy anywhere. Connect GitHub to keep it in sync.' );
		} elseif ( $gh && empty( $last['repo'] ) ) {
			$out = array( 'new', 'Not on GitHub yet', 'Push your site to GitHub', 'The next sync creates the repository and pushes the site.' );
		} elseif ( $changed > (int) ( $last['started'] ?? 0 ) ) {
			$out = array(
				'behind',
				'Changes waiting',
				$gh ? 'Your content changed since the last sync' : 'Your content changed since the last conversion',
				$auto ? 'Auto-sync pushes it a couple of minutes after your last edit.' : ( $gh ? 'Sync now to push the changes to GitHub.' : 'Convert again to get a fresh zip.' ),
			);
		} else {
			$out = array(
				'ok',
				$auto ? 'Auto-sync is on' : 'Up to date',
				$gh ? 'Your Astro site is in sync' : 'Your Astro site is ready',
				$gh ? 'Every published entry is on GitHub. Edits made in the repo are kept on the next sync.' : 'Download the zip, or connect GitHub to keep it in sync.',
			);
		}
		return array( 'state' => $out[0], 'badge' => $out[1], 'title' => $out[2], 'text' => $out[3], 'last' => $last, 'restart' => $restart );
	}

	/**
	 * One run as a line of the activity list. Pure (unit-tested).
	 *
	 * @return array{kind:string, title:string, text:string} kind: error | running | commit | noop | zip.
	 */
	public static function run_item( array $r ): array {
		$status = $r['status'] ?? '';
		if ( 'error' === $status ) {
			$err = (string) ( $r['error'] ?? '' );
			return array( 'kind' => 'error', 'title' => 0 === strpos( $err, 'Stopped' ) ? 'Stopped' : 'Run failed', 'text' => $err );
		}
		if ( 'running' === $status ) {
			return array( 'kind' => 'running', 'title' => 'Running…', 'text' => '' );
		}
		$plural = fn( int $n, string $one, string $many ) => $n . ' ' . ( 1 === $n ? $one : $many );
		if ( ! empty( $r['commit'] ) ) {
			$parts = array( $plural( (int) ( $r['changed'] ?? 0 ), 'file changed', 'files changed' ) );
			if ( ! empty( $r['deleted'] ) ) {
				$parts[] = (int) $r['deleted'] . ' removed';
			}
			$text = implode( ', ', $parts ) . '.';
			if ( ! empty( $r['kept'] ) ) {
				$kept  = array_map( 'basename', array_slice( (array) $r['kept'], 0, 2 ) );
				$more  = count( (array) $r['kept'] ) - count( $kept );
				$text .= ' Kept ' . implode( ', ', $kept ) . ( $more > 0 ? ' and ' . $more . ' more' : '' ) . ', edited in the repo.';
			}
			return array( 'kind' => 'commit', 'title' => 'Synced to GitHub', 'text' => $text );
		}
		if ( ! empty( $r['repo'] ) ) {
			return array( 'kind' => 'noop', 'title' => 'Nothing to push', 'text' => 'All ' . $plural( (int) ( $r['files'] ?? 0 ), 'file is', 'files are' ) . ' already on GitHub.' );
		}
		$text = $plural( (int) ( $r['entries'] ?? 0 ), 'entry', 'entries' );
		if ( ! empty( $r['media'] ) ) {
			$text .= ' and ' . $plural( (int) $r['media'], 'media file', 'media files' );
		}
		return array( 'kind' => 'zip', 'title' => 'Converted to a zip', 'text' => $text . '.' );
	}

	/* --------------------------------------------------------------- Pieces */

	/** A line icon from ICONS. */
	public static function icon( string $name, int $size = 20 ): string {
		return '<svg class="wpta-i" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( self::ICONS[ $name ] ?? '' ) . '</svg>';
	}

	/**
	 * A brand mark from LOGOS.
	 *
	 * @param bool $mono  Draw GitHub and OpenAI in the text color (on teal buttons).
	 * @param bool $boxed Put OpenRouter's lime mark on its own dark square, where no dark tile is around it.
	 */
	public static function logo( string $name, int $size = 20, bool $mono = false, bool $boxed = false ): string {
		$fill = $mono && in_array( $name, array( 'github', 'openai' ), true ) ? 'currentColor' : self::LOGO_FILL[ $name ];
		$rule = in_array( $name, array( 'github', 'openai' ), true ) ? ' fill-rule="evenodd"' : '';
		$open = '<svg class="wpta-logo" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">';
		if ( 'openrouter' === $name && $boxed ) {
			return $open . '<rect width="24" height="24" rx="6" fill="#111111"/><path transform="translate(3.6 3.6) scale(.7)" d="' . self::LOGOS[ $name ] . '" fill="' . $fill . '"/></svg>';
		}
		return $open . '<path d="' . self::LOGOS[ $name ] . '" fill="' . $fill . '"' . $rule . '/></svg>';
	}

	/** Icon tile for a provider: its logo, or a line icon for local and none. */
	private static function provider_tile( string $id, int $size = 20, string $class = 'wpta-tile' ): string {
		$marks = array( 'chatgpt' => 'openai', 'claude' => 'claude', 'openrouter' => 'openrouter' );
		if ( isset( $marks[ $id ] ) ) {
			return '<span class="' . $class . ' is-brand' . ( 'openrouter' === $id ? ' is-dark' : '' ) . '">' . self::logo( $marks[ $id ], $size ) . '</span>';
		}
		return '<span class="' . $class . '">' . self::icon( 'local' === $id ? 'server' : 'layout', $size ) . '</span>';
	}

	private static function counts(): array {
		$counts = array();
		foreach ( WPTA_Exporter::post_types() as $type ) {
			$n   = (int) ( wp_count_posts( $type )->publish ?? 0 );
			$obj = get_post_type_object( $type );
			if ( $n && $obj ) {
				$counts[] = $n . ' ' . strtolower( 1 === $n ? $obj->labels->singular_name : $obj->labels->name );
			}
		}
		return $counts;
	}

	private static function ai_summary( array $s, ?WPTA_LLM $ai ): string {
		if ( $ai ) {
			return $ai->label();
		}
		if ( 'claude' === $s['provider'] ) {
			return 'Default layout, then Claude Code';
		}
		return 'none' === $s['provider'] ? 'None — default layout' : 'Not set up — default layout';
	}

	private static function output_summary( array $s ): string {
		if ( '' === $s['github_token'] ) {
			return 'Zip download';
		}
		return ( $s['github_login'] ? $s['github_login'] . '/' : '' ) . $s['repo_name'] . ( $s['repo_private'] ? ' · private' : ' · public' );
	}

	private static function repo_url( array $s, ?array $last ): string {
		if ( ! empty( $last['repo'] ) ) {
			return (string) $last['repo'];
		}
		return $s['github_login'] && WPTA_Sync::manifest( $s['github_login'] . '/' . $s['repo_name'] ) ? 'https://github.com/' . $s['github_login'] . '/' . $s['repo_name'] : '';
	}

	private static function zip_url( ?array $r ): string {
		return $r && ! empty( $r['zip'] ) && is_readable( WPTA_Job::root() . '/' . $r['id'] . '/site.zip' ) ? (string) $r['zip'] : '';
	}

	private static function ago( int $t ): string {
		return $t ? human_time_diff( $t ) . ' ago' : '';
	}

	private static function toggle( string $name, bool $on, string $icon, string $label, string $desc = '', array $attrs = array() ): void {
		$id   = 'wpta-t-' . $name;
		$more = '';
		foreach ( $attrs as $k => $v ) {
			$more .= ' ' . $k . ( true === $v ? '' : '="' . esc_attr( $v ) . '"' );
		}
		?>
		<label class="wpta-pill" for="<?php echo esc_attr( $id ); ?>">
			<span class="wpta-pill-icon"><?php echo self::icon( $icon, 18 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span>
			<span class="wpta-pill-text"><strong><?php echo esc_html( $label ); ?></strong><?php if ( $desc ) : ?><small><?php echo esc_html( $desc ); ?></small><?php endif; ?></span>
			<input type="checkbox" class="wpta-switch" role="switch" id="<?php echo esc_attr( $id ); ?>" name="wpta[<?php echo esc_attr( $name ); ?>]" value="1" <?php checked( $on ); ?><?php echo $more; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>>
		</label>
		<?php
	}

	/* --------------------------------------------------------------- Render */

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s      = WPTA_Settings::get();
		$cg     = WPTA_ChatGPT::status();
		$notice = self::NOTICES[ sanitize_key( wp_unslash( $_GET['wpta_notice'] ?? '' ) ) ] ?? null; // phpcs:ignore WordPress.Security.NonceVerification -- display only.
		if ( self::in_setup() ) {
			self::render_setup( $s, $cg, $notice );
		} else {
			self::render_dashboard( $s, $cg, $notice );
		}
	}

	private static function notice( ?array $notice ): void {
		if ( $notice ) {
			echo '<div class="notice notice-' . esc_attr( $notice[0] ) . ' is-dismissible"><p>' . esc_html( $notice[1] ) . '</p></div>';
		}
	}

	private static function brand_block( bool $tagline ): void {
		$brand = WPTA_Settings::brand();
		?>
		<span class="wpta-brand">
			<img src="<?php echo esc_url( $brand['logo'] ); ?>" alt="" width="40" height="40">
			<span><strong><?php echo esc_html( $brand['name'] ); ?></strong><?php if ( $tagline ) : ?><small>WordPress in, fast Astro site out</small><?php endif; ?></span>
		</span>
		<?php
	}

	private static function form_open( string $class ): void {
		?>
		<form class="<?php echo esc_attr( $class ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wpta-settings">
			<input type="hidden" name="action" value="wpta_save">
			<input type="hidden" name="wpta_section" value="" id="wpta-section">
			<?php wp_nonce_field( 'wpta_save' ); ?>
		<?php
	}

	private static function footer(): void {
		$brand = WPTA_Settings::brand();
		?>
		<footer class="wpta-foot">
			<?php echo esc_html( $brand['name'] . ' ' . WPTA_VERSION ); ?>
			<?php if ( $brand['by'] ) : ?>· by <?php echo esc_html( $brand['by'] ); ?><?php endif; ?>
			· <a href="<?php echo esc_url( $brand['docs'] ); ?>" target="_blank" rel="noopener">Docs</a>
			· <a href="<?php echo esc_url( $brand['support'] ); ?>" target="_blank" rel="noopener">Support</a>
		</footer>
		<?php
	}

	/* ---------------------------------------------------------------- Setup */

	private static function render_setup( array $s, array $cg, ?array $notice ): void {
		$brand  = WPTA_Settings::brand();
		$gh     = '' !== $s['github_token'];
		$titles = array(
			1 => array( 'How should AI help rebuild your site?', 'AI rebuilds your theme’s header, menu and footer, and cleans up shortcodes from plugins that are gone. Pick what you already have.' ),
			2 => array( 'Where should your Astro site live?', 'Connect GitHub to get a repository you can deploy anywhere and keep in sync. Or skip it and download a zip.' ),
			3 => array( 'Ready to bring it back to life', 'About a minute for most sites. Keep this tab open while it runs.' ),
		);
		$counts = self::counts();
		?>
		<div class="wrap wpta wpta-setup">
			<header class="wpta-top">
				<?php self::brand_block( false ); ?>
				<a class="wpta-quiet-link" href="<?php echo esc_url( self::setup_url( false ) ); ?>">Skip setup</a>
			</header>
			<h1 class="screen-reader-text"><?php echo esc_html( $brand['name'] ); ?> setup</h1>
			<hr class="wp-header-end">
			<?php self::notice( $notice ); ?>

			<?php self::form_open( 'wpta-wizard' ); ?>
				<input type="hidden" name="wpta[ai_layout]" value="<?php echo (int) $s['ai_layout']; ?>">
				<input type="hidden" name="wpta[ai_cleanup]" value="<?php echo (int) $s['ai_cleanup']; ?>">

				<div class="wpta-wizard-head">
					<span class="wpta-eyebrow"><?php echo self::icon( 'sparkle', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>Setup · step <span class="wpta-step-n">1</span> of 3</span>
					<?php foreach ( $titles as $n => $t ) : ?>
						<div class="wpta-step-title" data-for="<?php echo (int) $n; ?>" <?php echo 1 === $n ? '' : 'hidden'; ?>>
							<h2><?php echo esc_html( $t[0] ); ?></h2>
							<p><?php echo esc_html( $t[1] ); ?></p>
						</div>
					<?php endforeach; ?>
					<ol class="wpta-steps">
						<li class="is-current" data-for="1"><span class="n">1</span>Choose AI</li>
						<li data-for="2"><span class="n">2</span>Connect GitHub</li>
						<li data-for="3"><span class="n">3</span>Convert</li>
					</ol>
				</div>

				<div class="wpta-pane" data-pane="1">
					<?php self::provider_cards( $s, $cg, false ); ?>
					<?php self::provider_panels( $s, $cg ); ?>
				</div>

				<div class="wpta-pane" data-pane="2" hidden>
					<section class="wpta-card wpta-split">
						<div class="wpta-glow"><span class="wpta-tile is-xl is-brand"><?php echo self::logo( 'github', 44 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span></div>
						<div class="wpta-split-body">
							<?php self::github_box( $s ); ?>
							<?php self::repo_fields( $s ); ?>
						</div>
					</section>
				</div>

				<div class="wpta-pane" data-pane="3" hidden>
					<div class="wpta-two">
						<section class="wpta-card wpta-rows" aria-label="Summary">
							<div class="wpta-row"><span class="wpta-tile"><?php echo self::icon( 'file' ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span><span><strong><?php echo esc_html( $counts ? implode( ' · ', $counts ) : 'Nothing published yet' ); ?></strong><small>Content, with the same URLs</small></span></div>
							<div class="wpta-row"><span class="wpta-summary-tile" data-summary-tile="ai"><?php echo self::provider_tile( $s['provider'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span><span><strong data-summary="ai"><?php echo esc_html( self::ai_summary( $s, WPTA_LLM::from_settings() ) ); ?></strong><small>Rebuilds the layout and cleans up shortcodes</small></span></div>
							<div class="wpta-row"><span class="wpta-tile is-brand"><?php echo $gh ? self::logo( 'github' ) : self::icon( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span><span><strong data-summary="output"><?php echo esc_html( self::output_summary( $s ) ); ?></strong><small>Where the Astro project goes</small></span></div>
						</section>
						<section class="wpta-pills" aria-label="What to build">
							<?php self::build_toggles( $s ); ?>
							<?php self::auto_toggle( $s, $gh, 'Sync after I publish' ); ?>
						</section>
					</div>
				</div>

				<div class="wpta-wizard-nav">
					<button type="button" class="wpta-btn" data-wizard="back" hidden>Back</button>
					<span class="wpta-muted" data-wizard="hint">Both AI and GitHub are optional.</span>
					<span class="wpta-wizard-go">
						<button type="button" class="wpta-btn is-ghost" data-wizard="skip" hidden>Skip — download a zip</button>
						<button type="button" class="wpta-btn is-primary" data-wizard="next">Continue</button>
					</span>
				</div>
				<p class="wpta-msg" data-wizard="msg" aria-live="polite"></p>
			</form>

			<?php self::run_view( $gh ); ?>
			<?php self::footer(); ?>
		</div>
		<?php
	}

	/* ------------------------------------------------------------ Providers */

	private static function provider_cards( array $s, array $cg, bool $compact ): void {
		$cards = array(
			'local'      => array( 'Gemma, Llama or Qwen on a server you run. Nothing leaves it.', 'Free · private', 'is-good' ),
			'chatgpt'    => array( 'Sign in with ChatGPT and use the Plus or Pro plan you have.', 'No API key', '' ),
			'claude'     => array( 'Convert here, then Claude Code polishes the site on your Pro or Max plan.', 'Via Claude Code', '' ),
			'openrouter' => array( 'Gemma, Claude, GPT and more. Connect in one click, buy credits here.', 'Free models + credits', 'is-teal' ),
			'none'       => array( 'A clean default layout. Polish it later with any agent.', 'Always works', '' ),
		);
		if ( $cg['connected'] ) {
			$cards['chatgpt'][1] = 'Signed in';
			$cards['chatgpt'][2] = 'is-good';
		}
		if ( '' !== ( $s['keys']['openrouter'] ?? '' ) ) {
			$cards['openrouter'][1] = 'Connected';
			$cards['openrouter'][2] = 'is-good';
		}
		$all = WPTA_Settings::providers();
		?>
		<div class="wpta-provs<?php echo $compact ? ' is-compact' : ''; ?>" role="radiogroup" aria-label="AI provider">
			<?php foreach ( $cards as $id => $c ) : ?>
				<?php
				if ( ! isset( $all[ $id ] ) ) {
					continue;
				}
				?>
				<label class="wpta-prov<?php echo $s['provider'] === $id ? ' is-selected' : ''; ?>">
					<input type="radio" class="wpta-sr" name="wpta[provider]" value="<?php echo esc_attr( $id ); ?>" <?php checked( $s['provider'], $id ); ?>>
					<span class="wpta-prov-tick" aria-hidden="true"><?php echo self::icon( 'check', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span>
					<span class="wpta-prov-art"><?php echo self::provider_tile( $id, $compact ? 20 : 26, 'wpta-tile' . ( $compact ? '' : ' is-lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span>
					<span class="wpta-prov-text">
						<strong><?php echo esc_html( $all[ $id ]['label'] ); ?></strong>
						<span><?php echo esc_html( $c[0] ); ?></span>
						<span class="wpta-badge <?php echo esc_attr( $c[2] ); ?>"><?php echo esc_html( $c[1] ); ?></span>
					</span>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function provider_panels( array $s, array $cg ): void {
		foreach ( WPTA_Settings::providers() as $id => $p ) {
			$r = WPTA_Settings::provider( $id );
			?>
			<section class="wpta-card wpta-panel" data-provider="<?php echo esc_attr( $id ); ?>" aria-label="<?php echo esc_attr( $p['label'] ); ?>" <?php echo $s['provider'] === $id ? '' : 'hidden'; ?>>
				<?php echo self::provider_tile( $id ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>
				<div class="wpta-panel-body">
					<?php
					if ( 'openrouter' === $id ) {
						self::openrouter_box( $r );
					} elseif ( 'chatgpt' === $id ) {
						self::chatgpt_box( $cg );
					} elseif ( 'claude' === $id ) {
						self::claude_box( $s );
					} else {
						echo '<p class="wpta-note">' . esc_html( $p['note'] ) . '</p>';
					}
					if ( 'none' !== $p['kind'] ) {
						self::provider_fields( $id, $p, $r );
					}
					?>
				</div>
			</section>
			<?php
		}
	}

	private static function provider_fields( string $id, array $p, array $r ): void {
		?>
		<div class="wpta-fields">
			<?php if ( ! empty( $p['server'] ) ) : ?>
				<div class="wpta-field">
					<label for="wpta-base-<?php echo esc_attr( $id ); ?>">Server URL</label>
					<input id="wpta-base-<?php echo esc_attr( $id ); ?>" class="wpta-in wpta-base" type="url" name="wpta[base_urls][<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $r['base_url'] ); ?>" placeholder="<?php echo esc_attr( $p['base_url'] ); ?>">
					<small>Ollama (<code>http://localhost:11434/v1</code>), LM Studio (<code>http://localhost:1234/v1</code>), llama.cpp or vLLM. WordPress must be able to reach it. <?php echo self::doc_link( 'local-models', 'Run a local model' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in doc_link(). ?></small>
				</div>
			<?php endif; ?>
			<?php if ( $p['key'] ) : ?>
				<details class="wpta-field wpta-keyfield" <?php echo '' === $r['api_key'] ? '' : 'open'; ?>>
					<summary>API key</summary>
					<input id="wpta-key-<?php echo esc_attr( $id ); ?>" class="wpta-in wpta-key" type="password" autocomplete="off" name="wpta[keys][<?php echo esc_attr( $id ); ?>]" value="" placeholder="<?php echo esc_attr( WPTA_Secrets::mask( $r['api_key'] ) ?: 'Set by Connect, or paste a key' ); ?>" aria-label="API key">
					<?php if ( $r['api_key'] ) : ?>
						<label class="wpta-check"><input type="checkbox" name="wpta[clear_keys][<?php echo esc_attr( $id ); ?>]" value="1"> Remove</label>
					<?php endif; ?>
					<small>Stored encrypted. Only sent to <?php echo esc_html( (string) wp_parse_url( $p['base_url'], PHP_URL_HOST ) ); ?>.</small>
				</details>
			<?php endif; ?>
			<div class="wpta-field">
				<label for="wpta-model-<?php echo esc_attr( $id ); ?>">Model</label>
				<span class="wpta-inline">
					<input id="wpta-model-<?php echo esc_attr( $id ); ?>" class="wpta-in wpta-model" type="text" list="wpta-models-<?php echo esc_attr( $id ); ?>" name="wpta[models][<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $r['model'] ); ?>" placeholder="<?php echo esc_attr( $p['model'] ?: 'Load the list or type a model ID' ); ?>">
					<datalist id="wpta-models-<?php echo esc_attr( $id ); ?>"></datalist>
					<button type="button" class="wpta-btn wpta-load-models">Load models</button>
					<button type="button" class="wpta-btn wpta-test">Test</button>
				</span>
				<p class="wpta-msg" aria-live="polite"></p>
			</div>
		</div>
		<?php
	}

	private static function openrouter_box( array $r ): void {
		?>
		<div class="wpta-connect" data-connect="openrouter">
			<?php if ( '' !== $r['api_key'] ) : ?>
				<div class="wpta-balance-row">
					<span class="wpta-badge is-good"><?php echo self::icon( 'check', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>Connected</span>
					<strong class="wpta-big wpta-balance">checking credits…</strong>
				</div>
				<p class="wpta-actions">
					<a class="wpta-btn is-primary wpta-buy" href="<?php echo esc_url( WPTA_Connect::OPENROUTER_CREDITS ); ?>" target="_blank" rel="noopener"><?php echo self::logo( 'openrouter', 16, false, true ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>Buy credits ↗</a>
					<button type="button" class="wpta-btn wpta-balance-refresh">Refresh balance</button>
					<button type="button" class="wpta-link wpta-or-connect">Reconnect</button>
				</p>
				<small>Credits are bought on OpenRouter and show up here. Models marked “free” cost nothing. <?php echo self::doc_link( 'openrouter', 'Credits and free models' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in doc_link(). ?></small>
			<?php else : ?>
				<p class="wpta-actions">
					<button type="button" class="wpta-btn is-primary wpta-or-connect"><?php echo self::logo( 'openrouter', 16, false, true ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>Connect OpenRouter</button>
					<span class="wpta-muted">Opens openrouter.ai, then comes back here.</span>
				</p>
				<small>No account yet? Create one while connecting. <?php echo self::doc_link( 'openrouter', 'Costs and free models' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in doc_link(). ?></small>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function chatgpt_box( array $cg ): void {
		?>
		<div class="wpta-connect" data-connect="chatgpt">
			<div class="wpta-cg-on" <?php echo $cg['connected'] ? '' : 'hidden'; ?>>
				<p class="wpta-actions">
					<span class="wpta-badge is-good"><?php echo self::icon( 'check', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>Signed in<span class="wpta-cg-email"><?php echo $cg['email'] ? ' as ' . esc_html( $cg['email'] ) : ''; ?></span></span>
					<a class="wpta-btn" href="<?php echo esc_url( $cg['usage_url'] ); ?>" target="_blank" rel="noopener">Usage &amp; limits ↗</a>
					<button type="button" class="wpta-link wpta-cg-signout">Sign out</button>
				</p>
			</div>
			<div class="wpta-cg-off" <?php echo $cg['connected'] ? 'hidden' : ''; ?>>
				<div class="wpta-minis">
					<div class="wpta-mini"><span class="num">1</span><span><strong>Approve <?php echo esc_html( WPTA_Settings::brand()['name'] ); ?></strong><small>ChatGPT opens in a new tab.</small></span></div>
					<div class="wpta-mini"><span class="num">2</span><span><strong>Copy the address</strong><small>The tab lands on a page that can’t load (<code>127.0.0.1:1455</code>). That’s expected.</small></span></div>
					<div class="wpta-mini"><span class="num">3</span><span><strong>Paste it here</strong><small>WordPress finishes the sign-in.</small></span></div>
				</div>
				<p class="wpta-actions">
					<button type="button" class="wpta-btn is-primary wpta-cg-start"><?php echo self::logo( 'openai', 16, true ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>Sign in with ChatGPT</button>
				</p>
				<div class="wpta-cg-paste wpta-inline" hidden>
					<input type="url" class="wpta-in wpta-cg-callback" aria-label="Address from the ChatGPT tab" placeholder="http://127.0.0.1:1455/auth/callback?code=…">
					<button type="button" class="wpta-btn is-primary wpta-cg-finish">Finish sign-in</button>
				</div>
				<small>Requires ChatGPT Plus or Pro. Usage counts toward your plan’s limits. <?php echo self::doc_link( 'chatgpt', 'How sign-in works' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in doc_link(). ?></small>
			</div>
		</div>
		<?php
	}

	private static function claude_box( array $s ): void {
		$repo = $s['github_login'] ? 'https://github.com/' . $s['github_login'] . '/' . $s['repo_name'] : 'https://github.com/you/' . $s['repo_name'];
		?>
		<div class="wpta-minis">
			<div class="wpta-mini"><span class="num">1</span><span><strong>Convert here</strong><small>With the default layout, at no AI cost in WordPress.</small></span></div>
			<div class="wpta-mini"><span class="num">2</span><span><strong>Open the repo in Claude Code</strong><small>It ships with WP2Astro skills.</small></span></div>
			<div class="wpta-mini"><span class="num">3</span><span><strong>Let Claude polish it</strong><small>Your next sync keeps every change it makes.</small></span></div>
		</div>
		<pre class="wpta-code">git clone <?php echo esc_html( $repo ); ?>

cd <?php echo esc_html( $s['repo_name'] ); ?> &amp;&amp; claude "/wp2astro-polish-layout"</pre>
		<small>Claude plans only work in Claude’s own apps, so the polishing happens in Claude Code, on your computer. <?php echo self::doc_link( 'claude-code', 'Finish the site with Claude Code' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in doc_link(). ?></small>
		<?php
	}

	/* --------------------------------------------------------------- GitHub */

	private static function github_box( array $s ): void {
		$connected = '' !== $s['github_token'];
		$oauth     = '' !== WPTA_Connect::github_client_id();
		?>
		<div class="wpta-connect" data-connect="github">
			<?php if ( $connected ) : ?>
				<p class="wpta-actions">
					<span class="wpta-badge is-good"><?php echo self::icon( 'check', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>Connected<?php echo $s['github_login'] ? ' as @' . esc_html( $s['github_login'] ) : ''; ?></span>
					<span class="wpta-muted"><?php echo 'oauth' === $s['github_auth'] ? 'with the GitHub app' : 'with an access token'; ?></span>
				</p>
				<p class="wpta-actions">
					<button type="button" class="wpta-btn wpta-gh-check">Check repo</button>
					<button type="button" class="wpta-link wpta-gh-disconnect">Disconnect</button>
				</p>
				<p class="wpta-msg wpta-gh-status" aria-live="polite"></p>
			<?php else : ?>
				<?php if ( $oauth ) : ?>
					<p class="wpta-actions"><button type="button" class="wpta-btn is-primary wpta-gh-start"><?php echo self::logo( 'github', 16, true ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>Connect GitHub</button></p>
					<div class="wpta-gh-device" hidden>
						<strong>Enter this code on GitHub</strong>
						<p class="wpta-actions"><code class="wpta-devcode wpta-gh-code"></code> <button type="button" class="wpta-btn wpta-copy">Copy</button></p>
						<p class="wpta-actions"><a class="wpta-btn is-primary wpta-gh-open" href="https://github.com/login/device" target="_blank" rel="noopener"><?php echo self::logo( 'github', 16, true ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>Open github.com/login/device ↗</a> <span class="wpta-badge wpta-gh-wait">Waiting for approval…</span></p>
					</div>
					<p class="wpta-msg" aria-live="polite"></p>
				<?php endif; ?>
				<details class="wpta-token" <?php echo $oauth ? '' : 'open'; ?>>
					<summary><?php echo $oauth ? 'Use a personal access token instead' : 'Connect with a personal access token'; ?></summary>
					<input type="password" class="wpta-in" autocomplete="off" name="wpta[github_token]" value="" placeholder="github_pat_…" aria-label="Personal access token">
					<small><a href="https://github.com/settings/personal-access-tokens/new" target="_blank" rel="noopener">Create a fine-grained token ↗</a> with <strong>Administration</strong> and <strong>Contents</strong> set to read &amp; write. Stored encrypted. <?php echo self::doc_link( 'github', 'Step by step' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in doc_link(). ?></small>
				</details>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function repo_fields( array $s ): void {
		?>
		<div class="wpta-field">
			<label for="wpta-repo">Repository</label>
			<input id="wpta-repo" class="wpta-in" type="text" name="wpta[repo_name]" value="<?php echo esc_attr( $s['repo_name'] ); ?>">
			<small>Created on the first run if it doesn’t exist.</small>
		</div>
		<?php self::toggle( 'repo_private', (bool) $s['repo_private'], 'lock', 'Private repository' ); ?>
		<?php
	}

	/* ------------------------------------------------------------- Toggles */

	private static function build_toggles( array $s ): void {
		$checks = array(
			'seo'       => array( 'search', 'SEO titles and descriptions', 'From Yoast, Rank Math or All in One SEO, plus canonical and Open Graph tags.' ),
			'feeds'     => array( 'rss', 'RSS feed, sitemap and robots.txt', 'Old /feed/ and /wp-sitemap.xml links redirect to the new ones.' ),
			'search'    => array( 'activity', 'Site search', 'Static full-text search with Pagefind; old ?s= links land on it.' ),
			'cpts'      => array( 'folder', 'Custom post types', 'Public custom post types get their own folders and archives.' ),
			'agent_kit' => array( 'terminal', 'Agent kit', 'AGENTS.md, CLAUDE.md and Claude Code skills so an AI agent can finish the site.' ),
			'fields'    => array( 'tag', 'Custom fields', 'Public post meta and ACF fields in each entry’s frontmatter. Off by default: fields can hold private data.' ),
		);
		foreach ( $checks as $k => $c ) {
			self::toggle( $k, (bool) $s[ $k ], $c[0], $c[1], $c[2] );
		}
	}

	private static function auto_toggle( array $s, bool $gh, string $label ): void {
		$attrs = $gh || $s['auto_sync'] ? array() : array( 'disabled' => true );
		self::toggle( 'auto_sync', (bool) $s['auto_sync'], 'sync', $label, $gh ? 'A couple of minutes after you publish, update or delete a post. Files edited in the repo are kept.' : 'Connect GitHub to turn this on.', $attrs );
	}

	/* --------------------------------------------------------- Progress view */

	private static function run_view( bool $gh ): void {
		$steps = array(
			'init'          => array( 'file', 'Read the theme and settings' ),
			'content'       => array( 'file', 'Convert entries to Markdown' ),
			'media'         => array( 'image', 'Copy media files' ),
			'ai_layout'     => array( 'sparkle', 'Rebuild the layout with AI' ),
			'ai_cleanup'    => array( 'wand', 'Clean up dead shortcodes' ),
			'package'       => array( 'package', 'Package the project' ),
			'zip'           => array( 'download', 'Build the zip' ),
			'github'        => array( 'github', 'Compare with GitHub' ),
			'github_upload' => array( 'upload', 'Upload changed files' ),
			'github_commit' => array( 'commit', 'Commit' ),
		);
		?>
		<section class="wpta-card wpta-run" id="wpta-run" hidden aria-labelledby="wpta-run-title">
			<div class="wpta-run-head">
				<div class="wpta-glow wpta-ring-wrap">
					<svg class="wpta-ring" width="160" height="160" viewBox="0 0 160 160" aria-hidden="true">
						<circle cx="80" cy="80" r="64" fill="#fff" stroke="#e6f6f4" stroke-width="12"/>
						<circle class="wpta-ring-bar" cx="80" cy="80" r="64" fill="none" stroke="#0f766e" stroke-width="12" stroke-linecap="round" stroke-dasharray="402.1" stroke-dashoffset="402.1" transform="rotate(-90 80 80)"/>
					</svg>
					<span class="wpta-ring-pct"><strong>0%</strong><small class="wpta-ring-label">starting</small></span>
				</div>
				<div class="wpta-run-text">
					<span class="wpta-badge is-amber wpta-run-busy" hidden>Provider busy — retrying</span>
					<h2 id="wpta-run-title"><?php echo $gh ? 'Syncing with GitHub…' : 'Converting your site…'; ?></h2>
					<p class="wpta-phase" aria-live="polite">Starting…</p>
					<p class="wpta-actions">
						<button type="button" class="wpta-btn wpta-stop"><?php echo self::icon( 'stop', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>Stop</button>
					</p>
					<div id="wpta-result" hidden></div>
				</div>
			</div>
			<h3 class="wpta-sub">Steps</h3>
			<ol class="wpta-phases">
				<?php foreach ( $steps as $id => $st ) : ?>
					<li data-phase="<?php echo esc_attr( $id ); ?>" <?php echo ! $gh && 0 === strpos( $id, 'github' ) ? 'hidden' : ''; ?>>
						<span class="wpta-tile is-sm"><?php echo 'github' === $st[0] ? self::logo( 'github', 16, true ) : self::icon( $st[0], 16 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span>
						<span class="wpta-phase-label"><?php echo esc_html( $st[1] ); ?></span>
						<span class="wpta-phase-time"></span>
					</li>
				<?php endforeach; ?>
			</ol>
			<details class="wpta-logbox"><summary>Log</summary><pre id="wpta-log"></pre></details>
		</section>
		<?php
	}

	/* ------------------------------------------------------------ Dashboard */

	private static function render_dashboard( array $s, array $cg, ?array $notice ): void {
		$brand = WPTA_Settings::brand();
		$gh    = '' !== $s['github_token'];
		$runs  = WPTA_Sync::runs();
		$hero  = self::hero_state( $runs, WPTA_Sync::changed(), $gh, (bool) $s['auto_sync'], WPTA_Sync::running() );
		$tabs  = array(
			'overview' => array( 'home', 'Overview' ),
			'activity' => array( 'activity', 'Activity' ),
			'settings' => array( 'sliders', 'Settings' ),
		);
		?>
		<div class="wrap wpta wpta-dash">
			<header class="wpta-top">
				<?php self::brand_block( true ); ?>
				<nav class="wpta-tabs" aria-label="<?php echo esc_attr( $brand['name'] ); ?> sections">
					<?php foreach ( $tabs as $id => $t ) : ?>
						<a href="#<?php echo esc_attr( $id ); ?>" data-tab="<?php echo esc_attr( $id ); ?>" <?php echo 'overview' === $id ? 'aria-current="page"' : ''; ?>><?php echo self::icon( $t[0], 16 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?><?php echo esc_html( $t[1] ); ?></a>
					<?php endforeach; ?>
				</nav>
				<button type="button" class="wpta-btn is-quiet wpta-help"><?php echo self::icon( 'help', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>Help</button>
			</header>
			<h1 class="screen-reader-text"><?php echo esc_html( $brand['name'] ); ?></h1>
			<hr class="wp-header-end">
			<?php self::notice( $notice ); ?>

			<div class="wpta-tabpanel" data-panel="overview">
				<?php self::run_view( $gh ); ?>
				<div class="wpta-overview">
					<?php self::hero( $s, $hero, $gh ); ?>
					<?php self::stats( $s, $hero['last'] ); ?>
					<div class="wpta-cols">
						<?php self::timeline( $runs ); ?>
						<div class="wpta-stack">
							<?php self::connections( $s, $cg, $gh, $hero['last'] ); ?>
							<?php self::claude_card( $s, $gh, $hero['last'] ); ?>
							<?php self::deploy_card(); ?>
						</div>
					</div>
				</div>
			</div>

			<div class="wpta-tabpanel" data-panel="activity" hidden>
				<section class="wpta-card wpta-pad" id="history">
					<div class="wpta-head"><span class="wpta-tile"><?php echo self::icon( 'activity' ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span><div><h2>Recent runs</h2><small>The last <?php echo (int) WPTA_Sync::MAX_RUNS; ?> runs: the button, auto-sync and WP-CLI.</small></div></div>
					<div class="wpta-table-wrap">
						<table class="wpta-runs">
							<thead><tr><th>When</th><th>How</th><th>Result</th><th><span class="screen-reader-text">Links</span></th></tr></thead>
							<tbody id="wpta-runs">
								<?php self::history_rows(); ?>
							</tbody>
						</table>
					</div>
				</section>
			</div>

			<div class="wpta-tabpanel" data-panel="settings" hidden>
				<?php self::settings( $s, $cg, $gh ); ?>
			</div>

			<?php self::footer(); ?>
		</div>
		<?php
	}

	private static function hero( array $s, array $hero, bool $gh ): void {
		$last  = $hero['last'];
		$repo  = self::repo_url( $s, $last );
		$zip   = self::zip_url( $last );
		$badge = array( 'running' => 'is-teal', 'failed' => 'is-bad', 'new' => '', 'behind' => 'is-amber', 'ok' => 'is-good' )[ $hero['state'] ];
		$meta  = array();
		if ( $last ) {
			$meta[] = ( empty( $last['repo'] ) ? 'Last converted ' : 'Last synced ' ) . self::ago( (int) ( $last['finished'] ?? $last['started'] ?? 0 ) );
			if ( ! empty( $last['commit'] ) ) {
				$meta[] = '<a href="' . esc_url( $last['commit'] ) . '" target="_blank" rel="noopener">commit ' . esc_html( substr( basename( (string) $last['commit'] ), 0, 7 ) ) . '</a>';
			}
			if ( ! empty( $last['files'] ) ) {
				$meta[] = (int) $last['files'] . ' files';
			}
			if ( ! empty( $last['finished'] ) && ! empty( $last['started'] ) ) {
				$meta[] = 'took ' . max( 1, (int) $last['finished'] - (int) $last['started'] ) . ' s';
			}
		}
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		?>
		<section class="wpta-card wpta-hero" aria-labelledby="wpta-hero-title">
			<div class="wpta-hero-main">
				<span class="wpta-badge <?php echo esc_attr( $badge ); ?>">● <?php echo esc_html( $hero['badge'] ); ?></span>
				<h2 id="wpta-hero-title"><?php echo esc_html( $hero['title'] ); ?></h2>
				<?php if ( '' !== $hero['text'] ) : ?><p><?php echo esc_html( $hero['text'] ); ?></p><?php endif; ?>
				<?php if ( $meta ) : ?><p class="wpta-meta"><?php echo implode( ' · ', $meta ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?></p><?php endif; ?>
				<p class="wpta-actions">
					<?php // Never disabled: while a run holds the lock, admin.js asks before starting anyway. ?>
					<button type="button" class="wpta-btn is-primary" id="wpta-start" <?php echo $hero['restart'] ? 'data-force="1"' : ''; ?>><?php echo self::icon( 'sync', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?><span><?php echo esc_html( $hero['restart'] ? 'Start again' : ( $gh && $last ? 'Sync now' : 'Convert to Astro' ) ); ?></span></button>
					<?php if ( $repo ) : ?>
						<a class="wpta-btn" href="<?php echo esc_url( $repo ); ?>" target="_blank" rel="noopener"><?php echo self::logo( 'github', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>Open repo</a>
					<?php endif; ?>
					<?php if ( $zip ) : ?>
						<a class="wpta-btn" href="<?php echo esc_url( $zip ); ?>"><?php echo self::icon( 'download', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>Download zip</a>
					<?php endif; ?>
				</p>
			</div>
			<div class="wpta-glow wpta-flow" aria-hidden="true">
				<span class="wpta-flow-node"><span class="wpta-tile is-lg is-brand"><span class="dashicons dashicons-wordpress"></span></span><small><?php echo esc_html( $host ); ?></small></span>
				<?php echo self::icon( 'arrow', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>
				<span class="wpta-flow-node"><span class="wpta-tile is-lg is-brand"><img src="<?php echo esc_url( WPTA_Settings::brand()['logo'] ); ?>" alt="" width="30" height="30"></span><small>Astro</small></span>
				<?php echo self::icon( 'arrow', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>
				<span class="wpta-flow-node"><span class="wpta-tile is-lg is-brand"><?php echo $gh ? self::logo( 'github', 26 ) : self::icon( 'download', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span><small><?php echo esc_html( $gh ? $s['repo_name'] : 'zip' ); ?></small></span>
			</div>
		</section>
		<?php
	}

	private static function stats( array $s, ?array $last ): void {
		$has = fn( string $k ) => $last && array_key_exists( $k, $last );
		$num = fn( string $k ) => $has( $k ) ? number_format_i18n( (int) $last[ $k ] ) : '—';
		$flagged = $has( 'flagged' ) ? (int) $last['flagged'] : null;
		$cleaned = $has( 'cleaned' ) ? (int) $last['cleaned'] : 0;
		$stats   = array(
			array( 'file', $num( 'entries' ), 'Entries converted', $has( 'media' ) ? 'Plus ' . (int) $last['media'] . ' media files' : 'From the last run' ),
			array( 'redirect', $num( 'redirects' ), 'Old URLs redirect', 'Slugs, ?p= and /feed/ links' ),
			array( 'tag', $has( 'seo' ) ? ( '' !== $last['seo'] ? $last['seo'] : ( $s['seo'] ? 'Post titles' : 'Off' ) ) : '—', 'SEO titles', $has( 'seo' ) && '' !== $last['seo'] ? 'Titles and descriptions kept' : 'No SEO plugin found' ),
			array( 'wand', null === $flagged ? '—' : ( $flagged ? $cleaned . ' / ' . $flagged : '0' ), 'Dead shortcodes cleaned', null === $flagged ? 'From the last run' : ( ! $flagged ? 'None found' : ( $cleaned < $flagged ? ( $flagged - $cleaned ) . ' left — see the report' : 'All replaced' ) ) ),
		);
		?>
		<div class="wpta-stats">
			<?php foreach ( $stats as $st ) : ?>
				<div class="wpta-card wpta-stat">
					<span class="wpta-tile"><?php echo self::icon( $st[0] ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span>
					<strong class="<?php echo strlen( (string) $st[1] ) > 9 ? 'is-long' : ''; ?>"><?php echo esc_html( (string) $st[1] ); ?></strong>
					<span><?php echo esc_html( $st[2] ); ?></span>
					<small><?php echo esc_html( $st[3] ); ?></small>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function timeline( array $runs ): void {
		$how = array( 'manual' => 'Button', 'auto' => 'Auto-sync', 'cli' => 'WP-CLI' );
		?>
		<section class="wpta-card wpta-pad" aria-labelledby="wpta-act-title">
			<div class="wpta-head"><h2 id="wpta-act-title">Recent activity</h2><a href="#activity" data-tab-link="activity">See all</a></div>
			<?php if ( ! $runs ) : ?>
				<p class="wpta-muted">No runs yet.</p>
			<?php endif; ?>
			<ol class="wpta-timeline">
				<?php foreach ( array_slice( $runs, 0, 5 ) as $r ) : ?>
					<?php
					$item = self::run_item( $r );
					$tile = array(
						'error'   => '<span class="wpta-tile is-bad">' . self::icon( 'alert' ) . '</span>',
						'running' => '<span class="wpta-tile">' . self::icon( 'clock' ) . '</span>',
						'commit'  => '<span class="wpta-tile is-brand">' . self::logo( 'github' ) . '</span>',
						'noop'    => '<span class="wpta-tile is-muted">' . self::icon( 'check' ) . '</span>',
						'zip'     => '<span class="wpta-tile">' . self::icon( 'download' ) . '</span>',
					)[ $item['kind'] ];
					?>
					<li>
						<?php echo $tile; // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>
						<div>
							<strong><?php echo esc_html( $item['title'] ); ?></strong>
							<?php if ( $item['text'] ) : ?><small><?php echo esc_html( mb_substr( $item['text'], 0, 160 ) ); ?></small><?php endif; ?>
						</div>
						<div class="wpta-when">
							<span><?php echo esc_html( self::ago( (int) ( $r['started'] ?? 0 ) ) ); ?></span>
							<?php if ( ! empty( $r['commit'] ) ) : ?>
								<a href="<?php echo esc_url( $r['commit'] ); ?>" target="_blank" rel="noopener">Commit ↗</a>
							<?php else : ?>
								<small><?php echo esc_html( $how[ $r['trigger'] ?? '' ] ?? '' ); ?></small>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
		<?php
	}

	private static function connections( array $s, array $cg, bool $gh, ?array $last ): void {
		$p    = WPTA_Settings::provider();
		$id   = $p['id'];
		$repo = self::repo_url( $s, $last );
		if ( 'openrouter' === $id && '' !== $p['api_key'] ) {
			$sub = '<span class="wpta-balance">checking credits…</span>';
		} elseif ( 'openrouter' === $id ) {
			$sub = 'Not connected';
		} elseif ( 'chatgpt' === $id ) {
			$sub = $cg['connected'] ? esc_html( 'Signed in' . ( $cg['email'] ? ' as ' . $cg['email'] : '' ) ) : 'Signed out';
		} elseif ( 'local' === $id ) {
			$sub = esc_html( $p['base_url'] );
		} elseif ( 'claude' === $id ) {
			$sub = 'Default layout here, polish in Claude Code';
		} else {
			$sub = 'Default layout';
		}
		$title = $p['label'] . ( 'none' !== $p['kind'] && $p['model'] ? ' · ' . $p['model'] : '' );
		?>
		<section class="wpta-card wpta-pad" aria-labelledby="wpta-con-title">
			<h2 id="wpta-con-title">Connections</h2>
			<div class="wpta-kv">
				<?php echo self::provider_tile( $id ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>
				<span class="wpta-kv-text"><strong><?php echo esc_html( $title ); ?></strong><small><?php echo $sub; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?></small></span>
				<?php if ( 'openrouter' === $id && '' !== $p['api_key'] ) : ?>
					<a class="wpta-btn is-sm" href="<?php echo esc_url( WPTA_Connect::OPENROUTER_CREDITS ); ?>" target="_blank" rel="noopener">Buy credits</a>
				<?php else : ?>
					<a class="wpta-btn is-sm" href="#ai">Change</a>
				<?php endif; ?>
			</div>
			<div class="wpta-kv">
				<span class="wpta-tile is-brand"><?php echo self::logo( 'github' ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span>
				<span class="wpta-kv-text"><strong>GitHub</strong><small><?php echo esc_html( $gh ? self::output_summary( $s ) : 'Not connected — you get a zip' ); ?></small></span>
				<?php if ( $repo ) : ?>
					<a class="wpta-btn is-sm" href="<?php echo esc_url( $repo ); ?>" target="_blank" rel="noopener">Open</a>
				<?php elseif ( ! $gh ) : ?>
					<a class="wpta-btn is-sm" href="#github">Connect</a>
				<?php endif; ?>
			</div>
			<label class="wpta-kv" for="wpta-auto-now">
				<span class="wpta-tile"><?php echo self::icon( 'sync' ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span>
				<span class="wpta-kv-text"><strong>Auto-sync</strong><small><?php echo $gh ? 'Syncs a couple of minutes after you publish' : 'Needs GitHub'; ?></small></span>
				<input type="checkbox" class="wpta-switch wpta-auto-now" role="switch" id="wpta-auto-now" <?php checked( $s['auto_sync'] && $gh ); ?> <?php disabled( ! $gh ); ?>>
			</label>
		</section>
		<?php
	}

	private static function claude_card( array $s, bool $gh, ?array $last ): void {
		$repo = self::repo_url( $s, $last );
		$cmd  = $repo
			? "git clone {$repo}.git\ncd {$s['repo_name']} && claude \"/wp2astro-polish-layout\""
			: "# Unzip the download, then in that folder:\nclaude \"/wp2astro-polish-layout\"";
		?>
		<section class="wpta-card wpta-pad" aria-labelledby="wpta-cc-title">
			<div class="wpta-head"><span class="wpta-tile is-brand"><?php echo self::logo( 'claude' ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span><h2 id="wpta-cc-title">Finish it with Claude Code</h2></div>
			<?php if ( $s['agent_kit'] ) : ?>
				<p class="wpta-muted">Open the <?php echo $gh ? 'repo' : 'project'; ?> in Claude Code and polish what the conversion couldn’t, on your Claude plan.</p>
				<pre class="wpta-code" id="wpta-cc-cmd"><?php echo esc_html( $cmd ); ?></pre>
				<p class="wpta-actions">
					<button type="button" class="wpta-btn is-sm" data-copy="#wpta-cc-cmd"><?php echo self::icon( 'copy', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>Copy</button>
					<?php echo self::doc_link( 'claude-code', 'How it works' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in doc_link(). ?>
				</p>
			<?php else : ?>
				<p class="wpta-muted">Turn on <a href="#output">Agent kit</a> in Settings, then sync. The repo gets the skills Claude Code uses.</p>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function deploy_card(): void {
		$hosts = array( 'FlyWP', 'DigitalOcean', 'Render', 'Netlify', 'Cloudflare Pages', 'Any static host' );
		?>
		<section class="wpta-card wpta-pad" aria-labelledby="wpta-dep-title">
			<div class="wpta-head"><span class="wpta-tile"><?php echo self::icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span><h2 id="wpta-dep-title">Deploy anywhere</h2></div>
			<ul class="wpta-hosts">
				<?php foreach ( $hosts as $h ) : ?>
					<li><?php echo esc_html( $h ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p class="wpta-muted">Build with <code>npm run build</code>, or run <code>npm start</code> to serve the WordPress redirects too. <?php echo self::doc_link( 'deploy', 'Deploy the site' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in doc_link(). ?></p>
		</section>
		<?php
	}

	public static function history_rows(): void {
		$runs = WPTA_Sync::runs();
		if ( ! $runs ) {
			echo '<tr><td colspan="4" class="wpta-muted">No runs yet.</td></tr>';
			return;
		}
		$how   = array( 'manual' => 'Button', 'auto' => 'Auto-sync', 'cli' => 'WP-CLI' );
		$class = array( 'error' => 'is-bad', 'running' => 'is-teal', 'commit' => 'is-good', 'noop' => '', 'zip' => 'is-good' );
		foreach ( $runs as $r ) {
			$item  = self::run_item( $r );
			$res   = '<span class="wpta-badge ' . $class[ $item['kind'] ] . '">' . esc_html( $item['title'] ) . '</span> ' . esc_html( mb_substr( $item['text'], 0, 160 ) );
			$links = '';
			if ( ! empty( $r['commit'] ) ) {
				$links .= '<a href="' . esc_url( $r['commit'] ) . '" target="_blank" rel="noopener">Commit ↗</a> ';
			}
			$zip = self::zip_url( $r );
			if ( $zip ) {
				$links .= '<a href="' . esc_url( $zip ) . '">Zip</a>';
			}
			printf(
				'<tr><td title="%s">%s</td><td>%s</td><td>%s</td><td class="wpta-links">%s</td></tr>',
				esc_attr( wp_date( 'Y-m-d H:i', (int) ( $r['started'] ?? 0 ) ) ),
				esc_html( self::ago( (int) ( $r['started'] ?? 0 ) ) ),
				esc_html( $how[ $r['trigger'] ?? '' ] ?? '' ),
				$res, // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
				$links // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
			);
		}
	}

	/* ---------------------------------------------------------------- Media */

	private static function media_section( array $s ): void {
		$mode  = WPTA_Media::mode( $s );
		$c     = WPTA_Storage::config( $s );
		$modes = array(
			'repo'      => array( 'folder', 'Files over ' . size_format( WPTA_Media::max_bytes( 'repo' ) ) . ' stay on WordPress. Simple, but every image makes the repo bigger.' ),
			'lfs'       => array( 'github', 'Keeps the repo small: GitHub stores the files in Git LFS. Your host must fetch LFS files when it builds, and GitHub’s LFS quotas apply.' ),
			'wordpress' => array( 'globe', 'Nothing is copied: pages link to the files on this WordPress site, so it must stay online.' ),
			'cdn'       => array( 'upload', 'Upload to Amazon S3, Cloudflare R2 or Bunny Storage and link the files from there.' ),
		);
		?>
		<section class="wpta-card wpta-sec" id="media" aria-labelledby="media-h">
			<div class="wpta-head"><span class="wpta-tile"><?php echo self::icon( 'image' ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span><div><h2 id="media-h">Media</h2><small>Where images and other uploads go. Changing it rebuilds every entry on the next run. <?php echo self::doc_link( 'media', 'Choose where media goes' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in doc_link(). ?></small></div></div>
			<div class="wpta-provs is-compact is-described" role="radiogroup" aria-label="Media storage">
				<?php foreach ( WPTA_Media::MODES as $id => $label ) : ?>
					<label class="wpta-prov<?php echo $mode === $id ? ' is-selected' : ''; ?>">
						<input type="radio" class="wpta-sr" name="wpta[media_mode]" value="<?php echo esc_attr( $id ); ?>" <?php checked( $mode, $id ); ?>>
						<span class="wpta-prov-tick" aria-hidden="true"><?php echo self::icon( 'check', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span>
						<span class="wpta-prov-art"><span class="wpta-tile<?php echo 'lfs' === $id ? ' is-brand' : ''; ?>"><?php echo 'lfs' === $id ? self::logo( 'github' ) : self::icon( $modes[ $id ][0] ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span></span>
						<span class="wpta-prov-text"><strong><?php echo esc_html( $label ); ?></strong><span><?php echo esc_html( $modes[ $id ][1] ); ?></span></span>
					</label>
				<?php endforeach; ?>
			</div>
			<div class="wpta-fields wpta-media-cdn" data-media-panel="cdn" <?php echo 'cdn' === $mode ? '' : 'hidden'; ?>>
				<div class="wpta-field">
					<label for="wpta-cdn-provider">Storage</label>
					<select id="wpta-cdn-provider" class="wpta-in" name="wpta[cdn_provider]">
						<?php foreach ( WPTA_Storage::PROVIDERS as $id => $label ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $c['provider'], $id ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="wpta-field">
					<label for="wpta-cdn-endpoint">Endpoint</label>
					<input id="wpta-cdn-endpoint" class="wpta-in" type="url" name="wpta[cdn_endpoint]" value="<?php echo esc_attr( $s['cdn_endpoint'] ); ?>" placeholder="https://s3.us-east-1.amazonaws.com">
					<small>S3: leave empty for AWS, or your provider’s S3 endpoint. R2: <code>https://&lt;account id&gt;.r2.cloudflarestorage.com</code>. Bunny: the storage hostname, <code>https://storage.bunnycdn.com</code> by default.</small>
				</div>
				<div class="wpta-two">
					<div class="wpta-field">
						<label for="wpta-cdn-bucket">Bucket or storage zone</label>
						<input id="wpta-cdn-bucket" class="wpta-in" type="text" name="wpta[cdn_bucket]" value="<?php echo esc_attr( $s['cdn_bucket'] ); ?>">
					</div>
					<div class="wpta-field">
						<label for="wpta-cdn-region">Region</label>
						<input id="wpta-cdn-region" class="wpta-in" type="text" name="wpta[cdn_region]" value="<?php echo esc_attr( $s['cdn_region'] ); ?>" placeholder="us-east-1">
						<small>S3 only. R2 and Bunny ignore it.</small>
					</div>
				</div>
				<div class="wpta-two">
					<div class="wpta-field">
						<label for="wpta-cdn-key-id">Access key ID</label>
						<input id="wpta-cdn-key-id" class="wpta-in" type="text" autocomplete="off" name="wpta[cdn_key_id]" value="<?php echo esc_attr( $s['cdn_key_id'] ); ?>">
						<small>S3 and R2. Bunny doesn’t use one.</small>
					</div>
					<div class="wpta-field">
						<label for="wpta-cdn-secret">Secret access key or password</label>
						<input id="wpta-cdn-secret" class="wpta-in" type="password" autocomplete="off" name="wpta[cdn_secret]" value="" placeholder="<?php echo esc_attr( WPTA_Secrets::mask( $s['cdn_secret'] ) ?: 'Stored encrypted' ); ?>">
						<?php if ( $s['cdn_secret'] ) : ?>
							<label class="wpta-check"><input type="checkbox" name="wpta[clear_cdn_secret]" value="1"> Remove</label>
						<?php endif; ?>
						<small>Bunny: the storage zone’s password (FTP &amp; API access).</small>
					</div>
				</div>
				<div class="wpta-field">
					<label for="wpta-cdn-url">Public URL</label>
					<input id="wpta-cdn-url" class="wpta-in" type="url" name="wpta[cdn_url]" value="<?php echo esc_attr( $s['cdn_url'] ); ?>" placeholder="https://media.example.com">
					<small>Where visitors load the files: your CDN or custom domain, an <code>r2.dev</code> address, or a Bunny pull zone (<code>https://zone.b-cdn.net</code>). Files keep their WordPress path, such as <code>/wp-content/uploads/2024/01/photo.jpg</code>.</small>
				</div>
				<p class="wpta-actions"><button type="button" class="wpta-btn wpta-storage-test">Test storage</button></p>
				<p class="wpta-msg wpta-storage-msg" aria-live="polite"></p>
			</div>
		</section>
		<?php
	}

	/* ------------------------------------------------------------- Settings */

	private static function settings( array $s, array $cg, bool $gh ): void {
		$nav = array(
			'ai'     => array( self::icon( 'sparkle', 18 ), 'AI model' ),
			'github' => array( self::logo( 'github', 18 ), 'GitHub' ),
			'output' => array( self::icon( 'layout', 18 ), 'What to build' ),
			'media'  => array( self::icon( 'image', 18 ), 'Media' ),
			'sync'   => array( self::icon( 'sync', 18 ), 'Keep in sync' ),
			'data'   => array( self::icon( 'database', 18 ), 'Plugin data' ),
		);
		self::form_open( 'wpta-settings' );
		?>
			<nav class="wpta-subnav" aria-label="Settings sections">
				<?php foreach ( $nav as $id => $n ) : ?>
					<a href="#<?php echo esc_attr( $id ); ?>"><?php echo $n[0]; // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?><?php echo esc_html( $n[1] ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="wpta-sections">
				<section class="wpta-card wpta-sec" id="ai" aria-labelledby="ai-h">
					<div class="wpta-head"><span class="wpta-tile"><?php echo self::icon( 'sparkle' ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span><div><h2 id="ai-h">AI model</h2><small>Layout and shortcode cleanup. Results are cached, so a sync doesn’t call the model again unless your theme or those posts change. <?php echo self::doc_link( 'providers', 'Choose an AI model' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in doc_link(). ?></small></div></div>
					<?php self::provider_cards( $s, $cg, true ); ?>
					<?php self::provider_panels( $s, $cg ); ?>
					<div class="wpta-pills">
						<?php self::toggle( 'ai_layout', (bool) $s['ai_layout'], 'layout', 'Rebuild the theme’s header, menu and footer as an Astro layout' ); ?>
						<?php self::toggle( 'ai_cleanup', (bool) $s['ai_cleanup'], 'wand', 'Clean up dead shortcodes in posts' ); ?>
					</div>
				</section>

				<section class="wpta-card wpta-sec" id="github" aria-labelledby="gh-h">
					<div class="wpta-head"><span class="wpta-tile is-brand"><?php echo self::logo( 'github' ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span><div><h2 id="gh-h">GitHub</h2><small>Push the Astro site to a repo, then deploy it anywhere. Without GitHub you get a zip. <?php echo self::doc_link( 'github', 'Connect GitHub' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in doc_link(). ?></small></div></div>
					<?php self::github_box( $s ); ?>
					<?php self::repo_fields( $s ); ?>
				</section>

				<section class="wpta-card wpta-sec" id="output" aria-labelledby="out-h">
					<div class="wpta-head"><span class="wpta-tile"><?php echo self::icon( 'layout' ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span><div><h2 id="out-h">What to build</h2><small>Everything is on by default.</small></div></div>
					<div class="wpta-pills"><?php self::build_toggles( $s ); ?></div>
				</section>

				<?php self::media_section( $s ); ?>

				<section class="wpta-card wpta-sec" id="sync" aria-labelledby="sync-h">
					<div class="wpta-head"><span class="wpta-tile"><?php echo self::icon( 'sync' ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span><div><h2 id="sync-h">Keep in sync</h2><small><?php echo self::doc_link( 'sync', 'What a sync pushes and keeps' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in doc_link(). ?></small></div></div>
					<div class="wpta-pills"><?php self::auto_toggle( $s, $gh, 'Sync to GitHub automatically' ); ?></div>
				</section>

				<section class="wpta-card wpta-sec" id="data" aria-labelledby="data-h">
					<div class="wpta-head"><span class="wpta-tile is-bad"><?php echo self::icon( 'database' ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?></span><div><h2 id="data-h">Plugin data</h2><small>Clear the AI cache, run history and stored zips. Settings and your GitHub repo are not touched.</small></div></div>
					<p class="wpta-actions">
						<button type="button" class="wpta-btn is-danger wpta-clear"><?php echo self::icon( 'trash', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup. ?>Clear data</button>
						<a class="wpta-link" href="<?php echo esc_url( self::setup_url( true ) ); ?>">Run the setup again</a>
					</p>
					<p class="wpta-msg wpta-clear-msg" aria-live="polite"></p>
				</section>
			</div>
			<div class="wpta-savebar">
				<span class="wpta-savebar-text" aria-live="polite">All changes saved</span>
				<button type="button" class="wpta-btn is-quiet wpta-discard" hidden>Discard</button>
				<button type="submit" class="wpta-btn is-primary">Save settings</button>
			</div>
		</form>
		<?php
	}
}
