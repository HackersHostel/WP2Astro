<?php
defined( 'ABSPATH' ) || exit;

/**
 * Deterministic export: site data, entries → Markdown, SEO meta, redirects, media and theme assets.
 */
class WPTA_Exporter {

	const ASSET_EXT       = array( 'css', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'eot' );
	const MAX_ASSET_BYTES = 5 * MB_IN_BYTES;
	/** Commerce and builder internals: not content a static site can serve. */
	const SKIP_TYPES = array( 'attachment', 'product', 'product_variation', 'shop_order', 'shop_coupon', 'elementor_library', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' );

	const KNOWN_SHORTCODES = array( 'gallery', 'caption', 'embed', 'video', 'audio', 'playlist', 'contact-form', 'contact-form-7', 'button', 'column', 'columns', 'row', 'tabs', 'tab', 'toggle', 'accordion', 'slider', 'map', 'youtube', 'vimeo', 'spoiler', 'quote', 'box', 'alert', 'note' );

	/* ------------------------------------------------------------------ URLs */

	/** Strip this site's origin so links/media become root-relative. */
	public static function relative( string $text ): string {
		$hosts = array_unique( array_filter( array( wp_parse_url( home_url(), PHP_URL_HOST ), wp_parse_url( site_url(), PHP_URL_HOST ) ) ) );
		foreach ( $hosts as $host ) {
			$text = preg_replace( '#(?:https?:)?//' . preg_quote( $host, '#' ) . '(?::\d+)?(?=[/"\'\s)?\#<]|$)#i', '', $text );
		}
		return $text;
	}

	/** Point theme URLs at the copies in public/theme and public/theme-parent. */
	public static function theme_paths( string $text ): string {
		$map = array( rtrim( self::rel_url( get_stylesheet_directory_uri() ), '/' ) . '/' => '/theme/' );
		if ( get_template_directory() !== get_stylesheet_directory() ) {
			$map[ rtrim( self::rel_url( get_template_directory_uri() ), '/' ) . '/' ] = '/theme-parent/';
		}
		return strtr( $text, $map );
	}

	public static function rel_url( string $url ): string {
		$r = self::relative( $url );
		return '' === $r ? '/' : $r;
	}

	/**
	 * Decode the non-ASCII part of a root-relative path ("/caf%c3%a9/" → "/café/"). WordPress stores UTF-8 slugs
	 * percent-encoded, but Astro routes and server.mjs match decoded paths. Encoded ASCII (%2F, %3F, %20…) and the
	 * query string are left alone. Pure (unit-tested).
	 */
	public static function decode_path( string $url ): string {
		if ( '' === $url || '/' !== $url[0] || 0 === strpos( $url, '//' ) ) {
			return $url;
		}
		$cut  = strcspn( $url, '?#' );
		$path = preg_replace_callback( '/(?:%[89a-f][0-9a-f])+/i', fn( $m ) => rawurldecode( $m[0] ), substr( $url, 0, $cut ) );
		return mb_check_encoding( $path, 'UTF-8' ) ? $path . substr( $url, $cut ) : $url;
	}

	/** Permalink path usable as a static route ("/2010/05/hello/"). */
	public static function permalink( WP_Post $post ): string {
		$path = self::rel_url( get_permalink( $post ) );
		if ( false !== strpos( $path, '?' ) ) {
			// Plain permalinks (?p=123) can't be static routes.
			$path = '/' . ( 'page' === $post->post_type ? get_page_uri( $post ) : $post->post_name ) . '/';
		}
		$path = self::decode_path( '/' . ltrim( $path, '/' ) );
		return str_ends_with( $path, '/' ) || preg_match( '/\.[a-z0-9]{2,5}$/i', $path ) ? $path : $path . '/';
	}

	/* -------------------------------------------------------------- Site data */

	public static function site_data( array $stylesheets, array $scripts = array() ): array {
		$cats = array();
		foreach ( get_categories( array( 'hide_empty' => true ) ) as $t ) {
			$cats[] = array( 'slug' => $t->slug, 'name' => html_entity_decode( $t->name, ENT_QUOTES ), 'path' => self::decode_path( self::rel_url( get_term_link( $t ) ) ) );
		}
		$tags = array();
		foreach ( get_tags( array( 'hide_empty' => true ) ) as $t ) {
			$tags[] = array( 'slug' => $t->slug, 'name' => html_entity_decode( $t->name, ENT_QUOTES ), 'path' => self::decode_path( self::rel_url( get_term_link( $t ) ) ) );
		}

		$static_front = 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) > 0;
		$posts_page   = (int) get_option( 'page_for_posts' );
		$blog_base    = $static_front && $posts_page ? self::permalink( get_post( $posts_page ) ) : '/';
		$name         = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$s        = WPTA_Settings::get();
		$archives = array();
		foreach ( array_diff( self::post_types(), array( 'post', 'page' ) ) as $type ) {
			$obj  = get_post_type_object( $type );
			$link = get_post_type_archive_link( $type );
			$archives[] = array(
				'type'  => $type,
				'label' => html_entity_decode( $obj->labels->name, ENT_QUOTES ),
				'path'  => $obj->has_archive && $link ? self::decode_path( self::rel_url( trailingslashit( $link ) ) ) : '',
			);
		}

		// Author archives (/author/name/) for everyone with published entries.
		$authors = array();
		foreach ( get_users( array( 'has_published_posts' => self::post_types(), 'orderby' => 'nicename' ) ) as $u ) {
			$authors[] = array(
				'slug' => $u->user_nicename,
				'name' => html_entity_decode( $u->display_name, ENT_QUOTES ),
				'path' => self::decode_path( self::rel_url( get_author_posts_url( $u->ID, $u->user_nicename ) ) ),
			);
		}

		// Date archives (/2024/, /2024/01/) as WordPress builds them; only with pretty permalinks.
		$dates = array();
		if ( get_option( 'permalink_structure' ) ) {
			$dates = array(
				'year'  => str_replace( '1970', '{y}', self::rel_url( get_year_link( 1970 ) ) ),
				'month' => str_replace( array( '1970', '/12/' ), array( '{y}', '/{m}/' ), self::rel_url( get_month_link( 1970, 12 ) ) ),
			);
		}

		return array(
			'title'        => $name,
			'description'  => html_entity_decode( get_bloginfo( 'description' ), ENT_QUOTES ),
			'url'          => home_url( '/' ),
			'slug'         => sanitize_title( $name ) ?: 'site',
			'language'     => get_bloginfo( 'language' ),
			'timezone'     => wp_timezone_string(),
			'postsPerPage' => max( 1, (int) get_option( 'posts_per_page', 10 ) ),
			'frontPage'    => $static_front,
			'blogBase'     => $blog_base,
			'menu'         => self::menu(),
			'categories'   => $cats,
			'tags'         => $tags,
			'stylesheets'  => $stylesheets,
			'scripts'      => $scripts,
			'archives'     => $archives,
			'authors'      => $authors,
			'dateArchives' => $dates ? $dates : (object) array(),
			'features'     => array(
				'seo'      => (bool) $s['seo'],
				'feeds'    => (bool) $s['feeds'],
				'search'   => (bool) $s['search'],
				'agentKit' => (bool) $s['agent_kit'],
			),
			'generator'    => WPTA_Settings::brand()['name'] . ' ' . WPTA_VERSION,
		);
	}

