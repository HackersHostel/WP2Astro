<?php
defined( 'ABSPATH' ) || exit;

/**
 * Connect flows (AJAX): model lists, test call, OpenRouter (OAuth PKCE + credits),
 * Sign in with ChatGPT, and GitHub (device flow or token).
 */
class WPTA_Connect {

	const OPENROUTER_AUTH    = 'https://openrouter.ai/auth';
	const OPENROUTER_API     = 'https://openrouter.ai/api/v1';
	const OPENROUTER_CREDITS = 'https://openrouter.ai/settings/credits';

	/**
	 * Public client ID of the Hackers Hostel GitHub OAuth App (device flow enabled).
	 * Client IDs are not secrets. Override with WP2ASTRO_GITHUB_CLIENT_ID or the `wpta_github_client_id` filter.
	 */
	const GITHUB_CLIENT_ID = '';

	public static function init(): void {
		foreach ( array( 'models', 'test', 'or_start', 'or_balance', 'cg_start', 'cg_finish', 'cg_disconnect', 'gh_start', 'gh_poll', 'gh_check', 'gh_disconnect' ) as $a ) {
			add_action( 'wp_ajax_wpta_' . $a, array( __CLASS__, $a ) );
		}
		add_action( 'admin_init', array( __CLASS__, 'or_callback' ) );
	}

	private static function guard(): void {
		check_ajax_referer( 'wpta' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}
	}

	private static function fail( $err ): void {
		wp_send_json_error( array( 'message' => is_wp_error( $err ) ? $err->get_error_message() : (string) $err ) );
	}

