<?php
defined( 'ABSPATH' ) || exit;

/**
 * Updates from Hackers Hostel GitHub releases (the plugin header's Update URI),
 * through core's update_plugins_{hostname} filter (WP 5.8+). Releases attach a `wp2astro.zip` asset.
 */
class WPTA_Updater {

	const CACHE = 'wpta_release';

	public static function init(): void {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'info' ), 10, 3 );
	}

	public static function repo(): string {
		return (string) apply_filters( 'wpta_update_repo', 'HackersHostel/wp2astro' );
	}

	/** Latest release: version, zip URL, notes, page URL. Cached for 12 hours. */
	public static function release(): ?array {
		$cached = get_site_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached ?: null;
		}
		$res  = wp_remote_get(
			'https://api.github.com/repos/' . self::repo() . '/releases/latest',
			array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'wp2astro' ) )
		);
		$data = is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
		$out  = array();
		if ( is_array( $data ) && ! empty( $data['tag_name'] ) && empty( $data['draft'] ) && empty( $data['prerelease'] ) ) {
			foreach ( (array) $data['assets'] as $a ) {
				if ( 'wp2astro.zip' === ( $a['name'] ?? '' ) ) {
					$out = array(
						'version' => ltrim( (string) $data['tag_name'], 'v' ),
						'package' => (string) $a['browser_download_url'],
						'url'     => (string) $data['html_url'],
						'notes'   => (string) ( $data['body'] ?? '' ),
						'date'    => (string) ( $data['published_at'] ?? '' ),
					);
				}
			}
		}
		set_site_transient( self::CACHE, $out, $out ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		return $out ?: null;
	}

	/** @param array|false $update */
	public static function check( $update, array $plugin_data, string $plugin_file ) {
		if ( plugin_basename( WPTA_FILE ) !== $plugin_file ) {
			return $update;
		}
		$r = self::release();
		if ( ! $r ) {
			return $update;
		}
		return array(
			'slug'         => dirname( $plugin_file ),
			'version'      => $r['version'],
			'url'          => $r['url'],
			'package'      => version_compare( $r['version'], WPTA_VERSION, '>' ) ? $r['package'] : '',
			'requires'     => '6.5',
			'requires_php' => '8.0',
		);
	}

	/** "View details" in the plugins list. */
	public static function info( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action || dirname( plugin_basename( WPTA_FILE ) ) !== ( $args->slug ?? '' ) ) {
			return $result;
		}
		$r     = self::release();
		$brand = WPTA_Settings::brand();
		return (object) array(
			'name'          => $brand['name'],
			'slug'          => $args->slug,
			'version'       => $r['version'] ?? WPTA_VERSION,
			'author'        => '<a href="https://hackershostel.com">Hackers Hostel</a>',
			'homepage'      => $brand['url'],
			'download_link' => $r['package'] ?? '',
			'requires'      => '6.5',
			'requires_php'  => '8.0',
			'last_updated'  => $r['date'] ?? '',
			'sections'      => array(
				'description' => 'Turn this WordPress site into a fast Astro site with the same URLs, push it to GitHub and keep it in sync.',
				'changelog'   => $r ? '<pre>' . esc_html( $r['notes'] ) . '</pre>' : '',
			),
		);
	}
}
