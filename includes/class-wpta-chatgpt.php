<?php
defined( 'ABSPATH' ) || exit;

/**
 * Sign in with ChatGPT — ChatGPT plan usage for open-source, self-hosted apps.
 * https://developers.openai.com/siwc/token-sharing-open-source
 *
 * OpenAI only allows a 127.0.0.1 loopback callback. WordPress usually runs on another machine,
 * so the browser lands on an unreachable 127.0.0.1 page and the user pastes that address back
 * into wp-admin. The code exchange then happens here, on the host that owns the tokens.
 */
class WPTA_ChatGPT {

	const OPTION        = 'wpta_chatgpt';
	const AUTHORIZE     = 'https://auth.openai.com/api/accounts/authorize';
	const TOKEN         = 'https://auth.openai.com/api/accounts/oauth/token';
	const DISCOVERY     = 'https://auth.openai.com/.well-known/openid-configuration';
	const ISSUER        = 'https://auth.openai.com';
	const RESOURCE      = 'https://api.openai.com/v1';
	const REDIRECT      = 'http://127.0.0.1:1455/auth/callback';
	const SCOPE         = 'openid profile email offline_access resource.invoke chatgpt.tokens.use.direct';
	const PLAN_SCOPE    = 'chatgpt.tokens.use.direct';
	const USAGE_URL     = 'https://chatgpt.com/settings/usage';
	const REGISTER_ID   = 'dynamic_agent_client';
	const SECRET_FIELDS = array( 'access_token', 'refresh_token', 'id_token' );

	/* ------------------------------------------------------------- Storage */

	private static function load(): array {
		$s = get_option( self::OPTION, array() );
		$s = is_array( $s ) ? $s : array();
		foreach ( self::SECRET_FIELDS as $f ) {
			$s[ $f ] = WPTA_Secrets::open( (string) ( $s[ $f ] ?? '' ) );
		}
		return $s;
	}

