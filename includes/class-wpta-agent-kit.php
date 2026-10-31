<?php
defined( 'ABSPATH' ) || exit;

/**
 * Agent kit for the generated repo: AGENTS.md (Codex, Cursor, Copilot…), CLAUDE.md and Claude Code skills.
 * This is how a Claude subscription finishes the job: the user's own Claude Code does the polishing,
 * which is allowed, unlike reusing subscription credentials inside this plugin.
 */
class WPTA_Agent_Kit {

	/** @return array<string,string> path => contents */
	public static function files( array $site, array $report ): array {
		return array(
			'AGENTS.md'                                         => self::agents( $site, $report ),
			'CLAUDE.md'                                         => self::claude(),
			'.claude/skills/wp2astro-polish-layout/SKILL.md'   => self::skill_layout( $site ),
			'.claude/skills/wp2astro-fix-shortcodes/SKILL.md'  => self::skill_shortcodes(),
			'.claude/skills/wp2astro-verify/SKILL.md'          => self::skill_verify(),
		);
	}

	/** Open tasks, derived from the conversion report. Deterministic, so unchanged sites don't produce commits. */
	private static function tasks( array $report ): array {
		$tasks = array();
		$fell  = false;
		foreach ( (array) $report['layout'] as $line ) {
			if ( false !== stripos( $line, 'default layout' ) ) {
				$fell = true;
			}
		}
		if ( $fell ) {
			$tasks[] = 'The layout is the generic default, not the old theme. Rebuild `src/layouts/BaseLayout.astro` to match the live WordPress site (skill: `wp2astro-polish-layout`).';
		} else {
			$tasks[] = 'Compare the AI-built `src/layouts/BaseLayout.astro` with the live WordPress site and fix differences in header, navigation and footer (skill: `wp2astro-polish-layout`).';
		}
		if ( $report['unconverted'] ) {
			$list = array_map( fn( $f ) => '`' . $f['path'] . '`', array_slice( $report['unconverted'], 0, 20 ) );
			$tasks[] = 'Replace dead shortcodes in ' . implode( ', ', $list ) . ( count( $report['unconverted'] ) > 20 ? ' and more' : '' ) . ' (skill: `wp2astro-fix-shortcodes`).';
		}
		if ( $report['cleaned'] ) {
			$tasks[] = 'Review the AI shortcode cleanup in ' . count( $report['cleaned'] ) . ' file(s) listed in `CONVERSION_REPORT.md`.';
		}
		if ( $report['media_failed'] ) {
			$tasks[] = 'Some media files were not copied (see `CONVERSION_REPORT.md` → Media not copied). Find replacements or remove the references.';
		}
		$tasks[] = 'Run `npm install && npm run build` and check that every permalink in the report builds (skill: `wp2astro-verify`).';
		return $tasks;
	}

	private static function agents( array $site, array $report ): string {
		$brand = WPTA_Settings::brand()['name'];
		$tasks = '';
		foreach ( self::tasks( $report ) as $i => $t ) {
			$tasks .= ( $i + 1 ) . '. ' . $t . "\n";
		}
		$md = <<<'MD'
# AGENTS.md

This is an Astro site converted from the WordPress site {{url}} by {{brand}}.
The WordPress site may still be online: use it as the visual reference.

## Commands

- `npm install`
- `npm run dev` — dev server on http://localhost:4321
- `npm run build` — static build into `dist/` (plus the Pagefind search index when search is on)
- `npm start` — serve `dist/` with the WordPress redirects (`redirects.json`)

## Layout of the project

- `src/content/posts|pages|items/` — Markdown entries. `permalink` in the frontmatter is the URL; it must not change.
- `src/site.json` — site title, menu, categories, tags, custom post type archives, feature flags.
- `src/layouts/BaseLayout.astro` — page shell (header, nav, footer). Keep `<SeoHead {...Astro.props} />` in `<head>` and `<slot />` where content goes.
- `src/layouts/PostLayout.astro`, `src/components/` — entry template, lists, SEO tags.
- `public/theme/` — the old theme's CSS, images and fonts. Prefer editing CSS here over inline styles.
- `public/wp-content/uploads/` — media at their WordPress paths.
- `CONVERSION_REPORT.md` — what the conversion could not do.

## Rules

- Never change permalinks or delete entries; old URLs must keep working.
- No client-side JavaScript frameworks; this is a static site.
- Keep the old theme's class names and ids so its CSS keeps applying.
- WordPress can sync again. Files you edit here are kept on later syncs, so edit freely.

## Open tasks

{{tasks}}
MD;
		return strtr( $md, array( '{{url}}' => $site['url'], '{{brand}}' => $brand, '{{tasks}}' => $tasks ) );
	}

