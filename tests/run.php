<?php
/**
 * Unit tests for the pure parts of WP2Astro (no WordPress needed): php tests/run.php
 * The full pipeline is tested end to end with the harness in dev/ (see dev/README.md).
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MB_IN_BYTES', 1048576 );

function wp_strip_all_tags( $s ) {
	return trim( strip_tags( preg_replace( '#<(script|style)[^>]*?>.*?</\1>#si', '', $s ) ) );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function trailingslashit( $s ) {
	return rtrim( (string) $s, '/\\' ) . '/';
}
function esc_url( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}

require __DIR__ . '/../includes/class-wpta-sync.php';
require __DIR__ . '/../includes/class-wpta-ai.php';
require __DIR__ . '/../includes/class-wpta-llm.php';
require __DIR__ . '/../includes/class-wpta-github.php';
require __DIR__ . '/../includes/class-wpta-markdown.php';
require __DIR__ . '/../includes/class-wpta-exporter.php';
require __DIR__ . '/../includes/class-wpta-updater.php';
require __DIR__ . '/../includes/class-wpta-admin.php';
require __DIR__ . '/../includes/class-wpta-media.php';
require __DIR__ . '/../includes/class-wpta-storage.php';

$fails = 0;
$count = 0;
function check( string $name, $actual, $expected ): void {
	global $fails, $count;
	++$count;
	if ( $actual !== $expected ) {
		++$fails;
		echo "FAIL {$name}\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
	}
}

/* ------------------------------------------------------------------ Media */

$doc = "---\nfeaturedImage: \"/wp-content/uploads/2024/01/a.jpg\"\n---\n\n![x](/wp-content/uploads/2024/01/a.jpg)\n\n<img src=\"/wp-content/uploads/b.png\" srcset=\"/wp-content/uploads/b.png 1x, /wp-content/uploads/b-2x.png 2x\">\nSee /wp-content/uploads-old/ and https://other.test/wp-content/uploads/c.jpg\n";
$out = WPTA_Media::rewrite( $doc, '/wp-content/uploads', 'https://cdn.test/wp-content/uploads' );
check( 'media: frontmatter image', str_contains( $out, 'featuredImage: "https://cdn.test/wp-content/uploads/2024/01/a.jpg"' ), true );
check( 'media: markdown image', str_contains( $out, '](https://cdn.test/wp-content/uploads/2024/01/a.jpg)' ), true );
check( 'media: srcset entries', str_contains( $out, 'srcset="https://cdn.test/wp-content/uploads/b.png 1x, https://cdn.test/wp-content/uploads/b-2x.png 2x"' ), true );
check( 'media: other paths and hosts untouched', str_contains( $out, 'See /wp-content/uploads-old/ and https://other.test/wp-content/uploads/c.jpg' ), true );
check( 'media: no base, no change', WPTA_Media::rewrite( $doc, '/wp-content/uploads', '' ), $doc );
$one = "![a](/wp-content/uploads/a.jpg) ![b](/wp-content/uploads/a.jpg.webp)\nfeaturedImage: \"/wp-content/uploads/a.jpg\"";
check( 'media: relink one file only', WPTA_Media::relink( $one, '/wp-content/uploads/a.jpg', 'https://wp.test/wp-content/uploads/a.jpg' ), "![a](https://wp.test/wp-content/uploads/a.jpg) ![b](/wp-content/uploads/a.jpg.webp)\nfeaturedImage: \"https://wp.test/wp-content/uploads/a.jpg\"" );
check( 'media: lfs pointer', WPTA_Media::lfs_pointer( str_repeat( 'a', 64 ), 12345 ), "version https://git-lfs.github.com/spec/v1\noid sha256:" . str_repeat( 'a', 64 ) . "\nsize 12345\n" );
$tmp = tempnam( sys_get_temp_dir(), 'wpta' );
file_put_contents( $tmp, str_repeat( "media bytes\n", 5000 ) );
check( 'media: streamed blob id equals the in-memory one', WPTA_GitHub::blob_sha_file( $tmp ), WPTA_GitHub::blob_sha( (string) file_get_contents( $tmp ) ) );
unlink( $tmp );

