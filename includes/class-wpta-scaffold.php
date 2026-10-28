<?php
defined( 'ABSPATH' ) || exit;

/**
 * Static files of the generated Astro project.
 * Everything here is deterministic; only src/layouts/BaseLayout.astro may later be replaced by the AI step.
 */
class WPTA_Scaffold {

	const ASTRO_VERSION = '^7.4.2';

	/** @return array<string,string> path => contents */
	public static function files( array $site ): array {
		$pkg = array(
			'name'    => $site['slug'],
			'type'    => 'module',
			'version' => '1.0.0',
			'private' => true,
			'scripts' => array(
				'dev'     => 'astro dev',
				'build'   => 'astro build',
				'preview' => 'astro preview',
				'start'   => 'node server.mjs',
			),
			'dependencies' => array( 'astro' => self::ASTRO_VERSION ),
			'engines'      => array( 'node' => '>=22.23.4' ),
		);

		return array(
			'package.json'                    => wp_json_encode( $pkg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n",
			'.gitignore'                      => "node_modules/\ndist/\n.astro/\n.env\n",
			'astro.config.mjs'                => self::astro_config(),
			'server.mjs'                      => self::server(),
			'src/content.config.ts'           => self::content_config(),
			'src/lib/content.ts'              => self::lib(),
			'src/layouts/BaseLayout.astro'    => self::default_layout(),
			'src/layouts/PostLayout.astro'    => self::post_layout(),
			'src/components/PostList.astro'   => self::post_list(),
			'src/pages/index.astro'           => self::index_page(),
			'src/pages/[...slug].astro'       => self::catch_all(),
			'src/pages/404.astro'             => self::not_found(),
			'README.md'                       => self::readme( $site ),
		);
	}

	private static function astro_config(): string {
		return <<<'JS'
import { defineConfig } from 'astro/config';
import site from './src/site.json' with { type: 'json' };

// Same URLs as the old WordPress site: /2010/05/my-post/ etc.
export default defineConfig({
  site: site.url,
  trailingSlash: 'always',
  build: { format: 'directory' },
});

JS;
	}

	private static function server(): string {
		return <<<'JS'
// Zero-dependency static server for dist/ — used by `npm start` (e.g. FlyWP Node.js deployments).
import { createServer } from 'node:http';
import { createReadStream, existsSync, statSync } from 'node:fs';
import { extname, join, normalize, resolve } from 'node:path';

const root = resolve('dist');
const port = Number(process.env.PORT) || 4321;
const host = process.env.HOST || '0.0.0.0';
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

createServer((req, res) => {
  let path;
  try { path = decodeURIComponent(new URL(req.url, 'http://x').pathname); } catch { path = '/'; }
  const file = normalize(join(root, path));
  if (!file.startsWith(root)) { res.writeHead(403).end(); return; }

  if (existsSync(file) && statSync(file).isFile()) return send(res, file);
  if (existsSync(join(file, 'index.html'))) {
    if (!path.endsWith('/')) { res.writeHead(301, { Location: path + '/' }).end(); return; }
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
  categories: z.array(z.string()).default([]),
  tags: z.array(z.string()).default([]),
  featuredImage: z.string().optional(),
  type: z.enum(['post', 'page']).default('post'),
});

export const collections = {
  posts: defineCollection({ loader: glob({ pattern: '**/*.md', base: './src/content/posts' }), schema: entry }),
  pages: defineCollection({ loader: glob({ pattern: '**/*.md', base: './src/content/pages' }), schema: entry }),
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
		return <<<'ASTRO'
---
import site from '../site.json';

interface Props {
  title?: string;
  description?: string;
}

const { title, description = site.description } = Astro.props;
const pageTitle = title ? `${title} – ${site.title}` : site.title;
---
<!doctype html>
<html lang={site.language || 'en'}>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{pageTitle}</title>
    {description && <meta name="description" content={description} />}
    {site.stylesheets.map((href) => <link rel="stylesheet" href={href} />)}
  </head>
  <body class="wp-to-astro">
    <div id="page" class="site">
      <header id="masthead" class="site-header">
        <div class="site-branding">
          <p class="site-title"><a href="/" rel="home">{site.title}</a></p>
          {site.description && <p class="site-description">{site.description}</p>}
        </div>
        {site.menu.length > 0 && (
          <nav id="site-navigation" class="main-navigation" aria-label="Primary">
            <ul class="menu">
              {site.menu.map((item) => <li class="menu-item"><a href={item.url}>{item.label}</a></li>)}
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
  </body>
</html>

ASTRO;
	}

	private static function post_layout(): string {
		return <<<'ASTRO'
---
import BaseLayout from './BaseLayout.astro';
import { formatDate, hasImage, termName, termPath } from '../lib/content';

const { entry } = Astro.props;
const d = entry.data;
const showFeatured = d.featuredImage && !hasImage(entry.body, d.featuredImage);
---
<BaseLayout title={d.title} description={d.excerpt}>
  <article class={`${d.type} type-${d.type} hentry`}>
    <header class="entry-header">
      <h1 class="entry-title">{d.title}</h1>
      {d.type === 'post' && (
        <div class="entry-meta">
          <time datetime={d.date.toISOString()}>{formatDate(d.date)}</time>
          {d.author && <span class="byline"> · {d.author}</span>}
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
import { getPages, getPosts, site, toParam } from '../lib/content';

export async function getStaticPaths() {
  const posts = await getPosts();
  const pages = await getPages();
  const per = site.postsPerPage || 10;
  const base = site.blogBase || '/';
  const paths: any[] = [];

  // Every post and page at its original permalink.
  for (const entry of [...posts, ...pages]) {
    const p = entry.data.permalink;
    if (p === '/' || p === base) continue; // home + posts page are listings
    paths.push({ params: { slug: toParam(p) }, props: { kind: 'entry', entry } });
  }

  // Blog listing with WordPress-style pagination: /page/2/ or /blog/page/2/.
  const total = Math.max(1, Math.ceil(posts.length / per));
  for (let n = 1; n <= total; n++) {
    if (n === 1 && base === '/') continue; // handled by index.astro
    const path = n === 1 ? base : `${base}page/${n}/`;
    paths.push({
      params: { slug: toParam(path) },
      props: {
        kind: 'list',
        posts: posts.slice((n - 1) * per, n * per),
        prev: n > 1 ? (n === 2 ? base : `${base}page/${n - 1}/`) : undefined,
        next: n < total ? `${base}page/${n + 1}/` : undefined,
      },
    });
  }

  // Category and tag archives at their WordPress URLs.
  for (const kind of ['categories', 'tags'] as const) {
    for (const term of site[kind]) {
      const list = posts.filter((p) => p.data[kind].includes(term.slug));
      if (!list.length) continue;
      paths.push({ params: { slug: toParam(term.path) }, props: { kind: 'list', posts: list, heading: term.name } });
    }
  }
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

	private static function readme( array $site ): string {
		$title = $site['title'];
		$url   = $site['url'];
		return <<<MD
# {$title} — Astro

Converted from WordPress ({$url}) with **WP to Astro**. Every post and page keeps its original URL.

## Develop

```bash
npm install
npm run dev      # http://localhost:4321
npm run build    # static site in dist/
npm start        # serve dist/ (PORT env, default 4321)
```

Requires Node.js 22.23.4+ (Astro 7).

## Edit content

- Posts: `src/content/posts/*.md`
- Pages: `src/content/pages/*.md`
- Site title, menu, categories/tags: `src/site.json`
- Layout: `src/layouts/BaseLayout.astro`, styles from the old theme in `public/theme/`
- Media: `public/wp-content/uploads/` (same paths as on WordPress)

See `CONVERSION_REPORT.md` for anything that needs a manual look.

## Deploy: FlyWP + DigitalOcean

1. In [FlyWP](https://flywp.com), connect a DigitalOcean account and create a server (a small droplet is enough).
2. Add a **Node.js** site and point it at this GitHub repository.
3. Build command: `npm install && npm run build` · Start command: `npm start`.
4. Point your domain at the server and enable SSL in FlyWP.

FlyWP's free plan covers one server and one site; the droplet itself is billed by DigitalOcean.

MD;
	}
}