	private static function save( array $s ): void {
		foreach ( self::SECRET_FIELDS as $f ) {
			$s[ $f ] = WPTA_Secrets::seal( (string) ( $s[ $f ] ?? '' ) );
		}
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $s, '', false );
		} else {
			update_option( self::OPTION, $s, false );
		}
	}

	/** Stable, opaque id for this WordPress install (one "agent host"). */
	private static function host_id(): string {
		$s = self::load();
		if ( empty( $s['host_id'] ) ) {
			$s['host_id'] = 'urn:uuid:' . wp_generate_uuid4();
			self::save( $s );
		}
		return $s['host_id'];
	}

	public static function connected(): bool {
		$s = self::load();
		return '' !== $s['refresh_token'] && ! empty( $s['plan'] );
	}

	public static function status(): array {
		$s = self::load();
		return array(
			'connected' => self::connected(),
			'email'     => (string) ( $s['email'] ?? '' ),
			'plan'      => ! empty( $s['plan'] ),
			'usage_url' => self::USAGE_URL,
		);
	}

	/* --------------------------------------------------------------- Sign in */

	private static function b64url( string $bin ): string {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	/** Build the authorize URL and remember this attempt (state, nonce, PKCE verifier) for the current user. */
	public static function start(): string {
		$s        = self::load();
		$verifier = self::b64url( random_bytes( 48 ) );
		$attempt  = array(
			'state'    => self::b64url( random_bytes( 24 ) ),
			'nonce'    => self::b64url( random_bytes( 24 ) ),
			'verifier' => $verifier,
			'client'   => (string) ( $s['client_id'] ?? '' ),
		);
		set_transient( 'wpta_chatgpt_attempt_' . get_current_user_id(), $attempt, 30 * MINUTE_IN_SECONDS );

		$args = array(
			'client_id'             => $attempt['client'] ?: self::REGISTER_ID,
			'ext_agent_host_id'     => self::host_id(),
			'response_type'         => 'code',
			'redirect_uri'          => self::REDIRECT,
			'scope'                 => self::SCOPE,
			'resource'              => self::RESOURCE,
			'state'                 => $attempt['state'],
			'nonce'                 => $attempt['nonce'],
			'code_challenge_method' => 'S256',
			'code_challenge'        => self::b64url( hash( 'sha256', $verifier, true ) ),
		);
		if ( '' === $attempt['client'] ) {
			$args['agent_name_hint'] = WPTA_Settings::brand()['name'];
		} else {
			if ( ! empty( $s['id_token'] ) ) {
				$args['id_token_hint'] = $s['id_token'];
			}
			if ( ! empty( $s['email'] ) ) {
				$args['login_hint'] = $s['email'];
			}
		}
		return self::AUTHORIZE . '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Finish sign-in from the pasted callback address (http://127.0.0.1:1455/auth/callback?code=…).
	 *
	 * @return true|WP_Error
	 */
	public static function finish( string $pasted ) {
		$key     = 'wpta_chatgpt_attempt_' . get_current_user_id();
		$attempt = get_transient( $key );
		if ( ! is_array( $attempt ) ) {
			return new WP_Error( 'wpta_chatgpt_attempt', 'This sign-in expired. Click “Sign in with ChatGPT” again.' );
		}
		$query = wp_parse_url( trim( $pasted ), PHP_URL_QUERY ) ?: ltrim( trim( $pasted ), '?' );
		parse_str( (string) $query, $q );
		if ( empty( $q['state'] ) || ! hash_equals( $attempt['state'], (string) $q['state'] ) ) {
			return new WP_Error( 'wpta_chatgpt_state', 'That address belongs to a different sign-in attempt. Start again and paste the newest address.' );
		}
		delete_transient( $key );
		if ( ! empty( $q['error'] ) ) {
			return new WP_Error( 'wpta_chatgpt_denied', 'access_denied' === $q['error'] ? 'Sign-in was cancelled in ChatGPT.' : 'ChatGPT returned: ' . sanitize_text_field( $q['error'] ) );
		}
		if ( empty( $q['code'] ) ) {
			return new WP_Error( 'wpta_chatgpt_code', 'The pasted address has no sign-in code. Copy the full address from the browser tab.' );
		}

		$client = $attempt['client'];
		if ( ! empty( $q['client_id'] ) ) {
			if ( '' !== $client && $client !== $q['client_id'] ) {
				return new WP_Error( 'wpta_chatgpt_client', 'ChatGPT returned a different app registration than expected. Sign in again.' );
			}
			$client = (string) $q['client_id'];
		}
		if ( '' === $client ) {
			return new WP_Error( 'wpta_chatgpt_client', 'ChatGPT did not return an app registration. Sign in again.' );
		}

		$tokens = self::token_request(
			array(
				'grant_type'    => 'authorization_code',
				'client_id'     => $client,
				'code'          => (string) $q['code'],
				'code_verifier' => $attempt['verifier'],
				'redirect_uri'  => self::REDIRECT,
				'resource'      => self::RESOURCE,
			)
		);
		if ( is_wp_error( $tokens ) ) {
			return $tokens;
		}

		// The ID token comes straight from the token endpoint over TLS, so we check its claims (OIDC Core 3.1.3.7).
		$claims = self::claims( (string) ( $tokens['id_token'] ?? '' ) );
		$aud    = (array) ( $claims['aud'] ?? array() );
		if ( self::ISSUER !== ( $claims['iss'] ?? '' ) || ! in_array( $client, $aud, true ) || ( $claims['nonce'] ?? '' ) !== $attempt['nonce'] || (int) ( $claims['exp'] ?? 0 ) < time() - 300 ) {
			return new WP_Error( 'wpta_chatgpt_id', 'ChatGPT sign-in could not be verified. Try again.' );
		}

		$s              = self::load();
		$s['client_id'] = $client;
		$s['sub']       = (string) ( $claims['sub'] ?? '' );
		$s['email']     = (string) ( $claims['email'] ?? '' );
		$s              = self::store_tokens( $s, $tokens );
		if ( empty( $s['plan'] ) ) {
			return new WP_Error( 'wpta_chatgpt_plan', 'You signed in, but ChatGPT plan usage was not allowed. Sign in again and allow it, or pick another provider.' );
		}
		return true;
	}

	/** @return array The saved state. */
	private static function store_tokens( array $s, array $tokens ): array {
		$scopes                  = explode( ' ', (string) ( $tokens['scope'] ?? '' ) );
		$s['access_token']       = (string) ( $tokens['access_token'] ?? '' );
		$s['refresh_token']      = (string) ( $tokens['refresh_token'] ?? $s['refresh_token'] ?? '' );
		$s['id_token']           = (string) ( $tokens['id_token'] ?? $s['id_token'] ?? '' );
		$s['expires_at']         = time() + (int) ( $tokens['expires_in'] ?? 3600 );
		$s['earliest_refresh']   = (int) ( $tokens['earliest_refresh_at'] ?? 0 );
		$s['plan']               = in_array( self::PLAN_SCOPE, $scopes, true );
		self::save( $s );
		return $s;
	}

	private static function claims( string $jwt ): array {
		$parts = explode( '.', $jwt );
		if ( 3 !== count( $parts ) ) {
			return array();
		}
		$json = base64_decode( strtr( $parts[1], '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $parts[1] ) % 4 ) % 4 ), true );
		$data = $json ? json_decode( $json, true ) : null;
		return is_array( $data ) ? $data : array();
	}

	/** @return array|WP_Error */
	private static function token_request( array $form ) {
		$res = wp_remote_post( self::TOKEN, array( 'timeout' => 30, 'body' => $form ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code || ! is_array( $body ) || empty( $body['access_token'] ) ) {
			$err = is_array( $body ) ? (string) ( $body['error_description'] ?? $body['error'] ?? '' ) : '';
			return new WP_Error( 'wpta_chatgpt_token', 'ChatGPT sign-in failed' . ( $err ? ': ' . $err : ' (HTTP ' . $code . ')' ) . '.', array( 'oauth_error' => $body['error'] ?? '' ) );
		}
		return $body;
	}

	/* ------------------------------------------------------------ Tokens */

	/**
	 * A valid access token, refreshed when it is about to expire.
	 * Refresh tokens rotate, so refreshes are serialized with a short lock.
	 *
	 * @return string|WP_Error
	 */
	public static function access_token() {
		$s = self::load();
		if ( '' === $s['refresh_token'] ) {
			return new WP_Error( 'wpta_chatgpt_signin', 'ChatGPT is not connected. Sign in with ChatGPT in WP2Astro → AI model.' );
		}
		if ( '' !== $s['access_token'] && (int) ( $s['expires_at'] ?? 0 ) > time() + 120 ) {
			return $s['access_token'];
		}

		$lock = 'wpta_chatgpt_refresh_lock';
		for ( $i = 0; $i < 10 && get_transient( $lock ); $i++ ) {
			sleep( 1 ); // Another request is refreshing; wait for its result.
		}
		$s = self::load();
		if ( '' !== $s['access_token'] && (int) ( $s['expires_at'] ?? 0 ) > time() + 120 ) {
			return $s['access_token'];
		}
		set_transient( $lock, 1, 30 );
		$tokens = self::token_request(
			array(
				'grant_type'    => 'refresh_token',
				'client_id'     => $s['client_id'],
				'refresh_token' => $s['refresh_token'],
				'resource'      => self::RESOURCE,
			)
		);
		if ( is_wp_error( $tokens ) ) {
			delete_transient( $lock );
			if ( in_array( $tokens->get_error_data()['oauth_error'] ?? '', array( 'invalid_grant', 'invalid_client' ), true ) ) {
				$s['access_token']  = '';
				$s['refresh_token'] = '';
				self::save( $s );
				return new WP_Error( 'wpta_chatgpt_expired', 'Your ChatGPT session ended. Sign in with ChatGPT again.' );
			}
			return $tokens;
		}
		self::store_tokens( $s, $tokens );
		delete_transient( $lock );
		return (string) $tokens['access_token'];
	}

	/** Sign out: revoke the renewable session, then clear tokens (keep client and host ids for the next sign-in). */
	public static function disconnect(): string {
		$s    = self::load();
		$note = '';
		if ( '' !== $s['refresh_token'] && ! empty( $s['client_id'] ) ) {
			$conf     = json_decode( (string) wp_remote_retrieve_body( wp_remote_get( self::DISCOVERY, array( 'timeout' => 15 ) ) ), true );
			$endpoint = is_array( $conf ) ? (string) ( $conf['revocation_endpoint'] ?? '' ) : '';
			$res      = $endpoint ? wp_remote_post(
				$endpoint,
				array(
					'timeout' => 15,
					'body'    => array(
						'token'           => $s['refresh_token'],
						'token_type_hint' => 'refresh_token',
						'client_id'       => $s['client_id'],
					),
				)
			) : null;
			if ( ! $res || is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				$note = 'Signed out here, but ChatGPT did not confirm it. You can also remove the app in ChatGPT → Settings → Security.';
			}
		}
		foreach ( self::SECRET_FIELDS as $f ) {
			$s[ $f ] = '';
		}
		$s['plan'] = false;
		self::save( $s );
		return $note;
	}

	/** Friendly messages for plan-usage error codes (errors-and-recovery docs). */
	public static function error( string $code, string $message, int $status ): WP_Error {
		$map = array(
			'subscription_sharing_user_not_eligible'    => 'This ChatGPT account or workspace can’t use its plan in other apps.',
			'subscription_sharing_usage_limit_exceeded' => 'Your ChatGPT plan usage limit for this app is reached. Check ' . self::USAGE_URL . ' and try again later, or switch provider.',
			'subscription_sharing_usage_unavailable'    => 'ChatGPT couldn’t check your plan usage right now. Try again in a few minutes.',
		);
		if ( isset( $map[ $code ] ) ) {
			return new WP_Error( 'wpta_' . $code, $map[ $code ] );
		}
		if ( 401 === $status ) {
			return new WP_Error( 'wpta_chatgpt_401', 'ChatGPT did not accept this sign-in. Sign in with ChatGPT again.' );
		}
		return new WP_Error( 'wpta_chatgpt_http', 'ChatGPT: ' . $message );
	}
}
