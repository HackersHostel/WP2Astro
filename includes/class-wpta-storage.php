<?php
defined( 'ABSPATH' ) || exit;

/**
 * Object storage for media: Amazon S3 and S3-compatible services (Cloudflare R2, DigitalOcean Spaces, Wasabi…)
 * signed with AWS Signature Version 4, and Bunny Storage. Plain HTTP via wp_remote_request; no SDK.
 */
class WPTA_Storage {

	const PROVIDERS = array(
		's3'    => 'Amazon S3 or S3-compatible',
		'r2'    => 'Cloudflare R2',
		'bunny' => 'Bunny Storage',
	);

	/**
	 * Storage settings, resolved.
	 *
	 * @return array{provider:string, endpoint:string, bucket:string, region:string, key_id:string, secret:string, url:string}
	 */
	public static function config( ?array $s = null ): array {
		$s        = $s ?? WPTA_Settings::get();
		$provider = isset( self::PROVIDERS[ $s['cdn_provider'] ?? '' ] ) ? $s['cdn_provider'] : 's3';
		$region   = trim( (string) ( $s['cdn_region'] ?? '' ) ) ?: 'us-east-1';
		$endpoint = rtrim( trim( (string) ( $s['cdn_endpoint'] ?? '' ) ), '/' );
		if ( '' === $endpoint && 'bunny' === $provider ) {
			$endpoint = 'https://storage.bunnycdn.com';
		} elseif ( '' === $endpoint && 's3' === $provider ) {
			$endpoint = 'https://s3.' . $region . '.amazonaws.com'; // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- the S3 API address for uploads, not an asset.
		}
		return array(
			'provider' => $provider,
			'endpoint' => $endpoint,
			'bucket'   => trim( (string) ( $s['cdn_bucket'] ?? '' ) ),
			'region'   => 'r2' === $provider ? 'auto' : $region,
			'key_id'   => trim( (string) ( $s['cdn_key_id'] ?? '' ) ),
			'secret'   => (string) ( $s['cdn_secret'] ?? '' ),
			'url'      => rtrim( trim( (string) ( $s['cdn_url'] ?? '' ) ), '/' ),
		);
	}

	/** What is missing before uploads can work, or ''. */
	public static function missing( array $c ): string {
		$need = array(
			'endpoint' => 'r2' === $c['provider'] ? 'the R2 endpoint (https://<account id>.r2.cloudflarestorage.com)' : 'the endpoint',
			'bucket'   => 'bunny' === $c['provider'] ? 'the storage zone name' : 'the bucket name',
			'secret'   => 'bunny' === $c['provider'] ? 'the storage zone password' : 'the secret access key',
			'url'      => 'the public URL the files are served from',
		);
		if ( 'bunny' !== $c['provider'] ) {
			$need['key_id'] = 'the access key ID';
		}
		foreach ( $need as $k => $label ) {
			if ( '' === $c[ $k ] ) {
				return 'Media storage needs ' . $label . '.';
			}
		}
		if ( ! preg_match( '#^https?://[^/\s]+#i', $c['endpoint'] ) || ! preg_match( '#^https?://[^/\s]+#i', $c['url'] ) ) {
			return 'The endpoint and the public URL must be full addresses, starting with https://.';
		}
		return '';
	}

	/** A short fingerprint of where files go, so a change of bucket or provider uploads everything again. */
	public static function target( array $c ): string {
		return substr( md5( $c['provider'] . '|' . $c['endpoint'] . '|' . $c['bucket'] ), 0, 12 );
	}

	/**
	 * Upload one local file as $key ("wp-content/uploads/2024/01/a.jpg").
	 *
	 * @return true|WP_Error
	 */
	public static function put( array $c, string $key, string $file ) {
		$body = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- local file.
		if ( false === $body ) {
			return new WP_Error( 'wpta_storage', 'Could not read ' . basename( $file ) . '.' );
		}
		$type = wp_check_filetype( $file )['type'] ?: 'application/octet-stream';
		return self::put_bytes( $c, $key, $body, $type );
	}

