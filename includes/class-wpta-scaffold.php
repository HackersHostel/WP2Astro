<?php
defined( 'ABSPATH' ) || exit;

/**
 * Static files of the generated Astro project.
 * Everything here is deterministic; only src/layouts/BaseLayout.astro may later be replaced by the AI step.
 */
class WPTA_Scaffold {

	const ASTRO_VERSION    = '^7.4.2';
	const PAGEFIND_VERSION = '^1.5.2';

	/** @return array<string,string> path => contents */
	public static function files( array $site ): array {
		$f    = $site['features'];
		$deps = array( 'astro' => self::ASTRO_VERSION );
		if ( $f['search'] ) {
			// A dependency, not a devDependency: hosts that install with NODE_ENV=production skip dev deps.
			$deps['pagefind'] = self::PAGEFIND_VERSION;
		}
		$pkg = array(
			'name'    => $site['slug'],
			'type'    => 'module',
			'version' => '1.0.0',
			'private' => true,
			'scripts' => array(
				'dev'     => 'astro dev',
				'build'   => 'astro build && node scripts/html-files.mjs' . ( $f['search'] ? ' && pagefind --site dist' : '' ),
				'preview' => 'astro preview',
				'start'   => 'node server.mjs',
			),
			'dependencies' => $deps,
			'engines'      => array( 'node' => '>=22.23.4' ),
		);

		$files = array(
			'package.json'                    => wp_json_encode( $pkg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n",
			'.gitignore'                      => "node_modules/\ndist/\n.astro/\n.env\n",
			'astro.config.mjs'                => self::astro_config(),
			'server.mjs'                      => self::server(),
			'scripts/html-files.mjs'          => self::html_files(),
			'src/content.config.ts'           => self::content_config(),
			'src/lib/content.ts'              => self::lib(),
			'src/layouts/BaseLayout.astro'    => self::default_layout(),
			'src/layouts/PostLayout.astro'    => self::post_layout(),
			'src/components/PostList.astro'   => self::post_list(),
			'src/pages/index.astro'           => self::index_page(),
			'src/pages/[...slug].astro'       => self::catch_all(),
			'src/pages/404.astro'             => self::not_found(),
			'src/components/SeoHead.astro'    => self::seo_head(),
			'src/content/items/.gitkeep'      => '',
			'README.md'                       => self::readme( $site ),
		);
		if ( $f['feeds'] ) {
			$files['src/pages/feed.xml.ts']    = self::feed();
			$files['src/pages/sitemap.xml.ts'] = self::sitemap();
			$files['src/pages/robots.txt.ts']  = self::robots();
		}
		if ( $f['search'] ) {
			$files['src/pages/search.astro'] = self::search_page();
		}
		return $files;
	}

	private static function astro_config(): string {
		return <<<'JS'
import { defineConfig } from 'astro/config';
import site from './src/site.json' with { type: 'json' };
import wpRedirects from './redirects.json' with { type: 'json' };

// Old WordPress URLs (changed slugs) as static redirect pages, for hosts without server rules.
// `npm start` (server.mjs) and public/_redirects answer the same URLs with real 301s.
const redirects = Object.fromEntries(
  Object.entries(wpRedirects.paths || {}).filter(([from, to]) => from.endsWith('/') && to.endsWith('/')),
);

// Same URLs as the old WordPress site: /2010/05/my-post/ etc.
export default defineConfig({
  site: site.url,
  trailingSlash: 'always',
  build: { format: 'directory' },
  redirects,
});

JS;
	}

	/**
	 * WordPress permalinks that end in .html (/2024/01/legacy.html) must stay files. Astro's directory build writes
	 * them as dist/2024/01/legacy.html/index.html; this moves each one to dist/2024/01/legacy.html.
	 */
	private static function html_files(): string {
		return <<<'JS'
// Runs after `astro build`: keeps WordPress permalinks that end in .html (/2024/01/legacy.html) at the same URL.
// Astro writes them as dist/2024/01/legacy.html/index.html; this moves each one to dist/2024/01/legacy.html.
import { existsSync, readdirSync, renameSync, rmdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

let moved = 0;
function walk(dir) {
  for (const name of readdirSync(dir)) {
    const path = join(dir, name);
    if (!statSync(path).isDirectory()) continue;
    if (/\.html?$/i.test(name) && existsSync(join(path, 'index.html')) && readdirSync(path).length === 1) {
      const tmp = `${path}.wp2astro-tmp`;
      renameSync(join(path, 'index.html'), tmp);
      rmdirSync(path);
      renameSync(tmp, path);
      moved++;
    } else {
      walk(path);
    }
  }
}
if (existsSync('dist')) walk('dist');
if (moved) console.log(`Kept ${moved} .html permalink(s) as files.`);

JS;
	}

	private static function server(): string {
		return <<<'JS'
// Zero-dependency static server for dist/ — used by `npm start` (e.g. FlyWP Node.js deployments).
import { createServer } from 'node:http';
import { createReadStream, existsSync, readFileSync, statSync } from 'node:fs';
import { extname, join, normalize, resolve } from 'node:path';

const root = resolve('dist');
const port = Number(process.env.PORT) || 4321;
const host = process.env.HOST || '0.0.0.0';
let redirects = { paths: {}, query: {} };
try { redirects = JSON.parse(readFileSync('redirects.json', 'utf8')); } catch {}
const types = {
  '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript', '.mjs': 'text/javascript',
  '.json': 'application/json', '.xml': 'application/xml', '.txt': 'text/plain; charset=utf-8',
  '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.gif': 'image/gif',
  '.webp': 'image/webp', '.avif': 'image/avif', '.ico': 'image/x-icon', '.pdf': 'application/pdf',
  '.woff': 'font/woff', '.woff2': 'font/woff2', '.ttf': 'font/ttf', '.otf': 'font/otf',
  '.mp4': 'video/mp4', '.webm': 'video/webm', '.mp3': 'audio/mpeg',
};

function send(res, file, status = 200) {
  res.writeHead(status, {
    'Content-Type': types[extname(file).toLowerCase()] || 'application/octet-stream',
    'Cache-Control': extname(file) === '.html' ? 'no-cache' : 'public, max-age=86400',
  });
  createReadStream(file).pipe(res);
}

// Header values must be ASCII: percent-encode non-ASCII paths ("/café/") for the Location header.
const ascii = (url) => url.replace(/[^\x00-\x7f]+/gu, encodeURIComponent);

function moved(res, to) {
  res.writeHead(301, { Location: ascii(to), 'Cache-Control': 'public, max-age=3600' }).end();
}

createServer((req, res) => {
  let path, url;
  try { url = new URL(req.url, 'http://x'); path = decodeURIComponent(url.pathname); } catch { path = '/'; }

  // WordPress links that have no file here: ?p=123, ?page_id=7, ?s=term, changed slugs, /feed/.
  if (url && path === '/' && url.search) {
    for (const [k, v] of url.searchParams) {
      if (k === 's' && redirects.query?.s) return moved(res, `${redirects.query.s}?q=${encodeURIComponent(v)}`);
      const to = redirects.query?.[`${k}=${v}`];
      if (to) return moved(res, to);
    }
  }
  const alt = path.endsWith('/') ? path.slice(0, -1) : path + '/';
  const to = redirects.paths?.[path] ?? redirects.paths?.[alt];
  if (to && to !== path) return moved(res, to);
  // "/x.html/" → "/x.html": .html permalinks are files, not folders.
  if (/\.html?\/$/i.test(path) && existsSync(normalize(join(root, alt)))) return moved(res, alt);

  const file = normalize(join(root, path));
  if (!file.startsWith(root)) { res.writeHead(403).end(); return; }

  if (existsSync(file) && statSync(file).isFile()) return send(res, file);
  if (existsSync(join(file, 'index.html'))) {
    if (!path.endsWith('/')) { res.writeHead(301, { Location: ascii(path + '/') }).end(); return; }
    return send(res, join(file, 'index.html'));
  }
  const notFound = join(root, '404.html');
  if (existsSync(notFound)) return send(res, notFound, 404);
  res.writeHead(404).end('Not found');
}).listen(port, host, () => console.log(`Serving dist/ on http://${host}:${port}`));

JS;
	}

	private static function content_config(): string {
		return <<<'TS'
import { defineCollection } from 'astro:content';
import { glob } from 'astro/loaders';
import { z } from 'astro/zod';

const entry = z.object({
  title: z.string(),
  date: z.coerce.date(),
  updated: z.coerce.date().optional(),
  permalink: z.string(),
  excerpt: z.string().default(''),
  author: z.string().default(''),
  authorSlug: z.string().optional(),
  categories: z.array(z.string()).default([]),
  tags: z.array(z.string()).default([]),
  featuredImage: z.string().optional(),
  type: z.string().default('post'), // post, page or a custom post type
  seoTitle: z.string().optional(),
  seoDescription: z.string().optional(),
  noindex: z.boolean().default(false),
  canonical: z.string().optional(),
  // Custom fields (post meta, ACF) when "Custom fields" is on in WP2Astro.
  fields: z.record(z.string(), z.any()).optional(),
});

export const collections = {
  posts: defineCollection({ loader: glob({ pattern: '**/*.md', base: './src/content/posts' }), schema: entry }),
  pages: defineCollection({ loader: glob({ pattern: '**/*.md', base: './src/content/pages' }), schema: entry }),
  // Custom post types, one folder per type: src/content/items/{type}/*.md
  items: defineCollection({ loader: glob({ pattern: '**/*.md', base: './src/content/items' }), schema: entry }),
};

TS;
	}

	private static function lib(): string {
		return <<<'TS'
import { getCollection } from 'astro:content';
import site from '../site.json';

export { site };

export async function getPosts() {
  const posts = await getCollection('posts');
  return posts.sort((a, b) => b.data.date.getTime() - a.data.date.getTime());
}

export async function getPages() {
  return getCollection('pages');
}

/** Custom post type entries, newest first; pass a type to filter. */
export async function getItems(type?: string) {
  const items = await getCollection('items');
  return items
    .filter((i) => !type || i.data.type === type)
    .sort((a, b) => b.data.date.getTime() - a.data.date.getTime());
}

/** Everything with a permalink. */
export async function getEntries() {
  return [...(await getPosts()), ...(await getPages()), ...(await getItems())];
}

/** "/2010/05/hello/" → "2010/05/hello" (Astro rest param). */
export function toParam(path: string) {
  return path.replace(/^\/+|\/+$/g, '') || undefined;
}

export function termName(kind: 'categories' | 'tags', slug: string) {
  return site[kind].find((t) => t.slug === slug)?.name ?? slug;
}

export function termPath(kind: 'categories' | 'tags', slug: string) {
  return site[kind].find((t) => t.slug === slug)?.path ?? '/';
}

/** Year and month ("2024", "01") of a date in the site's time zone, the way WordPress groups date archives. */
export function yearMonth(d: Date): [string, string] {
  const utc = (x: Date): [string, string] => [String(x.getUTCFullYear()), String(x.getUTCMonth() + 1).padStart(2, '0')];
  const tz = site.timezone || 'UTC';
  const offset = /^([+-])(\d{2}):(\d{2})$/.exec(tz);
  if (offset) {
    const minutes = (offset[1] === '-' ? -1 : 1) * (Number(offset[2]) * 60 + Number(offset[3]));
    return utc(new Date(d.getTime() + minutes * 60000));
  }
  try {
    const parts = new Intl.DateTimeFormat('en-US', { timeZone: tz, year: 'numeric', month: '2-digit' }).formatToParts(d);
    return [parts.find((p) => p.type === 'year')!.value, parts.find((p) => p.type === 'month')!.value];
  } catch {
    return utc(d);
  }
}

function monthName(year: string, month: string) {
  const d = new Date(Date.UTC(Number(year), Number(month) - 1, 15));
  try {
    return d.toLocaleDateString(site.language || 'en', { month: 'long', year: 'numeric', timeZone: 'UTC' });
  } catch {
    return `${year}-${month}`;
  }
}

/**
 * Every archive WordPress had besides the blog listing: categories, tags, authors, years and months, custom post
 * types. First pages only; [...slug].astro adds /page/2/ and on.
 */
export async function getArchives() {
  const posts = await getPosts();
  const items = await getItems();
  const out: { path: string; heading: string; posts: any[] }[] = [];
  for (const kind of ['categories', 'tags'] as const) {
    for (const term of site[kind]) {
      const list = posts.filter((p) => p.data[kind].includes(term.slug));
      if (list.length) out.push({ path: term.path, heading: term.name, posts: list });
    }
  }
  for (const author of site.authors || []) {
    const list = posts.filter((p) => p.data.authorSlug === author.slug);
    if (list.length && author.path) out.push({ path: author.path, heading: author.name, posts: list });
  }
  const dates = site.dateArchives as { year?: string; month?: string } | undefined;
  if (dates?.year && dates?.month) {
    const years = new Map<string, any[]>();
    const months = new Map<string, any[]>();
    for (const p of posts) {
      const [y, m] = yearMonth(p.data.date);
      years.set(y, [...(years.get(y) || []), p]);
      months.set(`${y}-${m}`, [...(months.get(`${y}-${m}`) || []), p]);
    }
    for (const [y, list] of years) out.push({ path: dates.year.replace('{y}', y), heading: y, posts: list });
    for (const [ym, list] of months) {
      const [y, m] = ym.split('-');
      out.push({ path: dates.month.replace('{y}', y).replace('{m}', m), heading: monthName(y, m), posts: list });
    }
  }
  for (const archive of site.archives || []) {
    const list = items.filter((i) => i.data.type === archive.type);
    if (archive.path && list.length) out.push({ path: archive.path, heading: archive.label, posts: list });
  }
  return out;
}

export function authorPath(slug?: string) {
  return (site.authors || []).find((a) => a.slug === slug)?.path;
}

export function formatDate(d: Date) {
  const opts: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'long', day: 'numeric' };
  try {
    return d.toLocaleDateString(site.language || 'en', { ...opts, timeZone: site.timezone || 'UTC' });
  } catch {
    return d.toLocaleDateString(site.language || 'en', opts);
  }
}

/** True if the body already shows this image (any size variant), so the layout doesn't repeat it. */
export function hasImage(body: string | undefined, url: string) {
  const base = url.split('/').pop()!.replace(/(-\d+x\d+)?\.\w+$/, '');
  return !!body && body.includes(base);
}

TS;
	}

	/** Used when AI is off or its layout fails validation. Uses WordPress-style class names so the copied theme CSS still applies. */
	public static function default_layout(): string {
		// phpcs:disable WordPress.WP.EnqueuedResources -- an Astro template written into the generated site, not output by WordPress.
		return <<<'ASTRO'
---
import site from '../site.json';
import SeoHead from '../components/SeoHead.astro';

interface Props {
  title?: string;
  description?: string;
  seoTitle?: string;
  image?: string;
  noindex?: boolean;
  canonical?: string;
  type?: string;
}
---
<!doctype html>
<html lang={site.language || 'en'}>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <SeoHead {...Astro.props} />
    {site.stylesheets.map((href) => <link rel="stylesheet" href={href} />)}
    <style is:global>
      .wp2astro-menu-toggle { display: none; }
      @media (max-width: 600px) {
        .wp2astro-menu-toggle { display: block; }
        .main-navigation:not(.toggled) > .menu { display: none; }
      }
    </style>
  </head>
  <body class="wp2astro">
    <div id="page" class="site">
      <header id="masthead" class="site-header">
        <div class="site-branding">
          <p class="site-title"><a href="/" rel="home">{site.title}</a></p>
          {site.description && <p class="site-description">{site.description}</p>}
        </div>
        {site.menu.length > 0 && (
          <nav id="site-navigation" class="main-navigation" aria-label="Primary">
            <button class="menu-toggle wp2astro-menu-toggle" aria-controls="primary-menu" aria-expanded="false">Menu</button>
            <ul id="primary-menu" class="menu">
              {site.menu.map((item) => <li class="menu-item"><a href={item.url}>{item.label}</a></li>)}
              {site.features?.search && <li class="menu-item menu-search"><a href="/search/">Search</a></li>}
            </ul>
          </nav>
        )}
      </header>
      <main id="primary" class="site-main">
        <slot />
      </main>
      <footer id="colophon" class="site-footer">
        <p>&copy; {new Date().getFullYear()} {site.title}</p>
      </footer>
    </div>
    <script is:inline>
      // Opens and closes the menu on small screens, unless the theme's own script already did on this click.
      let before = null;
      document.addEventListener('click', (e) => {
        const nav = e.target.closest('.wp2astro-menu-toggle')?.closest('nav');
        before = nav ? nav.classList.contains('toggled') : null;
      }, true);
      document.querySelectorAll('.wp2astro-menu-toggle').forEach((button) => {
        const nav = button.closest('nav');
        button.addEventListener('click', () => setTimeout(() => {
          if (before === null || nav.classList.contains('toggled') !== before) return;
          button.setAttribute('aria-expanded', String(nav.classList.toggle('toggled')));
        }));
      });
    </script>
  </body>
</html>

ASTRO;
		// phpcs:enable WordPress.WP.EnqueuedResources
	}

	private static function post_layout(): string {
		return <<<'ASTRO'
---
import BaseLayout from './BaseLayout.astro';
import { authorPath, formatDate, hasImage, site, termName, termPath } from '../lib/content';

const { entry } = Astro.props;
const d = entry.data;
const showFeatured = d.featuredImage && !hasImage(entry.body, d.featuredImage);
const searchable = site.features?.search && !d.noindex ? '' : undefined;
---
<BaseLayout
  title={d.title}
  description={d.seoDescription || d.excerpt}
  seoTitle={d.seoTitle}
  image={d.featuredImage}
  noindex={d.noindex}
  canonical={d.canonical}
  type={d.type === 'page' ? 'website' : 'article'}
>
  <article class={`${d.type} type-${d.type} hentry`} data-pagefind-body={searchable}>
    <header class="entry-header">
      <h1 class="entry-title" data-pagefind-meta="title">{d.title}</h1>
      {d.type !== 'page' && (
        <div class="entry-meta">
          <time datetime={d.date.toISOString()}>{formatDate(d.date)}</time>
          {d.author && <span class="byline"> · {authorPath(d.authorSlug) ? <a class="url fn n" href={authorPath(d.authorSlug)}>{d.author}</a> : d.author}</span>}
        </div>
      )}
    </header>
    {showFeatured && <figure class="post-thumbnail"><img src={d.featuredImage} alt="" /></figure>}
    <div class="entry-content">
      <slot />
    </div>
    {(d.categories.length > 0 || d.tags.length > 0) && (
      <footer class="entry-footer">
        {d.categories.length > 0 && (
          <span class="cat-links">Posted in {d.categories.map((c, i) => <>{i > 0 && ', '}<a href={termPath('categories', c)}>{termName('categories', c)}</a></>)}</span>
        )}
        {d.tags.length > 0 && (
          <span class="tags-links"> · Tagged {d.tags.map((t, i) => <>{i > 0 && ', '}<a href={termPath('tags', t)}>{termName('tags', t)}</a></>)}</span>
        )}
      </footer>
    )}
  </article>
</BaseLayout>

ASTRO;
	}

	private static function post_list(): string {
		return <<<'ASTRO'
---
import { formatDate } from '../lib/content';

interface Props {
  posts: any[];
  heading?: string;
  prev?: string;
  next?: string;
}
const { posts, heading, prev, next } = Astro.props;
---
{heading && <header class="page-header"><h1 class="page-title">{heading}</h1></header>}
{posts.map((p) => (
  <article class="post type-post hentry">
    <header class="entry-header">
      <h2 class="entry-title"><a href={p.data.permalink} rel="bookmark">{p.data.title}</a></h2>
      <div class="entry-meta"><time datetime={p.data.date.toISOString()}>{formatDate(p.data.date)}</time></div>
    </header>
    {p.data.excerpt && <div class="entry-summary"><p>{p.data.excerpt}</p></div>}
  </article>
))}
{(prev || next) && (
  <nav class="navigation posts-navigation" aria-label="Posts">
    <div class="nav-links">
      {next && <div class="nav-previous"><a href={next}>Older posts</a></div>}
      {prev && <div class="nav-next"><a href={prev}>Newer posts</a></div>}
    </div>
  </nav>
)}

ASTRO;
	}

	private static function index_page(): string {
		return <<<'ASTRO'
---
import { render } from 'astro:content';
import BaseLayout from '../layouts/BaseLayout.astro';
import PostLayout from '../layouts/PostLayout.astro';
import PostList from '../components/PostList.astro';
import { getPages, getPosts, site } from '../lib/content';

// WordPress "Your homepage displays": a static page, or the latest posts.
const front = site.frontPage ? (await getPages()).find((p) => p.data.permalink === '/') : undefined;
const Content = front ? (await render(front)).Content : null;

const posts = await getPosts();
const per = site.postsPerPage || 10;
const next = !front && posts.length > per ? `${site.blogBase}page/2/` : undefined;
---
{front && Content ? (
  <PostLayout entry={front}><Content /></PostLayout>
) : (
  <BaseLayout>
    <PostList posts={posts.slice(0, per)} next={next} />
  </BaseLayout>
)}

ASTRO;
	}

	private static function catch_all(): string {
		return <<<'ASTRO'
---
import { render } from 'astro:content';
import BaseLayout from '../layouts/BaseLayout.astro';
import PostLayout from '../layouts/PostLayout.astro';
import PostList from '../components/PostList.astro';
import { getArchives, getItems, getPages, getPosts, site, toParam } from '../lib/content';

export async function getStaticPaths() {
  const posts = await getPosts();
  const pages = await getPages();
  const items = await getItems();
  const per = site.postsPerPage || 10;
  const base = site.blogBase || '/';
  const paths: any[] = [];
  const used = new Set<string>(['/', base]); // home + posts page are listings
  const add = (path: string, props: any) => {
    if (used.has(path)) return; // two WordPress URLs can't map to one file
    used.add(path);
    paths.push({ params: { slug: toParam(path) }, props });
  };

  // Every post, page and custom post type entry at its original permalink.
  for (const entry of [...posts, ...pages, ...items]) {
    add(entry.data.permalink, { kind: 'entry', entry });
  }

  // A listing and its /page/2/, /page/3/… like WordPress, postsPerPage entries each.
  const listing = (path: string, list: any[], heading?: string, skipFirst = false) => {
    const total = Math.max(1, Math.ceil(list.length / per));
    const dir = path.endsWith('/') ? path : `${path}/`; // /category/news → /category/news/page/2/
    for (let n = 1; n <= total; n++) {
      if (n === 1 && skipFirst) continue;
      add(n === 1 ? path : `${dir}page/${n}/`, {
        kind: 'list',
        posts: list.slice((n - 1) * per, n * per),
        heading,
        prev: n > 1 ? (n === 2 ? path : `${dir}page/${n - 1}/`) : undefined,
        next: n < total ? `${dir}page/${n + 1}/` : undefined,
      });
    }
  };

  // The blog: /page/2/ (page 1 is index.astro), or /blog/ and /blog/page/2/ with a static front page.
  if (base !== '/') used.delete(base);
  listing(base, posts, undefined, base === '/');

  // Category, tag, author, year and month archives and custom post type archives at their WordPress URLs.
  for (const archive of await getArchives()) listing(archive.path, archive.posts, archive.heading);
  return paths;
}

const props = Astro.props;
const Content = props.kind === 'entry' ? (await render(props.entry)).Content : null;
---
{props.kind === 'entry' && Content ? (
  <PostLayout entry={props.entry}><Content /></PostLayout>
) : (
  <BaseLayout title={props.heading}>
    <PostList posts={props.posts} heading={props.heading} prev={props.prev} next={props.next} />
  </BaseLayout>
)}

ASTRO;
	}

	private static function not_found(): string {
		return <<<'ASTRO'
---
import BaseLayout from '../layouts/BaseLayout.astro';
---
<BaseLayout title="Page not found">
  <section class="error-404 not-found">
    <header class="page-header"><h1 class="page-title">Page not found</h1></header>
    <p><a href="/">Back to the homepage</a></p>
  </section>
</BaseLayout>

ASTRO;
	}

	/** Title, description, canonical, Open Graph, robots and feed links for every page. */
	private static function seo_head(): string {
		// phpcs:disable WordPress.WP.EnqueuedResources -- an Astro template written into the generated site, not output by WordPress.
		return <<<'ASTRO'
---
import site from '../site.json';

interface Props {
  title?: string;
  description?: string;
  seoTitle?: string;
  image?: string;
  noindex?: boolean;
  canonical?: string;
  type?: string;
}

const { title, description = site.description, seoTitle, image, noindex = false, canonical, type = 'website' } = Astro.props;
const fullTitle = seoTitle || (title ? `${title} – ${site.title}` : site.title);
const base = Astro.site ?? new URL(site.url);
// Pages at .html permalinks are built as folders, then moved to files (scripts/html-files.mjs): no trailing slash.
const url = new URL(canonical || Astro.url.pathname.replace(/(\.html?)\/$/i, '$1'), base).href;
const imageUrl = image ? new URL(image, base).href : undefined;
---
<title>{fullTitle}</title>
{description && <meta name="description" content={description} />}
<link rel="canonical" href={url} />
{noindex && <meta name="robots" content="noindex" />}
<meta property="og:site_name" content={site.title} />
<meta property="og:type" content={type} />
<meta property="og:title" content={seoTitle || title || site.title} />
{description && <meta property="og:description" content={description} />}
<meta property="og:url" content={url} />
{imageUrl && <meta property="og:image" content={imageUrl} />}
<meta name="twitter:card" content={imageUrl ? 'summary_large_image' : 'summary'} />
{site.features?.feeds && <link rel="alternate" type="application/rss+xml" title={site.title} href="/feed.xml" />}
<meta name="generator" content={site.generator} />
{(site.scripts || []).map((src) => <script is:inline src={src} defer></script>)}

ASTRO;
		// phpcs:enable WordPress.WP.EnqueuedResources
	}

	/** RSS 2.0 at /feed.xml (the old /feed/ URL redirects here). No dependencies. */
	private static function feed(): string {
		return <<<'TS'
import type { APIRoute } from 'astro';
import { getPosts, site } from '../lib/content';

const esc = (s: string) =>
  s.replace(/[<>&'"]/g, (c) => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', "'": '&apos;', '"': '&quot;' })[c]!);

export const GET: APIRoute = async ({ site: astroSite }) => {
  const base = astroSite ?? new URL(site.url);
  const posts = (await getPosts()).filter((p) => !p.data.noindex).slice(0, 20);
  const items = posts
    .map((p) => {
      const link = new URL(p.data.permalink, base).href;
      const desc = p.data.seoDescription || p.data.excerpt;
      return `    <item>
      <title>${esc(p.data.title)}</title>
      <link>${link}</link>
      <guid isPermaLink="true">${link}</guid>
      <pubDate>${p.data.date.toUTCString()}</pubDate>${desc ? `\n      <description>${esc(desc)}</description>` : ''}
    </item>`;
    })
    .join('\n');
  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>${esc(site.title)}</title>
    <link>${base.href}</link>
    <description>${esc(site.description || site.title)}</description>
    <language>${esc(site.language || 'en')}</language>
    <atom:link href="${new URL('/feed.xml', base).href}" rel="self" type="application/rss+xml" />
${items}
  </channel>
</rss>
`;
  return new Response(xml, { headers: { 'Content-Type': 'application/rss+xml; charset=utf-8' } });
};

TS;
	}

	/** One sitemap for every indexable URL (old /wp-sitemap.xml and /sitemap_index.xml redirect here). */
	private static function sitemap(): string {
		return <<<'TS'
import type { APIRoute } from 'astro';
import { getArchives, getEntries } from '../lib/content';
import site from '../site.json';

export const GET: APIRoute = async ({ site: astroSite }) => {
  const base = astroSite ?? new URL(site.url);
  const urls = new Map<string, Date | undefined>([['/', undefined]]);
  for (const e of await getEntries()) {
    if (!e.data.noindex && !e.data.canonical) urls.set(e.data.permalink, e.data.updated ?? e.data.date);
  }
  for (const a of await getArchives()) if (!urls.has(a.path)) urls.set(a.path, undefined);
  const body = [...urls]
    .map(([path, mod]) => `  <url><loc>${new URL(path, base).href}</loc>${mod ? `<lastmod>${mod.toISOString()}</lastmod>` : ''}</url>`)
    .join('\n');
  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${body}
</urlset>
`;
  return new Response(xml, { headers: { 'Content-Type': 'application/xml; charset=utf-8' } });
};

TS;
	}

