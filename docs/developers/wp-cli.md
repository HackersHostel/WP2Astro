# WP-CLI commands

Converts and syncs from the command line. Coding agents such as Claude Code and Codex use these commands to drive
WP2Astro. They use the settings saved in the dashboard, and their runs appear under **Recent runs** as **WP-CLI**.

## wp wp2astro convert

Converts the site to Astro and pushes it to GitHub when connected.

The command runs the same steps as **Convert to Astro** / **Sync now** in the dashboard, without time limits, and
prints each step with its progress. On success it prints the folder that holds the generated project and, when
something was pushed, the commit URL.

### Options

`[--[no-]ai]`
: Use the configured AI model for the layout and shortcode cleanup. Default: on.

`[--[no-]github]`
: Push to GitHub when connected. Default: on.

`[--zip=<path>]`
: Also copy the project zip to this path.

`[--force]`
: Start even if another conversion looks like it is running.

### Examples

    # Convert and push with the dashboard settings
    $ wp wp2astro convert
    Job k3j9x0q2m1za
    [  5%] Fetched the rendered homepage.
    ...
    Commit: https://github.com/your-name/your-site-astro/commit/1a2b3c…
    Success: Astro project ready in /var/www/html/wp-content/uploads/wp2astro/k3j9x0q2m1za/site

    # Build a zip only: no AI, no push
    $ wp wp2astro convert --no-ai --no-github --zip=./site.zip

    # Start even if a sync seems to be running
    $ wp wp2astro convert --force

### Exit status

`0` on success. `1` when another conversion is running (without `--force`) or the conversion failed; the error
message says why.

## wp wp2astro status

Shows the AI provider, the GitHub connection, whether auto-sync is on, and recent runs.

### Examples

    $ wp wp2astro status
    AI:      google/gemma-4-31b-it (OpenRouter)
    GitHub:  your-name/your-site-astro
    Auto-sync: on
    +------------------+---------+--------+---------+---------+------------------------------------------------+
    | started          | trigger | status | entries | changed | commit                                         |
    +------------------+---------+--------+---------+---------+------------------------------------------------+
    | 2026-10-28 14:02 | auto    | done   | 42      | 2       | https://github.com/your-name/your-site-astr... |
    +------------------+---------+--------+---------+---------+------------------------------------------------+

The `commit` column shows the error message for failed runs.

## Related

- [Keep the site in sync](../sync.md)
- [Finish the site with Claude Code](../claude-code.md)
