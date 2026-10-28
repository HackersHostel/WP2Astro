# Releasing

Installed sites update from this repo's GitHub releases (`Update URI` in the plugin header, handled by
`includes/class-wpta-updater.php`). The release must carry a `wp2astro.zip` asset whose top folder is `wp2astro/`.

1. Bump the version in **both** places in `wp2astro.php`: the `Version:` header and `WPTA_VERSION`.
2. Add a `## x.y.z` section to `CHANGELOG.md` (it becomes the release notes).
3. Check locally: `php tests/run.php` and `bin/build-zip.sh`.
4. Commit, then tag and push: `git tag v0.2.0 && git push origin v0.2.0`.

The *Release* workflow tests, checks the tag matches the version, builds the zip (honoring `.distignore`) and publishes
the release. Sites see the update within 12 hours (or right away from **Dashboard → Updates → Check again** once the
cached release info expires).
