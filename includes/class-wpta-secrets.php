<?php
defined( 'ABSPATH' ) || exit;

/**
 * Encrypts API keys and tokens before they are stored in wp_options.
 * The key is derived from the site's auth salts, so a database dump alone doesn't leak them.
 * If the salts change, stored secrets can't be read and the user simply connects again.
 */
class WPTA_Secrets {

	const PREFIX = 'wpta1:';

	private static function available(): bool {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) && is_readable( ABSPATH . WPINC . '/sodium_compat/autoload.php' ) ) {
			require_once ABSPATH . WPINC . '/sodium_compat/autoload.php';
		}
		return function_exists( 'sodium_crypto_secretbox' );
	}

	private static function key(): string {
		return hash_hmac( 'sha256', 'wp2astro-secrets', wp_salt( 'auth' ), true );
	}

	public static function seal( string $plain ): string {
		if ( '' === $plain || ! self::available() ) {
			return $plain;
		}
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return self::PREFIX . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, self::key() ) );
	}

	/** Plain (legacy) values pass through unchanged; unreadable ones become ''. */
	public static function open( string $stored ): string {
		if ( 0 !== strpos( $stored, self::PREFIX ) ) {
			return $stored;
		}
		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
		if ( false === $raw || ! self::available() || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		try {
			$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::key() );
		} catch ( Throwable $e ) {
			return '';
		}
		return false === $plain ? '' : $plain;
	}

	public static function mask( string $secret ): string {
		return '' === $secret ? '' : str_repeat( '•', 8 ) . substr( $secret, -4 );
	}
}
