<p align="center"><img src="assets/logo.svg" width="72" alt=""></p>

# WP2Astro

**Turn a WordPress site into a fast Astro site — same URLs, same look — push it to GitHub, and keep it in sync.**
A WordPress plugin by [Hackers Hostel](https://hackershostel.com). Free and open source (GPL-2.0-or-later).

- **Install like any plugin.** No Node, CLI or application passwords on your side: upload the zip, open **WP2Astro**,
  click **Convert**.
- **Same URLs.** Every post, page, custom post type entry, category and tag keeps its permalink. Old links still work:
  changed slugs, `?p=123`, `?page_id=7`, `?s=search` and `/feed/` redirect to the right place.
- **Same look.** The theme's CSS, images and fonts are copied, and an AI model rebuilds the header, menu and footer as
  an Astro layout from the rendered homepage.
- **Use the AI you already pay for — or none.** Your **ChatGPT plan** (Sign in with ChatGPT, no API key), or
  **OpenRouter** with one-click connect, in-plugin credit balance and **Buy credits** (Claude, GPT, and free models).
  With no AI you still get a working site. Nothing heavy runs on your server.
- **GitHub in one click.** Connect with GitHub's device flow (or a token), and the plugin creates the repo and pushes.
- **Smart sync.** Re-runs push only changed files, removed posts are removed, and **files you edit in the repo are
  never overwritten**. Turn on auto-sync and the repo updates a couple of minutes after you publish.
- **SEO kept.** Titles and descriptions from Yoast SEO, Rank Math or All in One SEO, canonical and Open Graph tags,
  RSS feed, sitemap, robots.txt.
- **Search.** Static full-text search with Pagefind.
- **Finish it with your Claude subscription.** The repo ships an agent kit (`AGENTS.md`, `CLAUDE.md`, Claude Code
  skills) so Claude Code or Codex can polish what the conversion couldn't.

## WP2Astro vs. WP Astro MCP

[WP Astro MCP](https://github.com/vapvarun/wp-astro-mcp) is an MCP server you run from Claude Code. WP2Astro runs inside
WordPress. Both are open source; pick what fits.

| | WP2Astro | WP Astro MCP |
|---|---|---|
| Setup | Install a plugin | Clone, build, configure an MCP server; WordPress application password |
| Who can run it | Anyone with wp-admin | Developers with Node.js 20+ and Claude Code |
| AI | Optional: ChatGPT plan, OpenRouter (Claude, GPT, free models), or Claude Code via the agent kit | Claude Code session |
| Keeps the theme's look | Yes (theme CSS + AI-built layout) | Uses its own Astro templates |
| Shortcodes | Rendered by WordPress itself; dead ones cleaned by AI | Rule-based handling |
| Old URLs, `?p=`, changed slugs | Yes | Redirect files for several hosts |
| SEO plugins, feeds, sitemap, search | Yes | Yes (plus JSON Feed) |
| Custom post types | Yes | Yes (plus ACF fields) |
| Sync | Changed files only, keeps edits made in the repo, auto-sync on publish | Incremental, scheduled or webhooks |
| Credentials | Encrypted in the database | Plain text config file |
| Scale | One site per install | Built for networks of many sites |

## Install

1. Download `wp2astro.zip` from the [latest release](https://github.com/HackersHostel/wp2astro/releases/latest).
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the zip, **Activate**.
3. Open **WP2Astro** in the admin menu.

Requires WordPress 6.5+ and PHP 8.0+. Updates arrive in **Dashboard → Updates** like any other plugin, from this repo's
releases.

## Use it

1. **AI model** (optional) — pick one:
   - **Your subscription:** *ChatGPT plan* — click **Sign in with ChatGPT**, approve, paste back the address the tab
     lands on. *Claude plan* — Claude subscriptions can't be used inside other apps, so the generated repo ships an
     agent kit: open it in Claude Code and it finishes the site on your plan.
   - **Pay as you go:** *OpenRouter* — **Connect OpenRouter**, pick a model (default Claude Haiku; free ones are
     marked, or use `openrouter/free`), see your balance and **Buy credits** from the dashboard.
   - **No AI:** a working site with a clean default layout.
2. **GitHub** (optional) — **Connect GitHub**, or paste a fine-grained token with *Administration* and *Contents*
   read & write. Without GitHub you download a zip.
3. **What to build** — SEO, feeds, search, custom post types, agent kit, auto-sync.
4. **Convert to Astro.** Later runs become **Sync now**.

From the command line (and for coding agents):

```bash
wp wp2astro convert                 # convert + push
wp wp2astro convert --no-ai --no-github --zip=./site.zip
wp wp2astro status                  # provider, GitHub, recent runs
```

## What you get

```
src/content/posts|pages|items/   Markdown with frontmatter (items = custom post types)
src/site.json                    title, menu, terms, archives, feature flags
src/layouts/BaseLayout.astro     the theme shell (AI-built or default)
src/components/SeoHead.astro     title, description, canonical, Open Graph
src/pages/                       permalinks, archives, pagination, feed.xml, sitemap.xml, robots.txt, search
public/theme/                    the old theme's CSS, images, fonts
public/wp-content/uploads/       referenced media at their original paths
redirects.json, public/_redirects  old WordPress URLs → new ones
server.mjs                       zero-dependency server for `npm start` (with real 301s)
AGENTS.md, CLAUDE.md, .claude/   agent kit
CONVERSION_REPORT.md             anything that needs a look
```

## Deploy

- **FlyWP + DigitalOcean:** add a Node.js site from the repo; build `npm install && npm run build`, start `npm start`.
  FlyWP's free plan covers one server and one site; the DigitalOcean droplet is paid.
- **Any static host:** build `npm run build`, publish `dist/`. Netlify and Cloudflare Pages read `public/_redirects`.

## Privacy

WP2Astro only talks to services you set up, and only when you convert, sync or test a connection. See
[docs/privacy.md](docs/privacy.md) for exactly what is sent where.

## Docs

- [AI providers](docs/providers.md) — every option, including how Sign in with ChatGPT works on a remote server
- [GitHub](docs/github.md) — connecting, permissions, and registering the OAuth app (maintainers)
- [Sync](docs/sync.md) — what gets pushed, what is kept, auto-sync
- [White-label](docs/white-label.md) — shipping WP2Astro under your brand
- [Privacy and external services](docs/privacy.md)
- [Releasing](docs/releasing.md)

## Not converted

Comments, forms, WooCommerce and other features that need a server; sidebar widgets; ACF fields beyond the rendered
content.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Security problems: [SECURITY.md](SECURITY.md). Help: [SUPPORT.md](SUPPORT.md).

## License

[GPL-2.0-or-later](LICENSE). Free to use. A white-label licence from Hackers Hostel covers shipping it under your own
brand — see [docs/white-label.md](docs/white-label.md).
