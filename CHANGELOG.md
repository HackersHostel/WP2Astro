# Changelog

## Unreleased

- New dashboard. The first visit opens a three-step setup (**Choose AI**, **Connect GitHub**, **Convert**); after
  that the screen has **Overview**, **Activity** and **Settings** tabs. Overview shows the sync status with **Sync
  now**, numbers from the last run, recent activity, connections and the Claude Code commands. Runs show each step
  live, with a **Stop** button. GitHub, OpenAI, Claude and OpenRouter logos throughout; no web fonts are loaded.
- **Claude plan** is now a choice under **AI model**: it converts with the default layout, turns on the agent kit
  and shows the commands to finish the site in Claude Code.
- **Settings → Plugin data**: **Clear data** removes the AI cache, run history and stored zips; **Run the setup
  again** brings back the setup.
- New logo: the teal WA glyph, in the dashboard, the admin menu, the plugin icons and the banner.
- **Local model** provider: run open models such as Gemma, Llama or Qwen on your own server (Ollama, LM Studio,
  llama.cpp, vLLM) through any OpenAI-compatible **Server URL**.
- OpenRouter's default model is now the open `google/gemma-4-31b-it` (was `anthropic/claude-haiku-5.5`).
- Fixed: posts, pages and terms with non-ASCII slugs (`café`, `日本`) broke `npm run build`. Their paths are now
  decoded, and `npm start` no longer crashes when it redirects to such a path.
- Fixed: shortcodes that print output instead of returning it no longer break the conversion step.
- A sync now stops, without changing anything, when GitHub returns only part of a large repository's file list,
  instead of risking deleting files.
- AI cache entries unused for 30 days are removed.
- Settings are read and decrypted once per request.
- Docs: [Run a local model](docs/local-models.md), and deploying to Render.

- Help tabs on the WP2Astro screen (Overview, AI model, GitHub, Sync, Troubleshooting) and a Help sidebar with links
  to the documentation, Q&A, issues and the changelog. Field descriptions link to the matching guide.
- `readme.txt` in WordPress.org format, and plugin directory icons and banners in `.wordpress-org/`. **View details**
  on the Plugins screen now shows the readme's description, installation steps and FAQ.
- Documentation rewritten as task-based guides for site owners, with developer pages for hooks, WP-CLI, white-label,
  the GitHub OAuth app and releasing. Every filter has a docblock with `@since`.
- `bin/build-zip.sh --wporg` builds the WordPress.org version, without the GitHub updater and the `Update URI` header.
- New `WP2ASTRO_BRAND_FORUM` constant and `forum` brand key for the Help sidebar's "Ask a question" link.

## 0.2.0

First Hackers Hostel release, renamed from "WP to Astro" to **WP2Astro**.

- New dashboard under its own **WP2Astro** menu: connect a model and GitHub, convert, sync, recent runs.
- AI providers, kept lightweight: your ChatGPT plan (Sign in with ChatGPT) or OpenRouter (one-click connect, credit
  balance, Buy credits; Claude, GPT and free models), or no AI. Claude subscriptions work through the agent kit in
  Claude Code. Load models and Test buttons. Local model servers (Ollama) are no longer offered.
- GitHub: one-click connect with the device flow (when the OAuth app's client ID is set), token checked on save.
- Smart sync: only changed files are uploaded and committed, removed entries are removed, files edited in the repo are
  kept, no commit when nothing changed. Optional auto-sync after publishing. Run history in the dashboard and CLI.
- AI results are cached, so syncs don't spend credits again on an unchanged theme or post.
- SEO: titles, descriptions, noindex and canonical from Yoast SEO, Rank Math or All in One SEO; canonical, Open Graph
  and Twitter tags on every page.
- RSS feed, sitemap and robots.txt; old `/feed/`, `/wp-sitemap.xml`, changed slugs, `?p=`, `?page_id=` and `?s=` URLs
  redirect (`npm start`, `public/_redirects`, and static redirect pages).
- Public custom post types with their archives.
- Static search with Pagefind.
- Agent kit in the generated repo: `AGENTS.md`, `CLAUDE.md` and Claude Code skills to polish the layout, fix
  shortcodes and verify the build.
- WP-CLI: `wp wp2astro convert` and `wp wp2astro status`.
- API keys and tokens are encrypted at rest.
- Updates from GitHub releases; white-label constants and filters.
- Loopback request to the homepage now verifies SSL like core does.

## 0.1.0

- Convert posts and pages to an Astro project with the same URLs, theme CSS, AI layout and shortcode cleanup; push to
  GitHub or download a zip.
