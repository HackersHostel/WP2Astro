#!/usr/bin/env bash
# Build the plugin zip (top folder wp2astro/) from the working tree, honoring .distignore.
# Usage: bin/build-zip.sh            → build/wp2astro.zip        (Hackers Hostel release, updates from GitHub)
#        bin/build-zip.sh --wporg    → build/wporg/wp2astro/ and build/wporg/wp2astro.zip
#                                      (WordPress.org: no GitHub updater, no Update URI header; see docs/developers/releasing.md)
set -euo pipefail
cd "$(dirname "$0")/.."

wporg=0
case "${1:-}" in
  "") ;;
  --wporg) wporg=1 ;;
  *) echo "Usage: bin/build-zip.sh [--wporg]" >&2; exit 2 ;;
esac

version=$(sed -n 's/^ \* Version: *//p' wp2astro.php | tr -d '[:space:]')
const=$(sed -n "s/^define( 'WPTA_VERSION', '\(.*\)' );/\1/p" wp2astro.php)
if [ "$version" != "$const" ]; then
  echo "Version mismatch: header $version, WPTA_VERSION $const" >&2
  exit 1
fi
stable=$(sed -n 's/^Stable tag: *//p' readme.txt | tr -d '[:space:]')
if [ "$version" != "$stable" ]; then
  echo "Version mismatch: header $version, readme.txt Stable tag $stable" >&2
  exit 1
fi

out=build
[ "$wporg" = 1 ] && out=build/wporg
rm -rf "$out/wp2astro" "$out/wp2astro.zip" && mkdir -p "$out/wp2astro"
rsync -a --exclude-from=.distignore --exclude=build ./ "$out/wp2astro/"

if [ "$wporg" = 1 ]; then
  # Guideline 8: plugins hosted on WordPress.org may only update from WordPress.org.
  rm "$out/wp2astro/includes/class-wpta-updater.php"
  sed -i.bak '/^ \* Update URI:/d' "$out/wp2astro/wp2astro.php" && rm "$out/wp2astro/wp2astro.php.bak"
  if grep -q 'Update URI' "$out/wp2astro/wp2astro.php"; then
    echo "Update URI header still present in the WordPress.org build" >&2
    exit 1
  fi
fi

(cd "$out" && zip -qr wp2astro.zip wp2astro)
echo "$out/wp2astro.zip ($version, $(find "$out/wp2astro" -type f | wc -l | tr -d ' ') files)"
