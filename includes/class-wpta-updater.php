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
		/**
		 * Filters the GitHub repository that plugin updates are read from.
		 *
		 * The repository's latest release must carry a wp2astro.zip asset whose top folder is wp2astro/.
		 *
		 * @since 0.2.0
		 *
		 * @param string $repo Repository as "owner/name". Default 'HackersHostel/wp2astro'.
		 */
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

	/** "View details" in the plugins list: the readme.txt sections, with the latest release notes as the changelog. */
	public static function info( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action || dirname( plugin_basename( WPTA_FILE ) ) !== ( $args->slug ?? '' ) ) {
			return $result;
		}
		$r        = self::release();
		$brand    = WPTA_Settings::brand();
		$readme   = WPTA_DIR . 'readme.txt';
		$sections = is_readable( $readme ) ? self::readme_sections( (string) file_get_contents( $readme ) ) : array();
		if ( $r ) {
			$sections['changelog'] = '<pre>' . esc_html( $r['notes'] ) . '</pre>';
		}
		$sections += array( 'description' => esc_html( 'Turn this WordPress site into a fast Astro site with the same URLs, push it to GitHub and keep it in sync.' ) );
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
			'sections'      => $sections,
		);
	}

	/**
	 * The readme.txt sections the plugin details modal shows, as HTML. Pure (unit-tested).
	 * Sections without a tab of their own (External services…) are appended to the description, as on WordPress.org.
	 *
	 * @return array<string,string> description, installation, faq, changelog => HTML
	 */
	public static function readme_sections( string $txt ): array {
		$tabs  = array( 'description' => 'description', 'installation' => 'installation', 'frequently asked questions' => 'faq', 'changelog' => 'changelog' );
		$parts = preg_split( '/^==[ \t]*(.+?)[ \t]*==[ \t]*$/m', str_replace( "\r\n", "\n", $txt ), -1, PREG_SPLIT_DELIM_CAPTURE );
		$out   = array();
		for ( $i = 1; $i + 1 < count( $parts ); $i += 2 ) {
			$name = strtolower( trim( $parts[ $i ] ) );
			if ( in_array( $name, array( 'screenshots', 'upgrade notice' ), true ) ) {
				continue;
			}
			$html = self::readme_html( $parts[ $i + 1 ] );
			if ( isset( $tabs[ $name ] ) ) {
				$out[ $tabs[ $name ] ] = $html;
			} else {
				$out['description'] = ( $out['description'] ?? '' ) . '<h3>' . esc_html( trim( $parts[ $i ] ) ) . '</h3>' . $html;
			}
		}
		return $out;
	}

	/** The small Markdown subset readme.txt uses: = headings =, paragraphs, lists, **bold**, `code`, [links](https://…). */
	private static function readme_html( string $md ): string {
		$inline = function ( string $t ): string {
			$t = esc_html( $t );
			$t = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $t );
			$t = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $t );
			return preg_replace_callback( '/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', fn( $m ) => '<a href="' . esc_url( html_entity_decode( $m[2] ) ) . '">' . $m[1] . '</a>', $t );
		};
		$html = '';
		foreach ( preg_split( '/\n[ \t]*\n/', trim( $md ) ) as $block ) {
			$lines = explode( "\n", trim( $block ) );
			if ( preg_match( '/^=[ \t]*(.+?)[ \t]*=$/', $lines[0], $h ) ) {
				$html .= '<h4>' . $inline( $h[1] ) . '</h4>';
				array_shift( $lines );
				if ( ! $lines ) {
					continue;
				}
			}
			if ( preg_match( '/^([*-]|\d+\.)[ \t]/', $lines[0], $l ) ) {
				$tag   = ctype_digit( $l[1][0] ) ? 'ol' : 'ul';
				$items = array();
				foreach ( $lines as $line ) {
					if ( preg_match( '/^(?:[*-]|\d+\.)[ \t]+(.*)$/', $line, $m ) ) {
						$items[] = $m[1];
					} elseif ( $items ) {
						$items[ count( $items ) - 1 ] .= ' ' . trim( $line ); // Wrapped list item.
					}
				}
				$html .= '<' . $tag . '><li>' . implode( '</li><li>', array_map( $inline, $items ) ) . '</li></' . $tag . '>';
			} else {
				$html .= '<p>' . $inline( implode( ' ', array_map( 'trim', $lines ) ) ) . '</p>';
			}
		}
		return $html;
	}
}