	private static function menu(): array {
		$locations = get_nav_menu_locations();
		$menu_id   = 0;
		foreach ( array( 'primary', 'menu-1', 'main', 'header', 'top' ) as $loc ) {
			if ( ! empty( $locations[ $loc ] ) ) {
				$menu_id = $locations[ $loc ];
				break;
			}
		}
		if ( ! $menu_id && $locations ) {
			$menu_id = (int) reset( $locations );
		}
		if ( ! $menu_id ) {
			$menus   = wp_get_nav_menus();
			$menu_id = $menus ? $menus[0]->term_id : 0;
		}

		$items = array();
		if ( $menu_id ) {
			foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $item ) {
				if ( (int) $item->menu_item_parent === 0 ) {
					$items[] = array( 'label' => html_entity_decode( $item->title, ENT_QUOTES ), 'url' => self::decode_path( self::rel_url( $item->url ) ) );
				}
			}
		}
		if ( ! $items ) {
			// Same fallback WordPress uses: top-level pages.
			$front = (int) get_option( 'page_on_front' );
			foreach ( get_pages( array( 'parent' => 0, 'sort_column' => 'menu_order,post_title' ) ) as $p ) {
				if ( $p->ID !== $front ) {
					$items[] = array( 'label' => html_entity_decode( get_the_title( $p ), ENT_QUOTES ), 'url' => self::permalink( $p ) );
				}
			}
		}
		return $items;
	}

	/* ---------------------------------------------------------------- Entries */

	/** Post types to export: posts, pages and (when enabled) public custom post types. */
	public static function post_types(): array {
		$types = array( 'post', 'page' );
		if ( WPTA_Settings::get()['cpts'] ) {
			foreach ( get_post_types( array( 'public' => true, '_builtin' => false ), 'names' ) as $t ) {
				if ( ! in_array( $t, self::SKIP_TYPES, true ) && is_post_type_viewable( $t ) ) {
					$types[] = $t;
				}
			}
		}

		/**
		 * Filters the post types exported to the Astro site and watched by auto-sync.
		 *
		 * Posts go to src/content/posts/, pages to src/content/pages/ and every other type to
		 * src/content/items/{type}/. Only published, non-password-protected entries are exported.
		 *
		 * @since 0.2.0
		 *
		 * @param string[] $types Post type names: 'post', 'page' and, when "Custom post types" is on, every public,
		 *                        viewable custom post type except commerce and builder internals.
		 */
		return array_values( array_unique( (array) apply_filters( 'wpta_post_types', $types ) ) );
	}

	public static function entry_ids(): array {
		return get_posts(
			array(
				'post_type'      => self::post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'has_password'   => false,
			)
		);
	}

	/**
	 * @return array{path:string, markdown:string, media:string[], flags:string[], permalink:string, title:string, type:string, redirects:array<string,string>}
	 */
	public static function entry( int $id ): array {
		$post = get_post( $id );
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $post );
		// Shortcodes that echo instead of returning would corrupt the AJAX step's JSON. WordPress prints that output
		// before the content, so keep it there.
		ob_start();
		$html = apply_filters( 'the_content', $post->post_content );
		$html = ob_get_clean() . $html;
		wp_reset_postdata();

		$html  = self::relative( $html );
		$flags = array();
		$dead  = self::dead_shortcodes( $html );
		if ( $dead ) {
			$flags[] = 'shortcodes:' . implode( ',', $dead );
			// wptexturize curled the quotes inside dead shortcodes; straighten them for the AI step.
			$html = preg_replace_callback(
				'/\[\/?[a-z][a-z0-9_-]*[^\]]*\]/i',
				fn( $m ) => str_replace( array( '&#8221;', '&#8220;', '&#8243;', '&#8242;', '&#8217;', '&#8216;', '”', '“', '″', '′', '’', '‘' ), array( '"', '"', '"', "'", "'", "'", '"', '"', '"', "'", "'", "'" ), $m[0] ),
				$html
			);
		}

		$featured = get_the_post_thumbnail_url( $post, 'full' );
		$featured = $featured ? self::rel_url( $featured ) : '';

		$media = self::media_refs( $html . ' ' . $featured );

		if ( has_excerpt( $post ) ) {
			$excerpt = $post->post_excerpt;
		} else {
			$plain   = wp_strip_all_tags( preg_replace( '#<(pre|table|figure)\b.*?</\1>#is', ' ', str_replace( '<', ' <', $html ) ) );
			$plain   = preg_replace( '/\[\/?[a-z][a-z0-9_-]*[^\]]*\]/i', ' ', $plain );
			$excerpt = wp_trim_words( $plain, 40, '…' );
		}

		$front = array(
			'title'      => html_entity_decode( get_the_title( $post ), ENT_QUOTES ),
			'date'       => get_post_time( 'c', false, $post ),
			'updated'    => get_post_modified_time( 'c', false, $post ),
			'permalink'  => self::permalink( $post ),
			'excerpt'    => trim( html_entity_decode( wp_strip_all_tags( $excerpt ), ENT_QUOTES ) ),
			'author'     => get_the_author_meta( 'display_name', $post->post_author ),
			'authorSlug' => (string) get_the_author_meta( 'user_nicename', $post->post_author ),
			'categories' => 'post' === $post->post_type ? wp_get_post_categories( $id, array( 'fields' => 'slugs' ) ) : array(),
			'tags'       => 'post' === $post->post_type ? wp_get_post_tags( $id, array( 'fields' => 'slugs' ) ) : array(),
			'type'       => $post->post_type,
		);
		if ( $featured ) {
			$front['featuredImage'] = $featured;
		}
		$settings = WPTA_Settings::get();
		if ( $settings['seo'] ) {
			foreach ( self::seo( $post ) as $k => $v ) {
				$front[ $k ] = $v;
			}
		}
		if ( ! empty( $settings['fields'] ) ) {
			$fields = self::fields( $post );
			if ( $fields ) {
				$front['fields'] = $fields;
				$media           = array_values( array_unique( array_merge( $media, self::media_refs( wp_json_encode( $fields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) ) ) );
			}
		}

		$slug = $post->post_name ?: 'entry-' . $id;
		if ( 'post' === $post->post_type ) {
			$path = 'src/content/posts/' . get_post_time( 'Y-m-d', false, $post ) . '-' . $slug . '.md';
		} elseif ( 'page' === $post->post_type ) {
			$path = 'src/content/pages/' . str_replace( '/', '--', get_page_uri( $post ) ?: $slug ) . '.md';
		} else {
			$uri  = is_post_type_hierarchical( $post->post_type ) ? get_page_uri( $post ) : $slug;
			$path = 'src/content/items/' . sanitize_key( $post->post_type ) . '/' . str_replace( '/', '--', $uri ?: $slug ) . '.md';
		}

		// Media left on WordPress or in object storage: point the links there instead of at copies in the repo.
		$mode     = WPTA_Media::mode( $settings );
		$markdown = WPTA_Media::rewrite( self::frontmatter( $front ) . WPTA_Markdown::convert( $html ), WPTA_Media::rel_base(), WPTA_Media::base( $mode ) );

		return array(
			'path'      => $path,
			'markdown'  => $markdown,
			'media'     => $media,
			'flags'     => $flags,
			'permalink' => $front['permalink'],
			'title'     => $front['title'],
			'type'      => $post->post_type,
			'redirects' => self::old_slugs( $post, $front['permalink'] ),
		);
	}

	/* ---------------------------------------------------------- Custom fields */

	/** Public meta that belongs to plugins or core, not to the content. */
	const SKIP_META = array( 'footnotes', 'enclosure', 'rank_math_', 'classic-editor-', 'wpcf7_', 'elementor_' );

	/**
	 * Custom fields for the `fields:` frontmatter key: public post meta (keys without a leading underscore) and, when
	 * ACF is active, its formatted fields. Keys are sorted so unchanged posts give unchanged files.
	 */
	public static function fields( WP_Post $post ): array {
		$fields = array();
		foreach ( (array) get_post_meta( $post->ID ) as $key => $values ) {
			$key = (string) $key;
			if ( '' === $key || '_' === $key[0] ) {
				continue;
			}
			foreach ( self::SKIP_META as $skip ) {
				if ( 0 === strpos( $key, $skip ) ) {
					continue 2;
				}
			}
			$values         = array_map( 'maybe_unserialize', (array) $values );
			$fields[ $key ] = 1 === count( $values ) ? $values[0] : $values;
		}
		if ( function_exists( 'get_fields' ) ) {
			$acf = get_fields( $post->ID );
			foreach ( is_array( $acf ) ? $acf : array() as $key => $value ) {
				$fields[ $key ] = $value; // ACF's formatted value wins over the raw meta of the same name.
			}
		}

		/**
		 * Filters the custom fields written to an entry's frontmatter under `fields:`.
		 *
		 * Runs only when "Custom fields" is on. Remove private values here: everything returned ends up in the
		 * generated repository. Posts, terms and users are written as small objects; strings that link to this
		 * site become root-relative.
		 *
		 * @since 0.3.0
		 *
		 * @param array   $fields Field name => value: public post meta, then ACF fields (formatted) when ACF is active.
		 * @param WP_Post $post   The entry.
		 */
		$fields = apply_filters( 'wpta_entry_fields', $fields, $post );
		return is_array( $fields ) ? (array) self::plain( $fields ) : array();
	}

	/** JSON-safe, deterministic copy of a field value. Pure apart from permalink lookups for posts and terms. */
	public static function plain( $v ) {
		if ( $v instanceof WP_Post ) {
			return array( 'id' => $v->ID, 'title' => html_entity_decode( get_the_title( $v ), ENT_QUOTES ), 'permalink' => self::permalink( $v ) );
		}
		if ( $v instanceof WP_Term ) {
			return array( 'slug' => $v->slug, 'name' => html_entity_decode( $v->name, ENT_QUOTES ) );
		}
		if ( $v instanceof WP_User ) {
			return array( 'name' => $v->display_name );
		}
		if ( is_object( $v ) ) {
			$v = get_object_vars( $v );
		}
		if ( is_array( $v ) ) {
			$out = array();
			foreach ( $v as $k => $item ) {
				$out[ $k ] = self::plain( $item );
			}
			if ( $out && array_keys( $out ) !== range( 0, count( $out ) - 1 ) ) {
				ksort( $out, SORT_STRING );
			}
			return $out;
		}
		if ( is_string( $v ) ) {
			return self::decode_path( self::relative( $v ) );
		}
		return is_scalar( $v ) || null === $v ? $v : null;
	}

	/* -------------------------------------------------------------------- SEO */

	/** Name of the active SEO plugin we read from, or ''. */
	public static function seo_plugin(): string {
		if ( function_exists( 'YoastSEO' ) ) {
			return 'Yoast SEO';
		}
		if ( class_exists( 'RankMath\\Helper' ) ) {
			return 'Rank Math';
		}
		if ( function_exists( 'aioseo' ) ) {
			return 'All in One SEO';
		}
		return '';
	}

	/**
	 * Title, description, noindex and canonical as the SEO plugin would print them.
	 * Falls back to stored meta (with basic variables replaced) when the plugin is gone.
	 *
	 * @return array{seoTitle?:string, seoDescription?:string, noindex?:bool, canonical?:string}
	 */
	public static function seo( WP_Post $post ): array {
		$title = '';
		$desc  = '';
		$robot = '';
		$canon = '';
		try {
			switch ( self::seo_plugin() ) {
				case 'Yoast SEO':
					$meta  = YoastSEO()->meta->for_post( $post->ID );
					$title = $meta ? (string) $meta->title : '';
					$desc  = $meta ? (string) $meta->description : '';
					$robot = $meta ? implode( ',', (array) $meta->robots ) : '';
					$canon = (string) get_post_meta( $post->ID, '_yoast_wpseo_canonical', true );
					break;
				case 'Rank Math':
					$title = (string) \RankMath\Post::get_meta( 'title', $post->ID );
					$desc  = (string) \RankMath\Post::get_meta( 'description', $post->ID );
					$title = $title ? \RankMath\Helper::replace_vars( $title, $post ) : '';
					$desc  = $desc ? \RankMath\Helper::replace_vars( $desc, $post ) : '';
					$robot = implode( ',', (array) \RankMath\Post::get_meta( 'robots', $post->ID ) );
					$canon = (string) \RankMath\Post::get_meta( 'canonical_url', $post->ID );
					break;
				case 'All in One SEO':
					$title = (string) aioseo()->meta->title->getPostTitle( $post );
					$desc  = (string) aioseo()->meta->description->getPostDescription( $post );
					break;
			}
		} catch ( Throwable $e ) {
			$title = '';
			$desc  = '';
		}

		if ( '' === $title && '' === $desc ) {
			$vars  = array(
				'title'    => get_the_title( $post ),
				'sitename' => get_bloginfo( 'name' ),
				'sitedesc' => get_bloginfo( 'description' ),
				'sep'      => '–',
				'excerpt'  => wp_strip_all_tags( get_the_excerpt( $post ) ),
			);
			$fill  = function ( string $t ) use ( $vars ): string {
				$t = preg_replace_callback( '/%%?([a-z_]+)%%?/', fn( $m ) => $vars[ $m[1] ] ?? '', $t );
				return trim( preg_replace( '/\s+/', ' ', $t ) );
			};
			$title = $fill( (string) ( get_post_meta( $post->ID, '_yoast_wpseo_title', true ) ?: get_post_meta( $post->ID, 'rank_math_title', true ) ) );
			$desc  = $fill( (string) ( get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true ) ?: get_post_meta( $post->ID, 'rank_math_description', true ) ) );
			$robot = get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true ) ? 'noindex' : implode( ',', (array) get_post_meta( $post->ID, 'rank_math_robots', true ) );
		}

		$out = array();
		if ( '' !== $title ) {
			$out['seoTitle'] = html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES );
		}
		if ( '' !== $desc ) {
			$out['seoDescription'] = html_entity_decode( wp_strip_all_tags( $desc ), ENT_QUOTES );
		}
		if ( false !== strpos( $robot, 'noindex' ) ) {
			$out['noindex'] = true;
		}
		$canon = $canon ? self::decode_path( self::rel_url( $canon ) ) : '';
		if ( $canon && 0 !== strpos( $canon, self::permalink( $post ) ) ) {
			$out['canonical'] = $canon;
		}
		return $out;
	}

	/* -------------------------------------------------------------- Redirects */

	/** Old permalinks WordPress still redirects (slug changes) → current permalink. */
	public static function old_slugs( WP_Post $post, string $permalink ): array {
		$out = array();
		foreach ( (array) get_post_meta( $post->ID, '_wp_old_slug' ) as $old ) {
			if ( ! is_string( $old ) || '' === $old || $old === $post->post_name ) {
				continue;
			}
			$clone            = clone $post;
			$clone->post_name = $old;
			$path             = self::permalink( $clone );
			if ( $path !== $permalink ) {
				$out[ $path ] = $permalink;
			}
		}
		return $out;
	}

	/**
	 * Everything the old site answered that the static site would 404 on.
	 *
	 * @param array<string,string> $old_slugs old path => new path
	 * @param string[]             $paths     Permalinks that exist (never redirected away).
	 * @param array<string,string> $query     "p=123" / "page_id=7" => permalink, collected while converting entries.
	 * @return array{paths:array<string,string>, query:array<string,string>}
	 */
	public static function redirects( array $old_slugs, array $paths, array $site, array $query = array() ): array {
		$exists = array_flip( $paths );
		$map    = array();
		foreach ( $old_slugs as $from => $to ) {
			if ( ! isset( $exists[ $from ] ) ) {
				$map[ $from ] = $to;
			}
		}
		if ( $site['features']['feeds'] ) {
			$map['/feed/']          = '/feed.xml';
			$map['/rss/']           = '/feed.xml';
			$map['/wp-sitemap.xml'] = '/sitemap.xml';
			$map['/sitemap_index.xml'] = '/sitemap.xml';
		}

		// Plain links (?p=123, ?page_id=7) keep working.
		ksort( $query, SORT_NATURAL );
		if ( $site['features']['search'] ) {
			$query['s'] = '/search/';
		}
		ksort( $map );
		return array( 'paths' => $map, 'query' => $query );
	}

	/** Netlify / Cloudflare Pages `_redirects` for the same rules (npm start reads redirects.json). */
	public static function redirects_file( array $r ): string {
		$out = "# Generated from WordPress. Same rules as redirects.json.\n";
		foreach ( $r['paths'] as $from => $to ) {
			$out .= $from . ' ' . $to . " 301\n";
		}
		return $out;
	}

	/**
	 * netlify.toml: build settings, plus the plain WordPress links (?p=123, ?page_id=7, ?s=) that `_redirects` can't
	 * hold, because Cloudflare Pages reads that file too and doesn't match query strings. Pure (unit-tested).
	 */
	public static function netlify_toml( array $r ): string {
		$str = fn( string $s ) => '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), preg_replace_callback( '/[^\x20-\x7e]+/', fn( $m ) => rawurlencode( $m[0] ), $s ) ) . '"';
		$out = "# Generated from WordPress. Netlify only: build settings and the old ?p=, ?page_id= and ?s= links.\n";
		$out .= "# Changed slugs and feed links are in public/_redirects, which Cloudflare Pages reads too.\n\n";
		$out .= "[build]\n  command = \"npm run build\"\n  publish = \"dist\"\n";
		foreach ( $r['query'] as $match => $to ) {
			if ( 's' === $match ) {
				$param = 's';
				$value = ':s';
				$to    = $to . '?q=:s';
			} else {
				list( $param, $value ) = array_pad( explode( '=', $match, 2 ), 2, '' );
			}
			// force: "/" exists as a file, and Netlify only applies unforced rules when nothing is there.
			$out .= "\n[[redirects]]\n  from = \"/\"\n  to = " . $str( $to ) . "\n  status = 301\n  force = true\n  query = { " . $param . ' = ' . $str( $value ) . " }\n";
		}
		return $out;
	}

	public static function frontmatter( array $data ): string {
		$out = "---\n";
		foreach ( $data as $k => $v ) {
			// JSON scalars and arrays are valid YAML.
			$out .= $k . ': ' . wp_json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
		}
		return $out . "---\n\n";
	}

	/** Shortcode-looking text that survived the_content (its plugin is gone). */
	public static function dead_shortcodes( string $html ): array {
		$text = wp_strip_all_tags( preg_replace( '#<(pre|code)[^>]*>.*?</\1>#is', '', $html ) );
		preg_match_all( '/\[(\/?)([a-z][a-z0-9_-]*)((?:\s+[^\]]*)?)\]/i', $text, $m, PREG_SET_ORDER );
		$names = array();
		foreach ( $m as $sc ) {
			$name = strtolower( $sc[2] );
			if ( '/' === $sc[1] || false !== strpos( $sc[3], '=' ) || preg_match( '/[_-]/', $name ) || in_array( $name, self::KNOWN_SHORTCODES, true ) ) {
				$names[ $name ] = true;
			}
		}
		return array_keys( $names );
	}

	/** Root-relative paths of files under the uploads dir referenced in $text. */
	public static function media_refs( string $text ): array {
		$base = rtrim( self::rel_url( wp_get_upload_dir()['baseurl'] ), '/' );
		preg_match_all( '#' . preg_quote( $base, '#' ) . '/[^"\'\s)<>?\#,]+#', $text, $m );
		return array_values( array_unique( array_map( 'urldecode', $m[0] ) ) );
	}

	/* ------------------------------------------------------------------ Files */

	/** The file behind a referenced upload ("/wp-content/uploads/2024/01/a.jpg"), or '' if it isn't in the uploads folder. */
	public static function media_file( string $rel ): string {
		$up   = wp_get_upload_dir();
		$base = rtrim( self::rel_url( $up['baseurl'] ), '/' );
		$real = realpath( $up['basedir'] . substr( $rel, strlen( $base ) ) );
		return $real && 0 === strpos( $real, (string) realpath( $up['basedir'] ) ) && is_file( $real ) ? $real : '';
	}

	/**
	 * Scripts the theme loads on the homepage (menu toggles and the like), copied next to its other assets, in page
	 * order. jQuery from wp-includes comes along when the page loads it, since theme scripts often need it.
	 *
	 * @return string[] Root-relative script URLs for site.json.
	 */
	public static function theme_scripts( string $site_dir, string $home_html ): array {
		if ( '' === $home_html || ! preg_match_all( '#<script\b[^>]*\bsrc=["\']([^"\']+)["\']#i', $home_html, $m ) ) {
			return array();
		}
		$roots = array( rtrim( self::rel_url( get_stylesheet_directory_uri() ), '/' ) => array( get_stylesheet_directory(), '/theme' ) );
		if ( get_template_directory() !== get_stylesheet_directory() ) {
			$roots[ rtrim( self::rel_url( get_template_directory_uri() ), '/' ) ] = array( get_template_directory(), '/theme-parent' );
		}
		$out = array();
		foreach ( $m[1] as $src ) {
			$path = (string) strtok( self::rel_url( html_entity_decode( $src ) ), '?' );
			foreach ( $roots as $from => list( $dir, $to ) ) {
				if ( 0 !== strpos( $path, $from . '/' ) ) {
					continue;
				}
				$rel  = substr( $path, strlen( $from ) );
				$real = realpath( $dir . $rel );
				if ( $real && 0 === strpos( $real, (string) realpath( $dir ) ) && preg_match( '/\.m?js$/', $real ) && filesize( $real ) <= self::MAX_ASSET_BYTES ) {
					wp_mkdir_p( dirname( $site_dir . '/public' . $to . $rel ) );
					copy( $real, $site_dir . '/public' . $to . $rel );
					$out[] = $to . $rel;
				}
				continue 2;
			}
			if ( preg_match( '#^/wp-includes/js/jquery/jquery(?:-migrate)?(?:\.min)?\.js$#', $path ) && is_readable( ABSPATH . ltrim( $path, '/' ) ) ) {
				wp_mkdir_p( dirname( $site_dir . '/public' . $path ) );
				copy( ABSPATH . ltrim( $path, '/' ), $site_dir . '/public' . $path );
				$out[] = $path;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Copy theme CSS, images and fonts, keeping relative paths so url() references still resolve.
	 * Returns the list of stylesheet hrefs for the layout.
	 */
	public static function copy_theme( string $site_dir, string $home_html ): array {
		$targets = array( get_stylesheet_directory() => '/theme' );
		if ( get_template_directory() !== get_stylesheet_directory() ) {
			$targets[ get_template_directory() ] = '/theme-parent';
		}
		foreach ( $targets as $dir => $public ) {
			self::copy_assets( $dir, $site_dir . '/public' . $public );
		}

		$sheets = array();
		wp_mkdir_p( $site_dir . '/public/theme' );

		// Block editor base styles + theme.json global styles (block themes rely on these).
		$lib = ABSPATH . WPINC . '/css/dist/block-library/style.min.css';
		if ( is_readable( $lib ) ) {
			copy( $lib, $site_dir . '/public/theme/wp-block-library.css' );
			$sheets[] = '/theme/wp-block-library.css';
		}
		if ( function_exists( 'wp_get_global_stylesheet' ) ) {
			$global = wp_get_global_stylesheet();
			if ( $global ) {
				file_put_contents( $site_dir . '/public/theme/global-styles.css', self::relative( $global ) );
				$sheets[] = '/theme/global-styles.css';
			}
		}

		// Stylesheets the theme actually enqueues, read from the rendered homepage.
		$theme_urls = array(
			rtrim( self::rel_url( get_stylesheet_directory_uri() ), '/' ) => '/theme',
			rtrim( self::rel_url( get_template_directory_uri() ), '/' )   => get_template_directory() !== get_stylesheet_directory() ? '/theme-parent' : '/theme',
		);
		$found = false;
		if ( $home_html && preg_match_all( '#<link\b[^>]*>#i', $home_html, $links ) ) {
			foreach ( $links[0] as $tag ) {
				if ( ! preg_match( '/rel=["\']?stylesheet/i', $tag ) || ! preg_match( '/href=["\']([^"\']+)["\']/i', $tag, $h ) ) {
					continue;
				}
				$href = strtok( self::rel_url( html_entity_decode( $h[1] ) ), '?' );
				foreach ( $theme_urls as $from => $to ) {
					if ( 0 === strpos( $href, $from . '/' ) ) {
						$sheets[] = $to . substr( $href, strlen( $from ) );
						$found    = true;
						continue 2;
					}
				}
				if ( preg_match( '#^https://fonts\.(googleapis|bunny)\.#', $h[1] ) ) {
					$sheets[] = html_entity_decode( $h[1] );
				}
			}
		}
		if ( ! $found ) {
			if ( isset( $targets[ get_template_directory() ] ) && get_template_directory() !== get_stylesheet_directory() && is_readable( get_template_directory() . '/style.css' ) ) {
				$sheets[] = '/theme-parent/style.css';
			}
			if ( is_readable( get_stylesheet_directory() . '/style.css' ) ) {
				$sheets[] = '/theme/style.css';
			}
		}
		return array_values( array_unique( $sheets ) );
	}

	private static function copy_assets( string $from, string $to ): void {
		if ( ! is_dir( $from ) ) {
			return;
		}
		$it = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $from, FilesystemIterator::SKIP_DOTS ),
				fn( $f ) => ! ( $f->isDir() && in_array( $f->getFilename(), array( 'node_modules', '.git', 'vendor', 'src', 'tests' ), true ) )
			)
		);
		$total = 0;
		foreach ( $it as $file ) {
			if ( ! $file->isFile() || ! in_array( strtolower( $file->getExtension() ), self::ASSET_EXT, true ) ) {
				continue;
			}
			$size = $file->getSize();
			if ( $size > self::MAX_ASSET_BYTES || $total + $size > 10 * self::MAX_ASSET_BYTES ) {
				continue;
			}
			$dest = $to . substr( $file->getPathname(), strlen( $from ) );
			wp_mkdir_p( dirname( $dest ) );
			if ( copy( $file->getPathname(), $dest ) ) {
				$total += $size;
			}
		}
	}

	/** Rendered homepage HTML via loopback request ('' if the server can't reach itself). */
	public static function fetch_home(): string {
		// Same rule core uses for loopback requests (cron, site health).
		$res = wp_remote_get( home_url( '/' ), array( 'timeout' => 20, 'sslverify' => apply_filters( 'https_local_ssl_verify', false ), 'redirection' => 3 ) );
		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			return '';
		}
		return (string) wp_remote_retrieve_body( $res );
	}
}