	/** Provider from the request, with unsaved form values (base URL, key, model) layered on top. */
	private static function request_provider(): array {
		$id = sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) );
		$p  = WPTA_Settings::provider( $id );
		foreach ( array( 'base_url' => 'esc_url_raw', 'api_key' => 'sanitize_text_field', 'model' => 'sanitize_text_field' ) as $f => $clean ) {
			$v = trim( (string) wp_unslash( $_POST[ $f ] ?? '' ) );
			if ( '' !== $v ) {
				$p[ $f ] = rtrim( $clean( $v ), '/' );
			}
		}
		return $p;
	}

	/* ---------------------------------------------------------------- Models */

	public static function models(): void {
		self::guard();
		$p    = self::request_provider();
		$list = WPTA_LLM::list_models( $p );
		if ( is_wp_error( $list ) ) {
			self::fail( 'free' === $p['group'] ? $list->get_error_message() . ' — is the ' . $p['label'] . ' server running and reachable from this WordPress server at ' . $p['base_url'] . '?' : $list );
		}
		wp_send_json_success( array( 'models' => $list ) );
	}

	public static function test(): void {
		self::guard();
		$p = self::request_provider();
		if ( '' === $p['model'] ) {
			self::fail( 'Pick a model first.' );
		}
		$t   = microtime( true );
		$out = WPTA_LLM::for_provider( $p )->chat( 'You are a connectivity check.', 'Reply with exactly: OK' );
		if ( is_wp_error( $out ) ) {
			self::fail( $out );
		}
		wp_send_json_success( array( 'message' => sprintf( '%s answered in %.1f s: “%s”', $p['model'], microtime( true ) - $t, mb_substr( trim( $out ), 0, 60 ) ) ) );
	}

	/* ------------------------------------------------------------ OpenRouter */

	private static function b64url( string $bin ): string {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	public static function or_start(): void {
		self::guard();
		$verifier = self::b64url( random_bytes( 48 ) );
		$state    = self::b64url( random_bytes( 18 ) );
		set_transient( 'wpta_or_attempt_' . get_current_user_id(), array( 'verifier' => $verifier, 'state' => $state ), 30 * MINUTE_IN_SECONDS );
		$callback = add_query_arg( array( 'page' => WPTA_Settings::SLUG, 'wpta_openrouter' => 1 ), admin_url( 'admin.php' ) );
		wp_send_json_success(
			array(
				'url' => self::OPENROUTER_AUTH . '?' . http_build_query(
					array(
						'callback_url'          => $callback,
						'code_challenge'        => self::b64url( hash( 'sha256', $verifier, true ) ),
						'code_challenge_method' => 'S256',
						'state'                 => $state,
						'key_label'             => WPTA_Settings::brand()['name'] . ' – ' . wp_parse_url( home_url(), PHP_URL_HOST ),
					),
					'',
					'&',
					PHP_QUERY_RFC3986
				),
			)
		);
	}

	/** OpenRouter redirects back to wp-admin with ?code=…; exchange it for a user-controlled API key. */
	public static function or_callback(): void {
		if ( empty( $_GET['wpta_openrouter'] ) || ( $_GET['page'] ?? '' ) !== WPTA_Settings::SLUG || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$back  = admin_url( 'admin.php?page=' . WPTA_Settings::SLUG );
		$query = wp_unslash( $_GET );
		$flag  = (string) $query['wpta_openrouter'];
		if ( false !== strpos( $flag, '?' ) ) {
			// Tolerate a callback that appended "?code=…" to our URL instead of "&code=…".
			parse_str( substr( $flag, strpos( $flag, '?' ) + 1 ), $extra );
			$query += $extra;
		}
		$attempt = get_transient( 'wpta_or_attempt_' . get_current_user_id() );
		$code    = sanitize_text_field( (string) ( $query['code'] ?? '' ) );
		delete_transient( 'wpta_or_attempt_' . get_current_user_id() );
		// The attempt must be this user's, from the last 30 minutes, with a matching state.
		if ( ! is_array( $attempt ) || '' === $code || ! hash_equals( $attempt['state'], (string) ( $query['state'] ?? '' ) ) ) {
			wp_safe_redirect( add_query_arg( 'wpta_notice', 'or_failed', $back ) );
			exit;
		}
		$verifier = $attempt['verifier'];
		$res  = wp_remote_post(
			self::OPENROUTER_API . '/auth/keys',
			array(
				'timeout' => 30,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'code' => $code, 'code_verifier' => $verifier, 'code_challenge_method' => 'S256' ) ),
			)
		);
		$body = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['key'] ) ) {
			wp_safe_redirect( add_query_arg( 'wpta_notice', 'or_failed', $back ) );
			exit;
		}
		$s                       = WPTA_Settings::get();
		$s['keys']['openrouter'] = (string) $body['key'];
		WPTA_Settings::update( array( 'provider' => 'openrouter', 'keys' => $s['keys'] ) );
		wp_safe_redirect( add_query_arg( 'wpta_notice', 'or_connected', $back ) . '#ai' );
		exit;
	}

	/** Credits left on the connected OpenRouter account. */
	public static function or_balance(): void {
		self::guard();
		$key = WPTA_Settings::provider( 'openrouter' )['api_key'];
		if ( '' === $key ) {
			self::fail( 'OpenRouter is not connected.' );
		}
		$get = fn( $path ) => wp_remote_get( self::OPENROUTER_API . $path, array( 'timeout' => 15, 'headers' => array( 'Authorization' => 'Bearer ' . $key ) ) );

		$out = array( 'buy_url' => self::OPENROUTER_CREDITS );
		$res = $get( '/credits' );
		$c   = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
		if ( isset( $c['data']['total_credits'] ) ) {
			$out['balance'] = round( (float) $c['data']['total_credits'] - (float) $c['data']['total_usage'], 4 );
		}
		$res = $get( '/key' );
		$k   = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! isset( $k['data'] ) && ! isset( $out['balance'] ) ) {
			self::fail( 'OpenRouter did not accept the saved key. Connect again.' );
		}
		if ( isset( $k['data'] ) ) {
			$out['usage']     = (float) ( $k['data']['usage'] ?? 0 );
			$out['key_limit'] = isset( $k['data']['limit_remaining'] ) ? (float) $k['data']['limit_remaining'] : null;
			$out['free_tier'] = ! empty( $k['data']['is_free_tier'] );
		}
		wp_send_json_success( $out );
	}

	/* ------------------------------------------------------------- ChatGPT */

	public static function cg_start(): void {
		self::guard();
		wp_send_json_success( array( 'url' => WPTA_ChatGPT::start() ) );
	}

	public static function cg_finish(): void {
		self::guard();
		$res = WPTA_ChatGPT::finish( (string) wp_unslash( $_POST['callback'] ?? '' ) );
		if ( is_wp_error( $res ) ) {
			self::fail( $res );
		}
		WPTA_Settings::update( array( 'provider' => 'chatgpt' ) );
		$models = WPTA_LLM::list_models( WPTA_Settings::provider( 'chatgpt' ) );
		wp_send_json_success( array( 'status' => WPTA_ChatGPT::status(), 'models' => is_wp_error( $models ) ? array() : $models ) );
	}

	public static function cg_disconnect(): void {
		self::guard();
		$note = WPTA_ChatGPT::disconnect();
		wp_send_json_success( array( 'message' => $note ?: 'Signed out of ChatGPT.' ) );
	}

	/* -------------------------------------------------------------- GitHub */

	public static function github_client_id(): string {
		$id = defined( 'WP2ASTRO_GITHUB_CLIENT_ID' ) ? WP2ASTRO_GITHUB_CLIENT_ID : self::GITHUB_CLIENT_ID;
		return (string) apply_filters( 'wpta_github_client_id', $id );
	}

	private static function github_post( string $path, array $form ) {
		$res = wp_remote_post(
			'https://github.com' . $path,
			array(
				'timeout' => 20,
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => $form,
			)
		);
		return is_wp_error( $res ) ? $res : (array) json_decode( wp_remote_retrieve_body( $res ), true );
	}

	/** Device flow step 1: get a user code to enter at github.com/login/device. */
	public static function gh_start(): void {
		self::guard();
		$client = self::github_client_id();
		if ( '' === $client ) {
			self::fail( 'One-click GitHub connect is not set up in this build. Use a personal access token instead.' );
		}
		$res = self::github_post( '/login/device/code', array( 'client_id' => $client, 'scope' => 'repo' ) );
		if ( is_wp_error( $res ) || empty( $res['device_code'] ) ) {
			self::fail( is_wp_error( $res ) ? $res : 'GitHub: ' . ( $res['error_description'] ?? 'could not start the device flow.' ) );
		}
		set_transient( 'wpta_gh_device_' . get_current_user_id(), $res['device_code'], (int) $res['expires_in'] );
		wp_send_json_success(
			array(
				'user_code'        => $res['user_code'],
				'verification_uri' => $res['verification_uri'],
				'interval'         => (int) ( $res['interval'] ?? 5 ),
				'expires_in'       => (int) $res['expires_in'],
			)
		);
	}

	/** Device flow step 2: poll until the user approves. */
	public static function gh_poll(): void {
		self::guard();
		$device = get_transient( 'wpta_gh_device_' . get_current_user_id() );
		if ( ! $device ) {
			self::fail( 'The GitHub code expired. Click Connect GitHub again.' );
		}
		$res = self::github_post(
			'/login/oauth/access_token',
			array(
				'client_id'   => self::github_client_id(),
				'device_code' => $device,
				'grant_type'  => 'urn:ietf:params:oauth:grant-type:device_code',
			)
		);
		if ( is_wp_error( $res ) ) {
			self::fail( $res );
		}
		if ( empty( $res['access_token'] ) ) {
			$err = $res['error'] ?? 'unknown';
			if ( in_array( $err, array( 'authorization_pending', 'slow_down' ), true ) ) {
				wp_send_json_success( array( 'pending' => true, 'interval' => (int) ( $res['interval'] ?? 0 ) ) );
			}
			delete_transient( 'wpta_gh_device_' . get_current_user_id() );
			self::fail( 'access_denied' === $err ? 'GitHub access was denied.' : 'GitHub: ' . ( $res['error_description'] ?? $err ) );
		}
		delete_transient( 'wpta_gh_device_' . get_current_user_id() );
		$user = ( new WPTA_GitHub( $res['access_token'] ) )->request( 'GET', '/user' );
		WPTA_Settings::update(
			array(
				'github_token' => $res['access_token'],
				'github_auth'  => 'oauth',
				'github_login' => is_wp_error( $user ) ? '' : (string) $user['login'],
			)
		);
		wp_send_json_success( array( 'pending' => false, 'login' => is_wp_error( $user ) ? '' : $user['login'] ) );
	}

	/** Who the token belongs to, and the state of the target repo. */
	public static function gh_check(): void {
		self::guard();
		$s = WPTA_Settings::get();
		if ( '' === $s['github_token'] ) {
			self::fail( 'GitHub is not connected.' );
		}
		$gh   = new WPTA_GitHub( $s['github_token'] );
		$user = $gh->request( 'GET', '/user' );
		if ( is_wp_error( $user ) ) {
			self::fail( $user );
		}
		if ( $user['login'] !== $s['github_login'] ) {
			WPTA_Settings::update( array( 'github_login' => $user['login'] ) );
		}
		$repo = $gh->request( 'GET', '/repos/' . rawurlencode( $user['login'] ) . '/' . rawurlencode( $s['repo_name'] ), null, array( 200, 404 ) );
		wp_send_json_success(
			array(
				'login' => $user['login'],
				'repo'  => is_wp_error( $repo ) || empty( $repo['full_name'] ) ? null : array(
					'name'    => $repo['full_name'],
					'url'     => $repo['html_url'],
					'private' => (bool) $repo['private'],
					'pushed'  => $repo['pushed_at'] ?? '',
				),
			)
		);
	}

	public static function gh_disconnect(): void {
		self::guard();
		$was = WPTA_Settings::get()['github_auth'];
		WPTA_Settings::update( array( 'github_token' => '', 'github_login' => '', 'github_auth' => '' ) );
		wp_send_json_success(
			array(
				'message' => 'oauth' === $was
					? 'Disconnected. To revoke access completely, remove the app at github.com/settings/applications.'
					: 'Disconnected. You can delete the token at github.com/settings/personal-access-tokens.',
			)
		);
	}
}
