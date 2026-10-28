# Sync

Every run converts the whole site again (it's fast and deterministic), then compares the result with what is on
GitHub and commits only the difference.

## What happens to each file

WP2Astro remembers the blob of every file it pushed (the *manifest*, stored per repo in the `wpta_sync_manifest`
option). For each file:

| Situation | Result |
|---|---|
| Same as on GitHub | Nothing to do |
| Changed in WordPress, untouched in the repo | Pushed |
| **Edited in the repo since the last sync** | **Kept as it is in the repo**, listed as "kept" |
| Added to the repo by someone (not generated) | Kept |
| No longer generated (post deleted/unpublished), untouched in the repo | Removed |
| No longer generated, but edited in the repo | Kept |

If nothing changed, no commit is made. To hand a file you edited back to WordPress, delete it in the repo (or restore
the last synced version); the next sync writes it again.

The very first sync to a repo replaces everything that's in it (normally just the README GitHub created).

## Things that make a run cheap

- Unchanged files aren't uploaded; files are compared by their git blob id, computed locally.
- AI results are cached: the layout is reused while the theme and menu are unchanged, and a cleaned post is reused
  while the post is unchanged.
- `CONVERSION_REPORT.md` and `AGENTS.md` contain no timestamps, so they only change when the content does.

## Auto-sync

Turn on **Sync to GitHub automatically**. A publish, update, unpublish or delete of any exported post type schedules
one sync two minutes later (more edits in that window join the same sync). It runs in WP-Cron in ~20-second slices.

Background syncs never replace `src/layouts/BaseLayout.astro` in the repo, and they don't call the AI for the layout.

WP-Cron runs when the site gets visits. On low-traffic sites, trigger it from the system cron:

```bash
*/5 * * * * cd /path/to/wordpress && wp cron event run --due-now
```

## History

The dashboard and `wp wp2astro status` list the last 20 runs: trigger (button, auto-sync, WP-CLI), entries,
changed/removed/kept counts, and links to the commit and zip. Zips are kept for 24 hours.
