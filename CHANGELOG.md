# Changelog

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
