# Dev harness

Everything here is for local testing and is not shipped (see `.distignore`). No API keys, ChatGPT account or GitHub
account are needed: `llm.py` and `gh.py` stand in for them.

## Test site

The test site used for v0.2 was built from wordpress.org zips (`wp core download` hung): WordPress + SQLite Database
Integration (`db.copy` → `wp-content/db.php`), Twenty Ten, Yoast SEO, permalinks `/%year%/%monthnum%/%postname%/`,
3 images imported first (IDs 4–6, assumed by `dev/seed.php`), then `wp eval-file dev/seed.php`. Extras: an mu-plugin
registering a public `project` CPT (`has_archive`, slug `projects`) with 2 entries, Yoast title/description on post 7,
a slug change on post 8 (`_wp_old_slug`), and `wp-content/mu-plugins/test-gh.php`:
`<?php add_filter('wpta_github_api', fn() => 'http://127.0.0.1:9999');`. If `wp core install` stores `home`/`siteurl`
with a subfolder, reset both to `http://localhost:8899`. Serve with `PHP_CLI_SERVER_WORKERS=4 php -S localhost:8899`
(workers needed for the loopback request). Login admin/admin.

From the plugin root (`mkdir dev-out` first):
- `python3 dev/llm.py` — mock on :11434 speaking `/chat/completions` and ChatGPT `/responses` (SSE). Model
  `badmodel` returns an invalid layout. Copy `dev/test-llm.php` into the test site's `mu-plugins/`: it points every
  provider at the mock through the `wpta_provider` filter.
- `python3 dev/gh.py` — mock GitHub on :9999 (token `ghp_test`): git-compatible blob ids, trees, commits, non-forced
  refs; writes the branch head to `dev-out/ghrepo/` and a log to `dev-out/ghrepo.json`. `POST /__edit
  {"path","content"}` simulates someone pushing an edit.
- `python3 dev/drive.py [openrouter|chatgpt|none] [model] [--noai] [--nogh] [--norun]` — saves settings through the
  real form, calls Load models + Test, runs all AJAX steps (unless `--norun`), downloads the zip to `dev-out/out.zip`.
  With a real GitHub connection, use `--nogh` and `wp wp2astro convert --no-github` to avoid pushing. For `chatgpt`, seed
  tokens first (`update_option( 'wpta_chatgpt', … )` with `WPTA_Secrets::seal()`ed tokens and `plan => true`).
- `node dev/shot.js` — Playwright before/after screenshots (WP on :8899, `npm start` of the output on :4567).
- `node dev/wporg/assets.js` — renders the WordPress.org icons and banners into `.wordpress-org/` (from
  `assets/logo.svg` and `dev/wporg/banner.svg`).
- `node dev/wporg/screenshots.js` — captures `screenshot-1.png` … `screenshot-6.png` for `.wordpress-org/`, in the
  order of `readme.txt`. Needs the test site, both mocks and one conversion served with `PORT=4567 npm start`.

Don't point `dev/drive.py` at a real site: it overwrites the plugin settings (including tokens).
