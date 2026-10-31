# Changelog

## 0.3.0

- New dashboard. The first visit opens a three-step setup (**Choose AI**, **Connect GitHub**, **Convert**); after
  that the screen has **Overview**, **Activity** and **Settings** tabs. Overview shows the sync status with **Sync
  now**, numbers from the last run, recent activity, connections and the Claude Code commands. Runs show each step
  live, with a **Stop** button. GitHub, OpenAI, Claude and OpenRouter logos throughout; no web fonts are loaded.
- **Claude plan** is now a choice under **AI model**: it converts with the default layout, turns on the agent kit
  and shows the commands to finish the site in Claude Code.
- **Settings → Plugin data**: **Clear data** removes the AI cache, run history and stored zips; **Run the setup
  again** brings back the setup.
- **Settings → Media** (#17): copy media into the repo (default, now capped at 25 MB per file), store it with Git
  LFS, leave it on WordPress, or upload it to Amazon S3, an S3-compatible service, Cloudflare R2 or Bunny Storage with
  a **Test storage** button. Files over the limit keep their WordPress address and are listed in the report. Blob
  uploads use far less PHP memory. New filter `wpta_max_media_bytes`.
- Archives (#19): category, tag and custom post type archives are paginated like the blog, and year, month and author
  archives are generated at their WordPress URLs and listed in the sitemap. Entries gain `authorSlug` in frontmatter,
  and bylines link to the author archive.
- Theme JavaScript (#20): scripts the theme loads on the homepage (and jQuery, when it does) are copied and loaded on
  every page, so menu toggles keep working. AI layouts may load them; the default layout gets a small-screen menu
  toggle of its own when the theme has none.
- **Custom fields** (#22): an opt-in switch under **What to build** writes public post meta and ACF fields to a
  `fields:` frontmatter key, with sorted keys. New filter `wpta_entry_fields`.
- Netlify (#21): `netlify.toml` with the build settings and 301s for `?p=`, `?page_id=` and `?s=` links, kept out of
  `public/_redirects` because Cloudflare Pages reads that file too.
- Large sites (#14, #15, #16): auto-sync renders only posts that changed (`wp wp2astro convert --cached` does the same);
  files are hashed as they are written instead of all at once; the zip is built in steps; `?p=` links are collected
  while converting; commits send GitHub only changed and removed paths on top of the current tree.
- Fixed (#10): permalinks ending in `.html` kept a trailing slash and changed URL. They are now files in `dist/`, with
  matching canonical links and sitemap entries.
- Fixed (#11): text that looks like Markdown (`[note](sic)`, `__init__`, `&copy;`, a line of `---` or `===`) changed
  meaning after conversion. It is now escaped.
- Fixed (#12): two runs started at the same moment could both run. The run lock is now taken atomically, a run that
  loses its lock stops, and a job is only stepped by one request at a time.
- CI (#23, #24): builds a fixture Astro site with every URL case and checks `dist/` and `npm start`, and converts a
  real WordPress site against the mocks (AI, GitHub with LFS, S3 storage) with a visual comparison of the result.
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
- Passes the WordPress.org upload check (Plugin Check): templates and prompts no longer use heredocs, job folders and
  cache files go through WordPress's file functions, and its false alarms are explained in the code.
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
