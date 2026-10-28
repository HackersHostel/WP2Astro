# Deploy the site

The repository (or zip) is a standard Astro project. You can host it as a small Node.js app, which also answers old
WordPress links with real redirects, or as plain static files on any static host.

| | Node.js app (`npm start`) | Static host (`dist/`) |
|---|---|---|
| Hosts | FlyWP + DigitalOcean, any VPS, Render, Railway… | Netlify, Cloudflare Pages, GitHub Pages, any web server… |
| Changed slugs | 301 redirects | 301 on Netlify and Cloudflare Pages (`public/_redirects`); redirect pages elsewhere |
| `/feed/`, `/wp-sitemap.xml` | 301 redirects | 301 on Netlify and Cloudflare Pages; not redirected elsewhere |
| `?p=123`, `?page_id=7`, `?s=…` | 301 redirects | Not redirected |
| Search | Yes | Yes |

All options need Node.js 22.23.4 or later to build.

## Deploy with FlyWP and DigitalOcean

[FlyWP](https://flywp.com) manages a server on your DigitalOcean account. FlyWP's free plan covers one server and one
site; DigitalOcean bills the server (droplet).

1. In FlyWP, connect your DigitalOcean account and create a server.
2. Add a **Node.js** site and connect it to your WP2Astro repository on GitHub.
3. Set the build command to:

   ```bash
   npm install && npm run build
   ```

4. Set the start command to:

   ```bash
   npm start
   ```

5. Point your domain at the site and deploy.

`npm start` runs `server.mjs`, a small server with no dependencies. It serves `dist/` and answers every old WordPress
URL listed in `redirects.json` with a 301 redirect. It listens on the port in the `PORT` environment variable, or 4321.

## Deploy to a static host

1. Create a new site on your host and connect your WP2Astro repository.
2. Set the build command to `npm run build` and the publish (output) directory to `dist`.
3. Deploy.

Netlify and Cloudflare Pages read `public/_redirects`, so changed slugs and old feed and sitemap links return 301
redirects. Other hosts get small redirect pages for changed slugs instead. Plain links such as `?p=123` need
`npm start`.

## Redeploy after a sync

Most hosts rebuild on every push to GitHub. With auto-sync on, a post you publish in WordPress reaches the live site
a few minutes later: two minutes for the sync, plus your host's build time.

## Use a different address than WordPress

Canonical links, the sitemap and the feed use the WordPress address, stored as `url` in `src/site.json`. If the Astro
site lives at another address, change `url` there in the repository. Later syncs keep your edit, but then menu and
category changes in WordPress stop reaching `src/site.json` (see [Keep the site in sync](sync.md)).

## Related

- [Keep the site in sync](sync.md)
- [Troubleshooting](troubleshooting.md)