	private static function claude(): string {
		return <<<'MD'
# CLAUDE.md

@AGENTS.md

## Claude Code skills in this repo

- `/wp2astro-polish-layout` — make the Astro layout match the live WordPress site.
- `/wp2astro-fix-shortcodes` — replace dead WordPress shortcodes listed in `CONVERSION_REPORT.md`.
- `/wp2astro-verify` — build the site and check every old URL still exists.

MD;
	}

	private static function skill_layout( array $site ): string {
		$md = <<<'MD'
---
name: wp2astro-polish-layout
description: Make src/layouts/BaseLayout.astro match the live WordPress site's header, navigation and footer. Use when the converted site doesn't look like the original.
---

# Polish the layout

Reference site: {{url}}

1. Fetch the live homepage and one post page. Note the header (logo, site title, header image), menu, sidebar position and footer.
2. Run `npm run dev` and open the same pages at http://localhost:4321. If you can take screenshots, compare them side by side.
3. Edit `src/layouts/BaseLayout.astro`:
   - Keep `import site from '../site.json'`, the `<SeoHead {...Astro.props} />` line in `<head>`, the stylesheet loop over `site.stylesheets`, and `<slot />`.
   - Render navigation from `site.menu`; don't hard-code links.
   - Reuse the theme's element structure, ids and classes so `public/theme/*.css` applies.
   - Images from the theme live under `/theme/…`.
4. Leave sidebar widgets out unless they are static content (they need a server on WordPress).
5. Run `npm run build` to confirm it still builds.

MD;
		return str_replace( '{{url}}', $site['url'], $md );
	}

	private static function skill_shortcodes(): string {
		return <<<'MD'
---
name: wp2astro-fix-shortcodes
description: Replace dead WordPress shortcodes ([vc_row], [button], [gallery]…) left in Markdown content, using CONVERSION_REPORT.md as the list. Use after a WordPress conversion.
---

# Fix dead shortcodes

1. Read `CONVERSION_REPORT.md` → "Dead shortcodes left in content" (and "Shortcodes cleaned by AI" to review earlier fixes).
2. For each file, replace every leftover shortcode with plain Markdown or HTML:
   - `[caption]…[/caption]` → the image, then the caption in italics
   - `[gallery …]` → the images it referenced if you can find them under `public/wp-content/uploads/`, otherwise remove it
   - `[embed]URL[/embed]`, `[youtube …]`, `[vimeo …]` → a link to the URL (or an `<iframe>` for YouTube/Vimeo)
   - buttons → a normal Markdown link
   - layout wrappers (rows, columns, tabs, accordions, boxes) → remove the tags, keep the text
   - anything that rendered nothing → remove it
3. Don't change any other text, the frontmatter or the `permalink`.
4. Run `npm run build`.

MD;
	}

	private static function skill_verify(): string {
		return <<<'MD'
---
name: wp2astro-verify
description: Build the converted Astro site and check that every WordPress permalink exists in dist/, the feed and sitemap build, and redirects resolve. Use before deploying.
---

# Verify the conversion

1. `npm install && npm run build`. Fix any build error first.
2. For every Markdown file in `src/content/`, read `permalink` from its frontmatter and check `dist/<permalink>/index.html` exists.
3. Check `dist/404.html`, and if present `dist/feed.xml`, `dist/sitemap.xml` and `dist/pagefind/`.
4. Start `npm start` and request a few entries from `redirects.json` (`paths` and `query`); each should answer 301 to an existing page.
5. Report missing URLs with the file they came from.

MD;
	}
}
