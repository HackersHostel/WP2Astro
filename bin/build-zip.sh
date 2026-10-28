#!/usr/bin/env bash
# Build build/wp2astro.zip (top folder wp2astro/) from the working tree, honoring .distignore.
# Usage: bin/build-zip.sh            → build/wp2astro.zip
set -euo pipefail
cd "$(dirname "$0")/.."

version=$(sed -n 's/^ \* Version: *//p' wp2astro.php | tr -d '[:space:]')
const=$(sed -n "s/^define( 'WPTA_VERSION', '\(.*\)' );/\1/p" wp2astro.php)
if [ "$version" != "$const" ]; then
  echo "Version mismatch: header $version, WPTA_VERSION $const" >&2
  exit 1
fi

rm -rf build && mkdir -p build/wp2astro
rsync -a --exclude-from=.distignore --exclude=build ./ build/wp2astro/
(cd build && zip -qr wp2astro.zip wp2astro)
echo "build/wp2astro.zip ($version, $(find build/wp2astro -type f | wc -l | tr -d ' ') files)"
