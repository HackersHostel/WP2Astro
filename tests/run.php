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

require __DIR__ . '/../includes/class-wpta-sync.php';
require __DIR__ . '/../includes/class-wpta-ai.php';
require __DIR__ . '/../includes/class-wpta-llm.php';
require __DIR__ . '/../includes/class-wpta-github.php';
require __DIR__ . '/../includes/class-wpta-markdown.php';
require __DIR__ . '/../includes/class-wpta-exporter.php';

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
check( 'reject foreign import', WPTA_AI::validate_layout( str_replace( "import site from '../site.json';", "import site from '../site.json';\nimport X from 'x';", $ai_layout ) ), "import from 'x' is not allowed." );

/* -------------------------------------------------------------- LLM text */

check( 'clean think + fence', WPTA_LLM::clean( "<think>hmm</think>Sure!\n```astro\n---\nx\n---\n```\nDone" ), "---\nx\n---\n" );
check( 'clean keeps markdown fences when strict', WPTA_LLM::clean( "Text\n```js\ncode\n```\nMore", false ), "Text\n```js\ncode\n```\nMore\n" );

/* ---------------------------------------------------------- Shortcodes */

check( 'dead shortcodes', WPTA_Exporter::dead_shortcodes( '<p>[vc_row][vc_column]Hi[/vc_column][/vc_row] [button url="x"]Go[/button] [not a shortcode]</p><pre>[gallery]</pre>' ), array( 'vc_row', 'vc_column', 'button' ) );

echo $fails ? "\n{$fails} of {$count} checks failed.\n" : "All {$count} checks passed.\n";
exit( $fails ? 1 : 0 );