	private static function robots(): string {
		return <<<'TS'
import type { APIRoute } from 'astro';
import site from '../site.json';

export const GET: APIRoute = ({ site: astroSite }) => {
  const base = astroSite ?? new URL(site.url);
  return new Response(`User-agent: *\nAllow: /\n\nSitemap: ${new URL('/sitemap.xml', base).href}\n`, {
    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
  });
};

TS;
	}

	/** Static full-text search with Pagefind (index built after `astro build`). Old ?s= links land here. */
	private static function search_page(): string {
		// phpcs:disable WordPress.WP.EnqueuedResources -- an Astro template written into the generated site, not output by WordPress.
		return <<<'ASTRO'
---
import BaseLayout from '../layouts/BaseLayout.astro';
---
<BaseLayout title="Search" noindex>
  <section class="search-page">
    <header class="page-header"><h1 class="page-title">Search</h1></header>
    <link rel="stylesheet" href="/pagefind/pagefind-ui.css" />
    <div id="search"></div>
    <script is:inline src="/pagefind/pagefind-ui.js"></script>
    <script is:inline>
      window.addEventListener('DOMContentLoaded', () => {
        const el = document.getElementById('search');
        if (!window.PagefindUI) {
          el.textContent = 'Search works after `npm run build` (the index is generated then).';
          return;
        }
        const ui = new PagefindUI({ element: '#search', showSubResults: true, resetStyles: false });
        const params = new URLSearchParams(location.search);
        const q = params.get('q') || params.get('s');
        if (q) ui.triggerSearch(q);
      });
    </script>
  </section>
</BaseLayout>

ASTRO;
		// phpcs:enable WordPress.WP.EnqueuedResources
	}

