<p align="center"><img src=".wordpress-org/banner-1544x500.png" alt="WP2Astro — WordPress in, fast Astro site out. Same URLs, same look, synced to GitHub."></p>

<p align="center">
  <a href="https://github.com/HackersHostel/wp2astro/releases/latest"><img src="https://img.shields.io/github/v/release/HackersHostel/wp2astro?label=release" alt="Latest release"></a>
  <a href="https://github.com/HackersHostel/wp2astro/actions/workflows/tests.yml"><img src="https://github.com/HackersHostel/wp2astro/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <img src="https://img.shields.io/badge/WordPress-6.5%2B-21759b" alt="WordPress 6.5+">
  <img src="https://img.shields.io/badge/PHP-8.0%2B-777bb4" alt="PHP 8.0+">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-GPL--2.0--or--later-blue" alt="License: GPL-2.0-or-later"></a>
</p>

# WP2Astro

**Turn a WordPress site into a fast Astro site — same URLs, same look — push it to GitHub, and keep it in sync.**

WP2Astro is a WordPress plugin by [Hackers Hostel](https://hackershostel.com). It converts the site it runs on into an
[Astro](https://astro.build) project, pushes it to a GitHub repository and keeps that repository up to date as you
publish. You keep writing in WordPress; your visitors get a static site. Free and open source (GPL-2.0-or-later).

## Highlights

- **Installs like any plugin.** No Node.js, terminal or application passwords on your side: upload the zip, open
  **WP2Astro**, click **Convert to Astro**.
- **Same URLs.** Every post, page, custom post type entry, category and tag keeps its permalink. Old links still work:
  changed slugs, `?p=123`, `?page_id=7`, `?s=search` and `/feed/` redirect to the right place.
- **Same look.** The theme's CSS, images and fonts are copied, and an AI model rebuilds the header, menu and footer as
  an Astro layout from the rendered homepage.
- **Use the AI you already pay for — or none.** Your **ChatGPT plan** (Sign in with ChatGPT, no API key), or
  **OpenRouter** with one-click connect, an in-plugin credit balance and **Buy credits** (Claude, GPT and free models).
  Without AI you still get a working site.
- **GitHub in one click.** Connect with GitHub's device flow (or a fine-grained token), and the plugin creates the
  repository and pushes.
- **Smart sync.** Re-runs push only changed files, removed posts are removed, and **files you edit in the repo are
  never overwritten**. With auto-sync on, the repo updates a couple of minutes after you publish.
- **SEO kept.** Titles and descriptions from Yoast SEO, Rank Math or All in One SEO; canonical and Open Graph tags;
  RSS feed, sitemap and robots.txt.
- **Search.** Static full-text search with Pagefind.
- **Finish it with your Claude plan.** Each repo ships an agent kit (`AGENTS.md`, `CLAUDE.md`, Claude Code skills), so
  Claude Code or Codex can polish what the conversion couldn't.
- **WordPress-native.** Help tabs on the dashboard, WP-CLI commands, encrypted secrets, and short job steps that fit
  shared hosting.

## How it works

```
WordPress (your server)                                    GitHub                       Your host
┌───────────────────────────────┐   only changed files   ┌──────────────────────┐    ┌──────────────────────┐
│ posts, pages, CPTs → Markdown │ ─────────────────────▶ │ your-site-astro repo │ ─▶ │ npm run build / start│
│ theme CSS, media, SEO meta    │   one commit per sync  │ (edits are kept)     │    │ FlyWP, Netlify, …    │
│ AI layout (optional, cached)  │                        └──────────────────────┘    └──────────────────────┘
└───────────────────────────────┘
```

Each run converts the whole site again in short steps (driven by the dashboard, WP-Cron or WP-CLI), compares the
result with the repository, and commits only the difference. AI calls are single requests with strict validation and a
deterministic fallback, so a bad answer never breaks a conversion.

## Requirements

- WordPress 6.5 or later, PHP 8.0 or later, and an administrator account.
- Optional: a ChatGPT Plus or Pro plan, or an OpenRouter account, for the AI steps.
- Optional: a GitHub account, for a repository and sync instead of a zip.
- To build or serve the generated site: Node.js 22.23.4 or later (on your computer or your host).

## Install

1. Download `wp2astro.zip` from the [latest release](https://github.com/HackersHostel/wp2astro/releases/latest).
2. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**, choose the zip, then **Install Now** and
   **Activate Plugin**.
3. Open **WP2Astro** in the admin menu.

Updates arrive in **Dashboard → Updates** like any other plugin, from this repository's releases.

## Quick start

1. **AI model** (optional) — select **ChatGPT plan** and click **Sign in with ChatGPT**, or select **OpenRouter** and
   click **Connect OpenRouter**, or keep **No AI**.
2. **GitHub** (optional) — click **Connect GitHub** and enter the code on github.com. Without GitHub you download a
   zip.
3. **What to build** — SEO, feeds, search, custom post types, agent kit, auto-sync.
4. Click **Convert to Astro**. Later runs become **Sync now**.

The [Getting started](docs/getting-started.md) guide walks through every step. In WordPress, click **Help** on the
WP2Astro screen for a short version.

From the command line (and for coding agents):

```bash
wp wp2astro convert                                      # convert and push
wp wp2astro convert --no-ai --no-github --zip=./site.zip # zip only
wp wp2astro status                                       # provider, GitHub, recent runs
```

## What you get

```
src/content/posts|pages|items/    Markdown with frontmatter (items = custom post types)
src/site.json                     title, menu, terms, archives, feature flags
src/layouts/BaseLayout.astro      the theme shell (AI-built or default)
src/components/SeoHead.astro      title, description, canonical, Open Graph
src/pages/                        permalinks, archives, pagination, feed.xml, sitemap.xml, robots.txt, search
public/theme/                     the old theme's CSS, images, fonts
public/wp-content/uploads/        referenced media at their original paths
redirects.json, public/_redirects old WordPress URLs → new ones
server.mjs                        zero-dependency server for `npm start` (with real 301s)
AGENTS.md, CLAUDE.md, .claude/    agent kit
CONVERSION_REPORT.md              anything that needs a look
```

## Deploy

- **FlyWP + DigitalOcean:** add a Node.js site from the repo; build `npm install && npm run build`, start `npm start`.
  FlyWP's free plan covers one server and one site; the DigitalOcean droplet is paid.
- **Any static host:** build `npm run build`, publish `dist/`. Netlify and Cloudflare Pages read `public/_redirects`.

See [Deploy the site](docs/deploy.md) for what each option redirects.

## Documentation

| For site owners | For developers |
|---|---|
| [Getting started](docs/getting-started.md) | [Hooks](docs/developers/hooks.md) |
| [Choose an AI model](docs/providers.md) | [WP-CLI commands](docs/developers/wp-cli.md) |
| [Use your ChatGPT plan](docs/chatgpt.md) | [White-label](docs/developers/white-label.md) |
| [Use OpenRouter](docs/openrouter.md) | [GitHub OAuth app](docs/developers/github-oauth-app.md) |
| [Finish the site with Claude Code](docs/claude-code.md) | [Releasing](docs/developers/releasing.md) |
| [Connect GitHub](docs/github.md) | [Contributing](CONTRIBUTING.md) |
| [Keep the site in sync](docs/sync.md) | [Local test setup](dev/README.md) |
| [Deploy the site](docs/deploy.md) | |
| [Troubleshooting](docs/troubleshooting.md) | |
| [Privacy and external services](docs/privacy.md) | |

`readme.txt` is the WordPress.org-format description of the plugin, and `.wordpress-org/` holds the plugin directory
icons and banners.

## Privacy

WP2Astro only talks to services you set up, and only when you convert, sync or test a connection. There is no
analytics or telemetry, and keys and tokens are encrypted at rest. See
[Privacy and external services](docs/privacy.md) for exactly what is sent where.

## Not converted

Comments, forms, WooCommerce and other features that need a server; sidebar widgets; custom fields (such as ACF)
beyond the rendered content.

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

## Contributing and support

- **Contribute:** see [CONTRIBUTING.md](CONTRIBUTING.md). The local test setup needs no API keys or GitHub account.
- **Questions:** [Q&A discussions](https://github.com/HackersHostel/wp2astro/discussions/categories/q-a).
- **Bugs:** [issues](https://github.com/HackersHostel/wp2astro/issues/new/choose) — see [SUPPORT.md](SUPPORT.md).
- **Security problems:** report them privately, as [SECURITY.md](SECURITY.md) describes.

## License

[GPL-2.0-or-later](LICENSE). Free to use. A white-label licence from Hackers Hostel covers shipping it under your own
brand — see [White-label](docs/developers/white-label.md).