	/** @return true|WP_Error */
	public static function put_bytes( array $c, string $key, string $body, string $type ) {
		$path = '/' . rawurlencode( $c['bucket'] ) . '/' . implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $key, '/' ) ) ) );
		$url  = $c['endpoint'] . $path;
		if ( 'bunny' === $c['provider'] ) {
			$headers = array(
				'AccessKey'    => $c['secret'],
				'Content-Type' => 'application/octet-stream',
				'Checksum'     => strtoupper( hash( 'sha256', $body ) ),
			);
		} else {
			$host    = (string) wp_parse_url( $c['endpoint'], PHP_URL_HOST ) . ( wp_parse_url( $c['endpoint'], PHP_URL_PORT ) ? ':' . wp_parse_url( $c['endpoint'], PHP_URL_PORT ) : '' );
			$headers = array(
				'Content-Type'         => $type,
				'Cache-Control'        => 'public, max-age=86400',
				'x-amz-content-sha256' => hash( 'sha256', $body ),
				'x-amz-date'           => gmdate( 'Ymd\THis\Z' ),
			);
			$headers['Authorization'] = self::sigv4(
				'PUT',
				$host,
				$path,
				array(
					'content-type'         => $headers['Content-Type'],
					'x-amz-content-sha256' => $headers['x-amz-content-sha256'],
					'x-amz-date'           => $headers['x-amz-date'],
				),
				$headers['x-amz-content-sha256'],
				$c['key_id'],
				$c['secret'],
				$c['region']
			);
		}
		$res = wp_remote_request(
			$url,
			array(
				'method'  => 'PUT',
				'timeout' => (int) min( 120, 20 + strlen( $body ) / MB_IN_BYTES * 2 ), // ~2 s per MB, so a dead endpoint fails fast.
				'headers' => $headers,
				'body'    => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code > 299 ) {
			$msg = wp_strip_all_tags( (string) wp_remote_retrieve_body( $res ) );
			if ( preg_match( '#<Message>(.*?)</Message>#s', (string) wp_remote_retrieve_body( $res ), $m ) ) {
				$msg = $m[1];
			}
			return new WP_Error( 'wpta_storage_' . $code, self::PROVIDERS[ $c['provider'] ] . ': HTTP ' . $code . ( $msg ? ' — ' . mb_substr( trim( $msg ), 0, 200 ) : '' ) );
		}
		return true;
	}

	/**
	 * AWS Signature Version 4, header form. Pure (unit-tested against AWS's documented example).
	 *
	 * @param array<string,string> $headers      Headers to sign, including x-amz-date (host is added).
	 * @param string               $payload_hash Hex SHA-256 of the body, as sent in x-amz-content-sha256.
	 * @param string               $query        Canonical query string ('' for none).
	 * @return string Authorization header value.
	 */
	public static function sigv4( string $method, string $host, string $path, array $headers, string $payload_hash, string $key_id, string $secret, string $region, string $query = '' ): string {
		$headers['host'] = $host;
		$headers         = array_change_key_case( $headers, CASE_LOWER );
		ksort( $headers );
		$canon_headers = '';
		foreach ( $headers as $k => $v ) {
			$canon_headers .= $k . ':' . trim( preg_replace( '/\s+/', ' ', (string) $v ) ) . "\n";
		}
		$signed   = implode( ';', array_keys( $headers ) );
		$amz_date = $headers['x-amz-date'];
		$day      = substr( $amz_date, 0, 8 );
		$scope    = $day . '/' . $region . '/s3/aws4_request';

		$canonical = $method . "\n" . $path . "\n" . $query . "\n" . $canon_headers . "\n" . $signed . "\n" . $payload_hash;
		$to_sign   = "AWS4-HMAC-SHA256\n" . $amz_date . "\n" . $scope . "\n" . hash( 'sha256', $canonical );

		$k = hash_hmac( 'sha256', $day, 'AWS4' . $secret, true );
		$k = hash_hmac( 'sha256', $region, $k, true );
		$k = hash_hmac( 'sha256', 's3', $k, true );
		$k = hash_hmac( 'sha256', 'aws4_request', $k, true );

		return 'AWS4-HMAC-SHA256 Credential=' . $key_id . '/' . $scope . ', SignedHeaders=' . $signed . ', Signature=' . hash_hmac( 'sha256', $to_sign, $k );
	}
}