	private static function readme( array $site ): string {
		$title = $site['title'];
		$url   = $site['url'];
		$brand = WPTA_Settings::brand();
		$md    = <<<'MD'
# {{title}} — Astro

Converted from WordPress ({{url}}) with [{{brand}}]({{brand_url}}). Every post and page keeps its original URL, and old WordPress links (`?p=123`, changed slugs, `/feed/`) redirect to the right place.

## Develop

```bash
npm install
npm run dev      # http://localhost:4321
npm run build    # static site in dist/
npm start        # serve dist/ with redirects (PORT env, default 4321)
```

Requires Node.js 22.23.4+ (Astro 7).

## Edit content

- Posts: `src/content/posts/*.md`
- Pages: `src/content/pages/*.md`
- Custom post types: `src/content/items/{type}/*.md`
- Site title, menu, categories/tags: `src/site.json`
- Layout: `src/layouts/BaseLayout.astro`, styles from the old theme in `public/theme/`
- SEO tags: `src/components/SeoHead.astro` (titles and descriptions come from the WordPress SEO plugin)
- Redirects: `redirects.json` (used by `npm start`), `public/_redirects` (Netlify, Cloudflare Pages) and `netlify.toml` (Netlify: also `?p=123` links)
- Media: `public/wp-content/uploads/` (same paths as on WordPress)

See `CONVERSION_REPORT.md` for anything that needs a manual look.

## Keeping it in sync with WordPress

{{brand}} can push updates from WordPress again (manually or automatically after you publish). It only commits files that changed, and **files you edit in this repo are kept**: if you change a file here, later syncs leave it alone. To hand a file back to WordPress, restore it to the last synced version or delete it.

## Finish it with an AI agent

`AGENTS.md` (Codex, Cursor, and others) and `CLAUDE.md` + `.claude/skills/` (Claude Code) describe this project and the tasks left over from the conversion. Open the repo in Claude Code and run `/wp2astro-polish-layout` or `/wp2astro-fix-shortcodes` — it works with your Claude subscription.

## Deploy: FlyWP + DigitalOcean

1. In [FlyWP](https://flywp.com), connect a DigitalOcean account and create a server (a small droplet is enough).
2. Add a **Node.js** site and point it at this GitHub repository.
3. Build command: `npm install && npm run build` · Start command: `npm start`.
4. Point your domain at the server and enable SSL in FlyWP.

FlyWP's free plan covers one server and one site; the droplet itself is billed by DigitalOcean.

## Deploy: Render

Create a **Web Service** from this repository with the Node runtime. Build command: `npm install && npm run build` · Start command: `npm start`. Render sets `PORT`, which `server.mjs` listens on.

Any static host works too (build command `npm run build`, output `dist/`); Netlify and Cloudflare Pages read `public/_redirects`.

If the site moves to a new domain, change `url` in `src/site.json` so canonical links, the sitemap and the feed use it.

MD;
		return strtr(
			$md,
			array(
				'{{title}}'     => $title,
				'{{url}}'       => $url,
				'{{brand}}'     => $brand['name'],
				'{{brand_url}}' => $brand['url'],
			)
		);
	}
}
