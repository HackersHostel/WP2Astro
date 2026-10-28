# CLAUDE.md

Read `PRD.md` first — it is the source of truth for scope. Non-goals there are hard non-goals.

## Conventions
- PHP 8.0+, WordPress 6.5+. No Composer dependencies; use core APIs (`wp_remote_*`, `WP_Filesystem`-free plain file IO inside the uploads job dir is OK).
- Prefix: classes `WPTA_*`, functions/options `wpta_*`, text domain `wp-to-astro`.
- Every AJAX handler: `check_ajax_referer( 'wpta' )` + `current_user_can( 'manage_options' )`.
- Keep each job step short; anything slow belongs in its own phase (see PRD §5).
- AI is optional: every AI step must have a deterministic fallback and log why it was used.

## Layout
- `wp-to-astro.php` — bootstrap
- `includes/class-wpta-settings.php` — settings + admin page
- `includes/class-wpta-llm.php` — OpenAI-compatible client
- `includes/class-wpta-markdown.php` — HTML → Markdown
- `includes/class-wpta-exporter.php` — content, media, theme assets
- `includes/class-wpta-scaffold.php` — Astro project templates
- `includes/class-wpta-ai.php` — layout + shortcode cleanup prompts/validation
- `includes/class-wpta-github.php` — repo create + Git Data API push
- `includes/class-wpta-job.php` — phased job runner (AJAX)
- `assets/admin.js`, `assets/admin.css`
- `dev/` — local test harness (mock LLM + GitHub, driver, seed). Excluded from releases via `.distignore`.

Current status and next tasks: `HANDOFF.md`.

## Checks before calling something done
- `find . -name '*.php' -exec php -l {} \;`
- Run a conversion on a local WP, then `npm install && npm run build` in the output.
