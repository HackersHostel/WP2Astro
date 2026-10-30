#!/usr/bin/env bash
# The "WordPress conversion" CI job (issue #24), runnable locally too. Builds the test site from dev/README.md in
# dev-out/ci/ (WordPress + SQLite + Twenty Ten, three images, dev/seed.php, the mu-plugins in dev/), starts the mocks
# (dev/llm.py, dev/gh.py, dev/storage.py), then:
#   1. dev/drive.py: settings through the dashboard form, a conversion through its AJAX steps, pushed to the mock;
#   2. one edited post, synced with `wp wp2astro convert --cached`: only that post may change;
#   3. media in Git LFS, then in object storage, both against the mocks;
#   4. npm install && npm run build on the zip from step 1, and dev/compare.mjs when Playwright is installed.
# Needs php (sqlite3, gd, zip), python3, node 22.23.4+, curl and unzip, and ports 8899, 11434, 9999, 9998 and 4567.
set -euo pipefail

REPO=$(cd "$(dirname "$0")/.." && pwd)
WORK="$REPO/dev-out/ci"
WP="$WORK/wordpress"
cd "$REPO"
rm -rf "$WORK" dev-out/ghrepo dev-out/ghrepo.json dev-out/storage dev-out/out.zip dev-out/compare
mkdir -p "$WORK"

pids=()
# Children first: PHP's built-in server forks workers (PHP_CLI_SERVER_WORKERS) that outlive their parent.
cleanup() { for pid in "${pids[@]:-}"; do pkill -P "$pid" 2>/dev/null || true; kill "$pid" 2>/dev/null || true; done; }
trap cleanup EXIT
step() { printf '\n== %s\n' "$*"; }
fail() { echo "FAIL: $*" >&2; exit 1; }
# Deprecation notices from WP-CLI's bundled libraries on new PHP versions would end up in captured output.
wp() { php -d memory_limit=512M -d error_reporting='E_ALL&~E_DEPRECATED' -d display_errors=stderr "$WORK/wp-cli.phar" --path="$WP" "$@"; }
json() { python3 -c "import json,sys; d=json.load(open('dev-out/ghrepo.json')); print(d$1)"; }
fetch() { curl -fsSL --retry 3 -o "$1" "$2"; }
wait_for() { for _ in $(seq 1 60); do curl -s -o /dev/null "$1" && return 0; sleep 0.5; done; fail "nothing answered at $1"; }

step "WordPress with SQLite and Twenty Ten"
fetch "$WORK/wp.zip" https://wordpress.org/latest.zip
fetch "$WORK/sqlite.zip" https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip
fetch "$WORK/twentyten.zip" https://downloads.wordpress.org/theme/twentyten.latest-stable.zip
fetch "$WORK/wp-cli.phar" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
unzip -q "$WORK/wp.zip" -d "$WORK"
unzip -q "$WORK/sqlite.zip" -d "$WP/wp-content/plugins"
unzip -q "$WORK/twentyten.zip" -d "$WP/wp-content/themes"
SQ="$WP/wp-content/plugins/sqlite-database-integration"
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$SQ#" -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" "$SQ/db.copy" > "$WP/wp-content/db.php"
wp config create --dbname=wp --dbuser=wp --dbpass=wp --skip-check --quiet
wp core install --url=http://localhost:8899 --title="Ghost Town Gazette" --admin_user=admin --admin_password=admin --admin_email=admin@example.test --skip-email --quiet
# WP-CLI can store the install folder in the address (http://localhost:8899/wordpress); the server serves its root.
wp option update home http://localhost:8899 --quiet
wp option update siteurl http://localhost:8899 --quiet
wp theme activate twentyten --quiet
wp rewrite structure '/%year%/%monthnum%/%postname%/' --quiet
mkdir -p "$WP/wp-content/mu-plugins"
cp dev/test-cpt.php dev/test-gh.php dev/test-llm.php "$WP/wp-content/mu-plugins/"
ln -s "$REPO" "$WP/wp-content/plugins/wp2astro"
wp plugin activate wp2astro --quiet

step "Seed content"
ids=()
for name in lighthouse mill crypt; do
	php -r '$i = imagecreatetruecolor(1200, 675); $c = array_map("hexdec", str_split(substr(md5($argv[1]), 0, 6), 2)); imagefill($i, 0, 0, imagecolorallocate($i, $c[0], $c[1], $c[2])); imagepng($i, $argv[2]);' "$name" "$WORK/$name.png"
	ids+=("$(wp media import "$WORK/$name.png" --title="$name" --porcelain)")
