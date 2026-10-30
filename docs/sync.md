# Keep the site in sync

After the first conversion, the **Convert to Astro** button becomes **Sync now**. A sync builds the whole site again
(it's fast and gives the same result for the same content), compares it with what is on GitHub, and commits only the
difference. Files you or an agent edited in the repository are never overwritten.

## Sync by hand

1. Go to **WP2Astro**.
2. On the **Overview** tab, click **Sync now**.
3. When it finishes, you see either **Synced to GitHub** ("3 file(s) changed, 0 removed.") with a **View commit**
   button, or **Already up to date** ("Nothing changed since the last sync.").

If nothing changed, no commit is made.

## What happens to each file

WP2Astro remembers what it pushed last time. For each file it compares three versions: the one WordPress generates
now, the one on GitHub, and the one it pushed last time.

| Situation | Result |
|---|---|
| Same as on GitHub | Nothing to do |
| Changed in WordPress, untouched in the repository | Pushed |
| **Edited in the repository since the last sync** | **Kept as it is in the repository**, counted as "kept" |
| Added to the repository by someone (not generated) | Left alone |
| No longer generated (post deleted or unpublished), untouched in the repository | Removed |
| No longer generated, but edited in the repository | Left alone |

**To hand a file back to WordPress**, delete it in the repository, or restore the version WordPress last pushed. The
next sync writes it again.

**Keep in mind:** a kept file stops receiving changes from WordPress. For example, if you edit `src/site.json` in the
repository, menu and category changes in WordPress no longer reach it until you hand it back.

## Turn on auto-sync

1. Connect GitHub first (see [Connect GitHub](github.md)).
2. In **Settings → Keep in sync**, turn on **Sync to GitHub automatically**.
3. Click **Save settings**.

Or flip the **Auto-sync** switch under **Connections** on the **Overview** tab. The status card then shows
**Auto-sync is on**.

From then on, publishing, updating, unpublishing or deleting a post, page or exported custom post type starts one sync
about two minutes later. More edits within those two minutes join the same sync. If another sync is running, the new
one waits and tries again five minutes later.

Background syncs differ from **Sync now** in two ways:

- They never replace `src/layouts/BaseLayout.astro` in the repository, and they don't ask the AI for a new layout.
  To regenerate the layout, click **Sync now**.
- They don't build a zip.

## Auto-sync on quiet sites

Background syncs run on WP-Cron, which only runs when someone visits your site. On a site with little traffic, a sync
can wait until the next visit. To run it on time, ask your host to call WP-Cron from the system cron every five
minutes, for example with WP-CLI:

```bash
*/5 * * * * cd /path/to/wordpress && wp cron event run --due-now
```

See [Hooking WP-Cron into the system task scheduler](https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/)
in the WordPress handbook.

## See past runs

The **Activity** tab lists the last 20 runs, and the **Overview** tab shows the latest five:

| Column | Shows |
|---|---|
| **When** | How long ago it started (hover for the date and time) |
| **How** | **Button**, **Auto-sync** or **WP-CLI** |
| **Result** | **Synced to GitHub**, **Nothing to push**, **Converted to a zip**, **Stopped** or **Run failed**, with the files changed, removed and kept, or why it failed |
| (links) | **Commit ↗** and, for 24 hours, **Zip** |

`wp wp2astro status` shows the same list on the command line.

## What makes a sync cheap

- Files are compared by their Git fingerprint, computed on your server, so unchanged files aren't uploaded.
- AI results are cached. The layout is reused while the theme and menu are unchanged, and a cleaned post is reused
  while the post is unchanged.
- Generated files contain no timestamps, so they only change when the content does.

## If someone pushes during a sync

WP2Astro never force-pushes. If the repository changed while a sync was running, the sync stops with "The repo changed
while syncing (someone pushed). Run the sync again." Run it again: it builds on the new commit and keeps the other
person's changes.

## Related

- [Connect GitHub](github.md)
- [Finish the site with Claude Code](claude-code.md)
- [Troubleshooting](troubleshooting.md)
