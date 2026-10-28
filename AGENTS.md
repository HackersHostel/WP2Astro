# AGENTS.md

WP2Astro: a WordPress plugin (Hackers Hostel) that converts the site it runs on into an Astro project, pushes it to
GitHub and keeps it in sync. `README.md` and `docs/` describe what it does; `dev/README.md` the local test setup.

## Conventions

- PHP 8.0+, WordPress 6.5+, no Composer dependencies. Core APIs only (`wp_remote_*`, `ZipArchive`, `DOMDocument`).
  Plain file IO inside `uploads/wp2astro/` is fine.
- Prefix: classes `WPTA_*`, options/hooks/AJAX actions `wpta_*`, text domain and slug `wp2astro`.
- Every AJAX handler: `check_ajax_referer( 'wpta' )` + `current_user_can( 'manage_options' )`.
- Secrets go through `WPTA_Settings::update()` / `WPTA_Secrets` (encrypted at rest). Never echo them back.
- Job steps stay short (AJAX, WP-Cron and WP-CLI all drive the same `WPTA_Job::run_step()`).
- AI is optional: every AI step has a deterministic fallback and logs why it was used.
- Generated files must be deterministic — no timestamps — or every sync commits.
- External requests are listed in `docs/privacy.md`; keep it current.

## Layout

- `wp2astro.php` — bootstrap, WP-CLI registration
- `includes/class-wpta-settings.php` — settings, providers, brand (white-label)
- `includes/class-wpta-secrets.php` — encryption of keys/tokens
- `includes/class-wpta-llm.php` — chat client: OpenAI-compatible (OpenRouter), ChatGPT Responses (SSE)
- `includes/class-wpta-chatgpt.php` — Sign in with ChatGPT (paste-back loopback flow, refresh, revoke)
- `includes/class-wpta-connect.php` — AJAX: models, test, OpenRouter OAuth + balance, ChatGPT, GitHub device flow
- `includes/class-wpta-admin.php` — dashboard page; `assets/admin.js`, `assets/admin.css`
- `includes/class-wpta-exporter.php` — entries (incl. CPTs), SEO meta, redirects, media, theme assets
- `includes/class-wpta-markdown.php` — HTML → Markdown
- `includes/class-wpta-scaffold.php` — Astro project templates (SEO head, feed, sitemap, search, server)
- `includes/class-wpta-agent-kit.php` — AGENTS.md / CLAUDE.md / skills for the generated repo
- `includes/class-wpta-ai.php` — layout + shortcode prompts, validation, normalization, cache
- `includes/class-wpta-github.php` — repo, blobs, remote tree, commit (Git Data API)
- `includes/class-wpta-sync.php` — sync plan (pure), manifest, history, lock, auto-sync cron
- `includes/class-wpta-job.php` — phased job runner
- `includes/class-wpta-cli.php` — `wp wp2astro convert|status`
- `includes/class-wpta-updater.php` — updates from GitHub releases
- `tests/run.php` — unit tests for the pure parts; `dev/` — mocks + driver (not shipped)

## Checks before calling something done

- `php tests/run.php` and `find . -name '*.php' -not -path './dev/*' -exec php -l {} \;`
- A conversion on the local WP with `python3 dev/drive.py`, then `npm install && npm run build` on the output
- `bin/build-zip.sh` builds
