<?php
/**
 * Writes a small Astro project from WPTA_Scaffold, without WordPress, with the URL cases that broke builds before:
 * a non-ASCII slug, a .html permalink, nested pages, a custom post type, paginated listings, and date, author and
 * term archives (including a post that falls in the next month in the site's time zone).
 *
 *   php dev/fixture.php <dir>      then: cd <dir> && npm install && npm run build && node <repo>/dev/check-dist.mjs
 *
 * Used by the "Astro build" job in .github/workflows/tests.yml.
 */

$dir = rtrim( $argv[1] ?? '', '/' );
if ( '' === $dir ) {
	fwrite( STDERR, "Usage: php dev/fixture.php <dir>\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPTA_URL', 'https://example.test/wp-content/plugins/wp2astro/' );
define( 'WPTA_VERSION', '0.0.0-fixture' );
define( 'MB_IN_BYTES', 1048576 );

function apply_filters( $hook, $value ) {
	return $value;
}
function wp_json_encode( $value, $flags = 0 ) {
	return json_encode( $value, $flags );
}

require __DIR__ . '/../includes/class-wpta-settings.php';
require __DIR__ . '/../includes/class-wpta-scaffold.php';
require __DIR__ . '/../includes/class-wpta-exporter.php';

$site = array(
	'title'        => 'Fixture Gazette',
	'description'  => 'Every URL case in one site',
	'url'          => 'https://example.test/',
	'slug'         => 'fixture-gazette',
	'language'     => 'en-US',
	'timezone'     => '+06:00',
	'postsPerPage' => 2,
	'frontPage'    => false,
	'blogBase'     => '/',
	'menu'         => array( array( 'label' => 'Home', 'url' => '/' ), array( 'label' => 'Parent', 'url' => '/parent/' ) ),
	'categories'   => array( array( 'slug' => 'news', 'name' => 'News', 'path' => '/category/news/' ) ),
	'tags'         => array( array( 'slug' => 'night', 'name' => 'Night', 'path' => '/tag/night/' ) ),
	'stylesheets'  => array(),
	'scripts'      => array(),
	'archives'     => array( array( 'type' => 'project', 'label' => 'Projects', 'path' => '/projects/' ) ),
	'authors'      => array( array( 'slug' => 'jane', 'name' => 'Jane Doe', 'path' => '/author/jane/' ) ),
	'dateArchives' => array( 'year' => '/{y}/', 'month' => '/{y}/{m}/' ),
	'features'     => array( 'seo' => true, 'feeds' => true, 'search' => true, 'agentKit' => false ),
	'generator'    => 'WP2Astro fixture',
);

$entry = function ( string $title, string $date, string $permalink, string $type = 'post', array $extra = array() ): string {
	$front = array_merge(
		array(
			'title'      => $title,
			'date'       => $date,
			'permalink'  => $permalink,
			'excerpt'    => $title . '.',
			'author'     => 'Jane Doe',
			'authorSlug' => 'jane',
			'categories' => 'post' === $type ? array( 'news' ) : array(),
			'tags'       => 'post' === $type ? array( 'night' ) : array(),
			'type'       => $type,
		),
		$extra
	);
	return WPTA_Exporter::frontmatter( $front ) . 'Body of *' . $title . "*.\n";
};

$files = WPTA_Scaffold::files( $site );
$files['src/site.json'] = json_encode( $site, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";

$posts = array(
	array( 'Night report one', '2024-01-05T21:00:00+06:00', '/2024/01/night-report-one/' ),
	array( 'Night report two', '2024-01-12T21:00:00+06:00', '/2024/01/night-report-two/' ),
	// 20:00 UTC on Jan 31 is 02:00 on Feb 1 in the site's +06:00: it belongs in /2024/02/.
	array( 'Just after midnight', '2024-01-31T20:00:00+00:00', '/2024/02/just-after-midnight/' ),
	array( 'An old .html permalink', '2024-01-20T10:00:00+06:00', '/2024/01/legacy.html' ),
	array( 'Spring report', '2024-03-02T10:00:00+06:00', '/2024/03/spring-report/' ),
	array( 'Last year', '2023-12-24T10:00:00+06:00', '/2023/12/last-year/' ),
);
foreach ( $posts as $i => list( $title, $date, $permalink ) ) {
	$files[ 'src/content/posts/post-' . $i . '.md' ] = $entry( $title, $date, $permalink, 'post', array( 'fields' => array( 'mood' => 'eerie', 'rating' => $i ) ) );
}
$files['src/content/pages/cafe.md']           = $entry( 'Café', '2024-01-01T00:00:00+06:00', '/café/', 'page' );
$files['src/content/pages/parent.md']         = $entry( 'Parent', '2024-01-01T00:00:00+06:00', '/parent/', 'page' );
$files['src/content/pages/parent--child.md']  = $entry( 'Child', '2024-01-01T00:00:00+06:00', '/parent/child/', 'page' );
$files['src/content/items/project/alpha.md']  = $entry( 'Alpha', '2024-02-02T00:00:00+06:00', '/projects/alpha/', 'project' );

$redirects = WPTA_Exporter::redirects(
	array( '/2024/01/old-slug/' => '/2024/01/night-report-one/' ),
	array_column( $posts, 2 ),
	$site,
	array( 'p=1' => '/2024/01/night-report-one/', 'page_id=7' => '/café/' )
);
$files['redirects.json']    = json_encode( $redirects, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
$files['public/_redirects'] = WPTA_Exporter::redirects_file( $redirects );
$files['netlify.toml']      = WPTA_Exporter::netlify_toml( $redirects );

foreach ( $files as $rel => $contents ) {
	$path = $dir . '/' . $rel;
	if ( ! is_dir( dirname( $path ) ) ) {
		mkdir( dirname( $path ), 0777, true );
	}
	file_put_contents( $path, $contents );
}
echo 'Wrote ' . count( $files ) . " files to {$dir}\n";