// AWS's documented Signature Version 4 example (GET Object, examplebucket/test.txt, 2013-05-24).
$empty = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
check(
	'storage: SigV4 matches the AWS example',
	WPTA_Storage::sigv4( 'GET', 'examplebucket.s3.amazonaws.com', '/test.txt', array( 'range' => 'bytes=0-9', 'x-amz-content-sha256' => $empty, 'x-amz-date' => '20130524T000000Z' ), $empty, 'AKIAIOSFODNN7EXAMPLE', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY', 'us-east-1' ),
	'AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20130524/us-east-1/s3/aws4_request, SignedHeaders=host;range;x-amz-content-sha256;x-amz-date, Signature=f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41'
);

/* ------------------------------------------------------------ Commit tree */

$p = WPTA_Sync::plan( array( 'a.md' => 'a2', 'b.md' => 'b1', 'new.md' => 'n1', 'kept.md' => 'k-ours' ), array( 'a.md' => 'a1', 'b.md' => 'b1', 'gone.md' => 'g1', 'kept.md' => 'k-edited', 'theirs.md' => 't1' ), array( 'a.md' => 'a1', 'b.md' => 'b1', 'gone.md' => 'g1', 'kept.md' => 'k-old' ) );
check( 'tree: only changed and deleted paths, sorted', WPTA_Sync::tree_changes( $p ), array(
	array( 'path' => 'a.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'a2' ),
	array( 'path' => 'gone.md', 'mode' => '100644', 'type' => 'blob', 'sha' => null ),
	array( 'path' => 'new.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'n1' ),
) );
check( 'tree: nothing to send when nothing changed', WPTA_Sync::tree_changes( WPTA_Sync::plan( array( 'a.md' => 'a1' ), array( 'a.md' => 'a1' ), array( 'a.md' => 'a1' ) ) ), array() );

/* ----------------------------------------------------------- Date archives */

check( 'dates: trailing-slash structure', WPTA_Exporter::date_patterns( '/1970/', '/1970/12/' ), array( 'year' => '/{y}/', 'month' => '/{y}/{m}/' ) );
check( 'dates: no trailing slash (/%postname%)', WPTA_Exporter::date_patterns( '/1970', '/1970/12' ), array( 'year' => '/{y}/', 'month' => '/{y}/{m}/' ) );
check( 'dates: with a front base', WPTA_Exporter::date_patterns( '/blog/1970/', '/blog/1970/12/' ), array( 'year' => '/blog/{y}/', 'month' => '/blog/{y}/{m}/' ) );

/* -------------------------------------------------------- Netlify redirects */

$toml = WPTA_Exporter::netlify_toml( array( 'paths' => array(), 'query' => array( 'p=123' => '/2010/05/hello/', 'page_id=7' => '/café/', 's' => '/search/' ) ) );
check( 'netlify: build settings', str_contains( $toml, "[build]\n  command = \"npm run build\"\n  publish = \"dist\"\n" ), true );
check( 'netlify: ?p= rule', str_contains( $toml, "[[redirects]]\n  from = \"/\"\n  to = \"/2010/05/hello/\"\n  status = 301\n  force = true\n  query = { p = \"123\" }\n" ), true );
check( 'netlify: non-ASCII target is percent-encoded', str_contains( $toml, 'to = "/caf%C3%A9/"' ), true );
check( 'netlify: search keeps the term', str_contains( $toml, "to = \"/search/?q=:s\"\n  status = 301\n  force = true\n  query = { s = \":s\" }" ), true );

/* ------------------------------------------------------- HTML to Markdown */

$md = fn( string $html ) => WPTA_Markdown::convert( $html );
check( 'md: brackets stay text', $md( '<p>See [note](sic)</p>' ), "See \\[note\\](sic)\n" );
check( 'md: an entity typed as text', $md( '<p>&amp;copy;</p>' ), "&amp;copy;\n" );
check( 'md: underscores stay text', $md( '<p>__dunder__ and snake_case</p>' ), "\\_\\_dunder\\_\\_ and snake\\_case\n" );
check( 'md: a paragraph of dashes is not a rule', $md( '<p>---</p>' ), "\\---\n" );
check( 'md: spaced dashes are not a rule', $md( '<p>- - -</p>' ), "\\- - -\n" );
check( 'md: === after a line break is not a heading', $md( '<p>Title<br>===</p>' ), "Title  \n\\===\n" );
check( 'md: --- after a line break is not a heading', $md( '<p>Title<br>---</p>' ), "Title  \n\\---\n" );
check( 'md: a list marker after a line break', $md( '<p>one<br>- two<br>3. three</p>' ), "one  \n\\- two  \n3\\. three\n" );
check( 'md: link text keeps its underscores', $md( '<p><a href="/a_b/">x_y</a></p>' ), "[x\\_y](/a_b/)\n" );
check( 'md: emphasis still converts', $md( '<p><strong>bold</strong> and <em>it</em></p>' ), "**bold** and *it*\n" );
check( 'md: a real heading is untouched', $md( '<h2>Two_words</h2>' ), "## Two\\_words\n" );

/* ------------------------------------------------------------ Dashboard */

$done  = array( 'status' => 'done', 'started' => 100, 'entries' => 20, 'files' => 81, 'repo' => 'https://github.com/a/b' );
$state = fn( array $runs, int $changed = 0, bool $gh = true, bool $auto = false, string $running = '' ) => WPTA_Admin::hero_state( $runs, $changed, $gh, $auto, $running )['state'];
check( 'hero: never run', $state( array() ), 'new' );
check( 'hero: running wins', $state( array( $done ), 500, true, false, 'abcdefabcdef' ), 'running' );
$open = array( 'abcdefabcdef' => array( 'status' => 'running', 'trigger' => 'manual', 'started' => 300 ), 'x' => $done );
check( 'hero: a manual run can be started again', WPTA_Admin::hero_state( $open, 0, true, false, 'abcdefabcdef' )['restart'], true );
$open['abcdefabcdef']['trigger'] = 'auto';
check( 'hero: a background run is not restarted', WPTA_Admin::hero_state( $open, 0, true, false, 'abcdefabcdef' )['restart'], false );
check( 'hero: nothing running, nothing to restart', WPTA_Admin::hero_state( $open, 0, true, false, '' )['restart'], false );
check( 'hero: last run failed', $state( array( array( 'status' => 'error', 'started' => 200, 'error' => 'x' ), $done ) ), 'failed' );
check( 'hero: content changed after the last good run', $state( array( $done ), 150 ), 'behind' );
check( 'hero: changes before the last run are synced', $state( array( $done ), 90 ), 'ok' );
check( 'hero: zip only, then GitHub connected', $state( array( array( 'status' => 'done', 'started' => 100 ) ) ), 'new' );
check( 'hero: zip only, no GitHub', $state( array( array( 'status' => 'done', 'started' => 100 ) ), 0, false ), 'ok' );
check( 'hero: the last good run counts, not a running one', WPTA_Admin::hero_state( array( array( 'status' => 'running', 'started' => 300 ), $done ), 0, true, false, '' )['last'], $done );
check( 'hero: auto-sync needs GitHub', WPTA_Admin::hero_state( array( $done ), 0, false, true, '' )['badge'], 'Up to date' );
check( 'hero: auto-sync badge', WPTA_Admin::hero_state( array( $done ), 0, true, true, '' )['badge'], 'Auto-sync is on' );

check( 'item: commit', WPTA_Admin::run_item( array( 'status' => 'done', 'commit' => 'c', 'changed' => 2, 'deleted' => 1, 'kept' => array( 'src/layouts/BaseLayout.astro' ) ) ), array( 'kind' => 'commit', 'title' => 'Synced to GitHub', 'text' => '2 files changed, 1 removed. Kept BaseLayout.astro, edited in the repo.' ) );
check( 'item: nothing to push', WPTA_Admin::run_item( $done )['text'], 'All 81 files are already on GitHub.' );
check( 'item: zip', WPTA_Admin::run_item( array( 'status' => 'done', 'entries' => 1, 'media' => 6 ) )['text'], '1 entry and 6 media files.' );
check( 'item: stopped', WPTA_Admin::run_item( array( 'status' => 'error', 'error' => 'Stopped. Nothing was pushed.' ) )['title'], 'Stopped' );
check( 'item: failed', WPTA_Admin::run_item( array( 'status' => 'error', 'error' => 'GitHub: 409' ) )['title'], 'Run failed' );

/* ------------------------------------------------------------ Sync plan */

// First sync onto an auto_init repo: everything ours, README replaced.
$p = WPTA_Sync::plan( array( 'README.md' => 'r2', 'a.md' => 'a1' ), array( 'README.md' => 'r1' ), array() );
check( 'first: tree', $p['tree'], array( 'README.md' => 'r2', 'a.md' => 'a1' ) );
check( 'first: upload', $p['upload'], array( 'README.md', 'a.md' ) );
check( 'first: nothing kept', $p['kept'], array() );
check( 'first: not noop', $p['noop'], false );

// Unchanged rerun: noop.
$p = WPTA_Sync::plan( array( 'a.md' => 'a1', 'b.md' => 'b1' ), array( 'a.md' => 'a1', 'b.md' => 'b1' ), array( 'a.md' => 'a1', 'b.md' => 'b1' ) );
check( 'rerun: noop', $p['noop'], true );
check( 'rerun: no uploads', $p['upload'], array() );

// One post changed in WordPress, one deleted in WordPress.
$p = WPTA_Sync::plan( array( 'a.md' => 'a2' ), array( 'a.md' => 'a1', 'b.md' => 'b1' ), array( 'a.md' => 'a1', 'b.md' => 'b1' ) );
check( 'change: tree', $p['tree'], array( 'a.md' => 'a2' ) );
check( 'change: changed', $p['changed'], array( 'a.md' ) );
check( 'change: deleted', $p['deleted'], array( 'b.md' ) );

// File edited in the repo since our last push: keep the repo version even though WordPress changed too.
$p = WPTA_Sync::plan( array( 'layout.astro' => 'L3', 'a.md' => 'a1' ), array( 'layout.astro' => 'L-human', 'a.md' => 'a1' ), array( 'layout.astro' => 'L1', 'a.md' => 'a1' ) );
check( 'edit: kept', $p['kept'], array( 'layout.astro' ) );
check( 'edit: tree keeps human', $p['tree']['layout.astro'], 'L-human' );
check( 'edit: noop', $p['noop'], true );
$m = WPTA_Sync::next_manifest( array( 'layout.astro' => 'L3', 'a.md' => 'a1' ), $p, array( 'layout.astro' => 'L1', 'a.md' => 'a1' ) );
check( 'edit: manifest remembers our old sha', $m['layout.astro'], 'L1' );

// Files someone added to the repo are never deleted; an edited file WordPress dropped is kept too.
$p = WPTA_Sync::plan( array( 'a.md' => 'a1' ), array( 'a.md' => 'a1', 'notes.md' => 'n1', 'old.md' => 'o-human' ), array( 'a.md' => 'a1', 'old.md' => 'o1' ) );
check( 'user files stay', $p['tree'], array( 'a.md' => 'a1', 'notes.md' => 'n1', 'old.md' => 'o-human' ) );
check( 'user files: none deleted', $p['deleted'], array() );

// A file someone created at a path we now generate (not first sync): theirs wins.
$p = WPTA_Sync::plan( array( 'a.md' => 'a1', 'new.md' => 'mine' ), array( 'a.md' => 'a1', 'new.md' => 'theirs' ), array( 'a.md' => 'a1' ) );
check( 'collision: kept', $p['kept'], array( 'new.md' ) );

// Background sync keep-list: layout stays as in the repo.
$p = WPTA_Sync::plan( array( 'src/layouts/BaseLayout.astro' => 'default' ), array( 'src/layouts/BaseLayout.astro' => 'ai' ), array( 'src/layouts/BaseLayout.astro' => 'ai' ), array( 'src/layouts/BaseLayout.astro' ) );
check( 'keep-list: tree', $p['tree'], array( 'src/layouts/BaseLayout.astro' => 'ai' ) );
check( 'keep-list: noop', $p['noop'], true );

// Same content at two paths uploads once; content already on GitHub isn't uploaded.
$p = WPTA_Sync::plan( array( 'x.png' => 's1', 'y.png' => 's1', 'z.png' => 'old' ), array( 'w.png' => 'old' ), array( 'w.png' => 'old' ) );
check( 'dedupe uploads', $p['upload'], array( 'x.png' ) );

/* ----------------------------------------------------------------- Git */

check( 'git blob sha (empty)', WPTA_GitHub::blob_sha( '' ), 'e69de29bb2d1d6434b8b29ae775ad8c2e48c5391' );
check( 'git blob sha (hello)', WPTA_GitHub::blob_sha( "hello\n" ), 'ce013625030ba8dba906f756967f9e9ca394464a' );

/* -------------------------------------------------------------- Layout */

$ai_layout = <<<'ASTRO'
---
import site from '../site.json';
const { title, description = site.description } = Astro.props;
---
<html lang={site.language}>
<head>
<meta charset="utf-8" />
<title>{title}</title>
<meta name="description" content={description} />
<meta property="og:title" content={title} />
{site.stylesheets.map((href) => <link rel="stylesheet" href={href} />)}
</head>
<body><nav>{site.menu.map((i) => <a href={i.url}>{i.label}</a>)}</nav><slot /></body>
</html>
ASTRO;
check( 'valid layout', WPTA_AI::validate_layout( $ai_layout ), '' );
$final = WPTA_AI::finalize_layout( $ai_layout );
check( 'finalize: imports SeoHead', str_contains( $final, "import SeoHead from '../components/SeoHead.astro';" ), true );
check( 'finalize: renders SeoHead in head', (bool) preg_match( '#<SeoHead \{\.\.\.Astro\.props\} />\s*</head>#', $final ), true );
check( 'finalize: drops model <title>', str_contains( $final, '<title>' ), false );
check( 'finalize: drops model meta description', str_contains( $final, 'name="description"' ), false );
check( 'finalize: keeps charset', str_contains( $final, '<meta charset="utf-8" />' ), true );
check( 'finalize: idempotent', WPTA_AI::finalize_layout( $final ), $final );
check( 'finalize: still valid', WPTA_AI::validate_layout( $final ), '' );

$headless = "---\nimport site from '../site.json';\n---\n<html lang=\"en\"><body>{site.stylesheets}{site.menu}<slot /></body></html>";
check( 'finalize: adds <head> when missing', str_contains( WPTA_AI::finalize_layout( $headless ), "<html lang=\"en\">\n<head>\n  <SeoHead" ), true );

check( 'reject hard-coded archive link', WPTA_AI::validate_layout( str_replace( '<slot />', '<slot /><a href="/2026/10/">October</a>', $ai_layout ) ), 'hard-coded link to /2026/10/… (widgets and archive links must be left out).' );
check( 'reject hard-coded category link', WPTA_AI::validate_layout( str_replace( '<slot />', '<slot /><a href="/category/x/">X</a>', $ai_layout ) ), 'hard-coded link to /category/… (widgets and archive links must be left out).' );
check( 'reject className', WPTA_AI::validate_layout( str_replace( '<nav>', '<nav className="x">', $ai_layout ) ), 'contains forbidden code: className=' );
check( 'allow a copied theme script', WPTA_AI::validate_layout( str_replace( '<slot />', '<slot /><script is:inline src="/theme/js/navigation.js" defer></script>', $ai_layout ) ), '' );
check( 'allow jQuery from wp-includes', WPTA_AI::validate_layout( str_replace( '<slot />', '<slot /><script src="/wp-includes/js/jquery/jquery.min.js"></script>', $ai_layout ) ), '' );
check( 'reject an inline script', WPTA_AI::validate_layout( str_replace( '<slot />', '<slot /><script>alert(1)</script>', $ai_layout ) ), 'contains a <script> that is not one of the theme\'s copied scripts.' );
check( 'reject an outside script', WPTA_AI::validate_layout( str_replace( '<slot />', '<slot /><script src="https://evil.test/x.js"></script>', $ai_layout ) ), 'contains a <script> that is not one of the theme\'s copied scripts.' );
check( 'reject foreign import', WPTA_AI::validate_layout( str_replace( "import site from '../site.json';", "import site from '../site.json';\nimport X from 'x';", $ai_layout ) ), "import from 'x' is not allowed." );

/* -------------------------------------------------------------- LLM text */

check( 'clean think + fence', WPTA_LLM::clean( "<think>hmm</think>Sure!\n```astro\n---\nx\n---\n```\nDone" ), "---\nx\n---\n" );
check( 'clean keeps markdown fences when strict', WPTA_LLM::clean( "Text\n```js\ncode\n```\nMore", false ), "Text\n```js\ncode\n```\nMore\n" );

/* ---------------------------------------------------------- Shortcodes */

check( 'dead shortcodes', WPTA_Exporter::dead_shortcodes( '<p>[vc_row][vc_column]Hi[/vc_column][/vc_row] [button url="x"]Go[/button] [not a shortcode]</p><pre>[gallery]</pre>' ), array( 'vc_row', 'vc_column', 'button' ) );

/* ---------------------------------------------------------------- Paths */

check( 'decode non-ASCII slug', WPTA_Exporter::decode_path( '/2024/01/%e6%97%a5%e6%9c%ac/' ), '/2024/01/日本/' );
check( 'decode accented slug', WPTA_Exporter::decode_path( '/caf%C3%A9/' ), '/café/' );
check( 'keep encoded ASCII', WPTA_Exporter::decode_path( '/a%2Fb%20c/' ), '/a%2Fb%20c/' );
check( 'keep query string', WPTA_Exporter::decode_path( '/s%c3%a9/?q=%c3%a9#x' ), '/sé/?q=%c3%a9#x' );
check( 'keep invalid UTF-8', WPTA_Exporter::decode_path( '/bad%e6%97/' ), '/bad%e6%97/' );
check( 'leave absolute URLs', WPTA_Exporter::decode_path( 'https://example.com/caf%c3%a9/' ), 'https://example.com/caf%c3%a9/' );
check( 'leave protocol-relative URLs', WPTA_Exporter::decode_path( '//cdn.example.com/caf%c3%a9/' ), '//cdn.example.com/caf%c3%a9/' );

/* ------------------------------------------------------------ readme.txt */

// WordPress.org readme rules (https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/),
// and readme.txt kept in step with the plugin header and CHANGELOG.md.
$readme = (string) file_get_contents( __DIR__ . '/../readme.txt' );
$plugin = (string) file_get_contents( __DIR__ . '/../wp2astro.php' );
$field  = fn( string $src, string $name ) => preg_match( '/^[ *]*' . preg_quote( $name, '/' ) . ':[ \t]*(.+?)[ \t]*$/m', $src, $m ) ? $m[1] : null;
preg_match( "/define\( 'WPTA_VERSION', '([^']+)' \);/", $plugin, $m );
$version = $m[1];
check( 'readme: name header', (bool) preg_match( '/\A=== WP2Astro ===\n/', $readme ), true );
check( 'readme: stable tag = plugin version', $field( $readme, 'Stable tag' ), $version );
check( 'readme: header version = WPTA_VERSION', $field( $plugin, 'Version' ), $version );
foreach ( array( 'Requires at least', 'Requires PHP', 'License', 'License URI' ) as $f ) {
	check( "readme: {$f} matches the plugin header", $field( $readme, $f ), $field( $plugin, $f ) );
}
check( 'readme: Tested up to is a major version', (bool) preg_match( '/^\d+\.\d+$/', (string) $field( $readme, 'Tested up to' ) ), true );
check( 'readme: contributors set', '' !== trim( (string) $field( $readme, 'Contributors' ) ), true );
$tags = array_map( 'trim', explode( ',', (string) $field( $readme, 'Tags' ) ) );
check( 'readme: 1-5 tags', count( $tags ) >= 1 && count( $tags ) <= 5, true );
preg_match( '/\A(?:.*\n)+?\n(.+)\n\n== Description ==/', $readme, $m );
$short = $m[1] ?? '';
check( 'readme: short description <= 150 chars', '' !== $short && mb_strlen( $short ) <= 150, true );
check( 'readme: short description has no markup', (bool) preg_match( '/[*`\[\]<>#=]/', $short ), false );
preg_match_all( '/^== (.+) ==$/m', $readme, $m );
check( 'readme: sections', $m[1], array( 'Description', 'Installation', 'Frequently Asked Questions', 'Screenshots', 'Changelog', 'Upgrade Notice', 'External services' ) );
check( 'readme: changelog has this version', str_contains( $readme, "== Changelog ==\n\n= {$version} =" ), true );
check( 'CHANGELOG.md has this version', str_contains( (string) file_get_contents( __DIR__ . '/../CHANGELOG.md' ), "\n## {$version}\n" ), true );
preg_match( '/== Upgrade Notice ==\n(.*?)(?=\n== |\z)/s', $readme, $m );
foreach ( preg_split( '/^= .+ =$/m', $m[1] ?? '', -1, PREG_SPLIT_NO_EMPTY ) as $notice ) {
	check( 'readme: upgrade notice <= 300 chars', mb_strlen( trim( $notice ) ) <= 300, true );
}
preg_match( '/== Screenshots ==\n\n(.*?)\n\n==/s', $readme, $m );
$captions = preg_match_all( '/^\d+\. /m', $m[1] ?? '' );
$shots    = glob( __DIR__ . '/../.wordpress-org/screenshot-*.png' );
if ( $shots ) {
	check( 'readme: a caption for every screenshot', count( $shots ), $captions );
}

$sections = WPTA_Updater::readme_sections( $readme );
check( 'details: tabs', array_keys( $sections ), array( 'description', 'installation', 'faq', 'changelog' ) );
check( 'details: external services in the description', str_contains( $sections['description'], '<h3>External services</h3>' ), true );
check( 'details: faq questions', substr_count( $sections['faq'], '<h4>' ) >= 6, true );
$html = WPTA_Updater::readme_sections( "== Description ==\n\nSee [docs](https://x.test/a?b=1&c=2) and `code` **now** <script>.\n\n* one\n  wrapped\n* two\n\n= Q? =\n\n1. a\n2. b\n" )['description'];
check( 'details: markdown subset', $html, '<p>See <a href="https://x.test/a?b=1&amp;c=2">docs</a> and <code>code</code> <strong>now</strong> &lt;script&gt;.</p><ul><li>one wrapped</li><li>two</li></ul><h4>Q?</h4><ol><li>a</li><li>b</li></ol>' );

echo $fails ? "\n{$fails} of {$count} checks failed.\n" : "All {$count} checks passed.\n";
exit( $fails ? 1 : 0 );