done
wp eval-file dev/seed.php "${ids[@]}"
post_id() { wp post list --post_type=post --name="$1" --field=ID; }
LIGHTHOUSE=$(post_id the-lighthouse-keeper-who-never-left)
MILL=$(post_id ten-things-found-in-the-old-mill)
wp post meta add "$MILL" _wp_old_slug old-mill-ten-things --quiet

step "Servers"
(cd "$WP" && PHP_CLI_SERVER_WORKERS=4 exec php -S localhost:8899 > "$WORK/php.log" 2>&1) & pids+=($!)
python3 dev/llm.py > "$WORK/llm.log" 2>&1 & pids+=($!)
python3 dev/gh.py > "$WORK/gh.log" 2>&1 & pids+=($!)
python3 dev/storage.py > "$WORK/storage.log" 2>&1 & pids+=($!)
wait_for http://localhost:8899/wp-login.php
wait_for http://127.0.0.1:9999/
wait_for http://127.0.0.1:9998/
wait_for http://127.0.0.1:11434/

step "1. Convert through the dashboard (AI mock, GitHub mock)"
python3 dev/drive.py openrouter
[ "$(json "['commits']")" = "1" ] || fail "expected one commit on the GitHub mock"
[ -f dev-out/out.zip ] || fail "no zip"

step "2. One edited post, synced from the cache"
wp post update "$MILL" --post_title="Edited for the cache test" --quiet
wp wp2astro convert --cached | tee "$WORK/cached.log"
grep -q "Reused .* unchanged entries from the cache" "$WORK/cached.log" || fail "the cache wasn't used"
sent=$(json "['tree_entries_sent']")
[ "$sent" -ge 1 ] && [ "$sent" -le 5 ] || fail "a one-post sync sent $sent tree entries"

step "3a. Media in Git LFS"
wp eval 'WPTA_Settings::update( array( "media_mode" => "lfs" ) );'
wp wp2astro convert --no-ai
[ "$(json "['lfs_objects']")" -ge 3 ] || fail "no LFS objects on the mock"
grep -q "filter=lfs" dev-out/ghrepo/.gitattributes || fail "no .gitattributes"
head -1 "$(find dev-out/ghrepo/public/wp-content/uploads -type f | head -1)" | grep -q "git-lfs" || fail "media isn't an LFS pointer"

step "3b. Media in object storage (S3 signature checked by the mock)"
wp eval 'WPTA_Settings::update( array( "media_mode" => "cdn", "cdn_provider" => "s3", "cdn_endpoint" => "http://127.0.0.1:9998", "cdn_region" => "eu-west-1", "cdn_bucket" => "media", "cdn_key_id" => "test-key", "cdn_secret" => "test-secret", "cdn_url" => "http://127.0.0.1:9998/media" ) );'
wp wp2astro convert --no-ai
[ -n "$(find dev-out/storage/media -type f 2>/dev/null | head -1)" ] || fail "nothing uploaded to the storage mock"
grep -rq "http://127.0.0.1:9998/media/wp-content/uploads/" dev-out/ghrepo/src/content || fail "links don't point at storage"
[ ! -d dev-out/ghrepo/public/wp-content/uploads ] || fail "media is still in the repo"
wp eval 'WPTA_Settings::update( array( "media_mode" => "repo" ) );'

step "4. Build the converted site"
unzip -q dev-out/out.zip -d "$WORK/site"
SITE=$(find "$WORK/site" -mindepth 1 -maxdepth 1 -type d | head -1)
(cd "$SITE" && npm install --no-audit --no-fund --loglevel=error && npm run build)
[ -f "$SITE/dist/2009/10/the-lighthouse-keeper-who-never-left/index.html" ] || fail "a post is missing from dist/"
[ -f "$SITE/dist/category/haunted-houses/index.html" ] || fail "a category archive is missing"
[ -f "$SITE/dist/2013/01/index.html" ] || fail "a month archive is missing"
(cd "$SITE" && PORT=4567 exec node server.mjs > "$WORK/astro.log" 2>&1) & pids+=($!)
wait_for http://localhost:4567/
[ "$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:4567/?p=$LIGHTHOUSE")" = "301" ] || fail "?p=$LIGHTHOUSE doesn't redirect"

if node -e "require.resolve('playwright')" 2>/dev/null; then
	step "Visual check"
	node dev/compare.mjs
else
	echo "Playwright isn't installed; skipping the visual check (npm install --no-save playwright)."
fi
echo
echo "WordPress conversion checks passed."
