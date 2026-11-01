# Releasing

WP2Astro ships as two builds from the same code. They differ only in how installed sites get updates.

| | Hackers Hostel release | WordPress.org build |
|---|---|---|
| Built with | `bin/build-zip.sh` | `bin/build-zip.sh --wporg` |
| Output | `build/wp2astro.zip` | `build/wporg/wp2astro/` and `build/wporg/wp2astro.zip` |
| Updates from | This repository's GitHub releases | WordPress.org |
| `includes/class-wpta-updater.php` | Included | **Removed** |
| `Update URI` plugin header | Included | **Removed** |
| `wpta_update_repo` filter | Available | Not available |
| "GitHub releases" in `readme.txt` → External services | Included | **Removed** |

Both builds leave out everything in `.distignore`: `docs/`, `dev/`, `tests/`, `bin/`, `.wordpress-org/`, `README.md`,
`CHANGELOG.md` and the other repository files. `readme.txt` and `LICENSE` stay in.

## Why the WordPress.org build has no updater

[Guideline 8](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#8-plugins-may-not-send-executable-code-via-third-party-systems)
of the WordPress.org plugin guidelines forbids plugins hosted there from installing or updating code from any server
other than WordPress.org. The GitHub updater (`includes/class-wpta-updater.php`, registered through the `Update URI`
header) does exactly that, so `--wporg` deletes the file and the header, and drops the matching "GitHub releases"
subsection from the copy of `readme.txt` in the build. `wp2astro.php` only loads the updater when the file exists, so
nothing else changes.

Never submit or commit to the WordPress.org SVN repository from a plain `bin/build-zip.sh` build.

## Keep the version in step

The version lives in four places. `bin/build-zip.sh` and `php tests/run.php` fail when they disagree.

1. The `Version:` header in `wp2astro.php`.
2. `WPTA_VERSION` in `wp2astro.php`.
3. `Stable tag:` in `readme.txt`.
4. A `## x.y.z` section in `CHANGELOG.md` and a `= x.y.z =` entry under `== Changelog ==` in `readme.txt`.

Add an `== Upgrade Notice ==` entry to `readme.txt` when the release needs one (300 characters at most).

## Publish a Hackers Hostel release

1. Move the `## Unreleased` notes in `CHANGELOG.md` under a new `## x.y.z` heading. They become the release notes.
2. Bump the version in the four places above, and update `Tested up to:` in `readme.txt` if you tested a newer
   WordPress major version.
3. Check locally: `php tests/run.php` and `bin/build-zip.sh`.
4. Commit, then tag and push: `git tag vx.y.z && git push origin vx.y.z`.

The *Release* workflow runs the tests, checks that the tag matches the version, builds the zip (honoring
`.distignore`) and publishes the release. Installed sites see the update within 12 hours, or right away from
**Dashboard → Updates → Check again** once the cached release info expires. Once it passes, the *WordPress.org* workflow publishes the
same version to the plugin directory, when it is turned on (see [below](#publish-to-wordpressorg-automatically)).

## Prepare the WordPress.org build

Before the first submission and before each WordPress.org release:

1. Run `bin/build-zip.sh --wporg`.
2. Install `build/wporg/wp2astro.zip` on a clean site with [Plugin Check](https://wordpress.org/plugins/plugin-check/)
   and run it the way the WordPress.org upload form does. Any error there rejects the upload:

   ```bash
   wp plugin check wp2astro --error-severity=7 --warning-severity=6 --include-low-severity-errors --exclude-checks=prefixing --categories=plugin_repo
   ```

   Then run `wp plugin check wp2astro` for everything a reviewer may ask about. Fix errors, or explain a false alarm
   in a `phpcs:ignore … -- reason` comment; file larger code findings as their own issues.
3. Paste `readme.txt` into the [readme validator](https://wordpress.org/plugins/developers/readme-validator/). It
   must show no errors or warnings.
4. Check that **External services** in `readme.txt` lists every request in [Privacy](../privacy.md).
5. Publish to WordPress.org: the *WordPress.org* workflow does it after the *Release* workflow (below). By hand,
   commit `build/wporg/wp2astro/` to SVN `trunk/`, copy it to `tags/x.y.z/`, and copy `.wordpress-org/` to SVN
   `assets/`.

## Publish to WordPress.org automatically

`.github/workflows/wporg-deploy.yml` (*WordPress.org* on the Actions tab) runs after the *Release* workflow passes for
a `v*` tag. It checks that the tag matches the plugin version, runs `bin/build-zip.sh --wporg`, and uses
[10up/action-wordpress-plugin-deploy](https://github.com/10up/action-wordpress-plugin-deploy) to commit the build to
SVN `trunk/`, copy it to `tags/x.y.z/`, and sync `.wordpress-org/` to `assets/`. A version already in `tags/` is
skipped.

It stays off until WordPress.org has approved the plugin, because the SVN repository only exists from then. To turn
it on, in the repository's **Settings → Secrets and variables → Actions**:

1. Add the secret `SVN_USERNAME`: the WordPress.org username that owns the plugin (`mahmudurrahmanlabib`).
2. Add the secret `SVN_PASSWORD`: the SVN password from that profile's **Account & Security** page, not the login
   password.
3. Add the variable `WPORG_DEPLOY` with the value `true`.

Then publish the release WordPress.org approved: on the Actions tab, open **WordPress.org**, click **Run workflow**,
enter the tag (for example `v0.3.1`) and leave **Dry run** off. **Dry run** builds and prepares the SVN commit without
committing, to check the setup first. From then on, pushing a tag publishes to GitHub and then to WordPress.org.

## Plugin directory assets

`.wordpress-org/` holds the images WordPress.org shows on the plugin page. They are not part of either zip.

| File | Size | Made from |
|---|---|---|
| `icon.svg`, `icon-128x128.png`, `icon-256x256.png` | 128 and 256 px | `assets/logo.svg` |
| `banner-772x250.png`, `banner-1544x500.png` | 772×250 and 1544×500 px | `dev/wporg/banner.svg` |
| `screenshot-1.png` … `screenshot-6.png` | about 1200 px wide | the local test site |

Regenerate them with `node dev/wporg/assets.js` (icons and banners) and `node dev/wporg/screenshots.js`
(screenshots; needs the local test site from [dev/README.md](../../dev/README.md)). The screenshot numbers must match
the captions under `== Screenshots ==` in `readme.txt`.
