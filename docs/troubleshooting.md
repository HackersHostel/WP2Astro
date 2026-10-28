# Troubleshooting

Start with the log. When a run stops, the message under the progress bar turns red and the **Log** below it opens. Each line is one
step, and the last line says what went wrong. Then check `CONVERSION_REPORT.md` in the repository or zip, which lists
anything that needs a look even when the run succeeds.

## The homepage couldn't be loaded (loopback blocked)

**What you see:** "Could not fetch the homepage over HTTP (loopback); continuing without it." The site still converts,
but the AI builds the layout from theme template files instead of the rendered page, which usually looks less like your
site.

**Why:** WP2Astro loads your homepage from the server itself, like WordPress does for scheduled events and Site Health.
Some hosts, firewalls, maintenance-mode plugins or password-protected staging sites block that request.

**What to do:**

1. Go to **Tools → Site Health** and look for "Your site could not complete a loopback request".
2. Turn off maintenance mode or HTTP authentication while you convert, or ask your host to allow requests from the
   server to its own address.
3. Click **Sync now**.

## The layout fell back to the default

**What you see:** "Layout attempt 1 rejected: …" or "Layout attempt 1 failed: …", then "Using the default layout."
The site works but doesn't look like your theme.

**Why:** WP2Astro checks the AI's layout before using it: it must render the menu and stylesheets from the site data,
contain no scripts, and so on. A rejected answer gets one retry. An API error (no credits, a usage limit, a timeout)
isn't retried.

**What to do:**

- For API errors, fix the cause (buy credits, wait for the limit to reset) and click **Sync now**.
- For rejected answers, pick a stronger model, click **Test**, and click **Sync now**. Rejected answers aren't cached,
  so the next run asks again.
- Or keep the default layout and finish it with `/wp2astro-polish-layout` in Claude Code. See
  [Finish the site with Claude Code](claude-code.md).

## OpenRouter doesn't bring you back

**What you see:** after **Connect OpenRouter**, you stay on openrouter.ai, land on an error page, or see "OpenRouter
didn't finish connecting. Try again, or paste an API key instead."

**Why:** OpenRouter returns you to your WordPress admin address. That fails when your browser can't open that address
(an internal hostname, a private network), when the site runs on plain HTTP and the redirect is refused, or when you
took more than 30 minutes.

**What to do:** create a key on openrouter.ai under **Settings → Keys**, paste it into **API key** and click
**Save settings**. See [Use OpenRouter](openrouter.md#use-an-api-key-instead).

## ChatGPT usage limit reached

**What you see:** "Your ChatGPT plan usage limit for this app is reached."

**Why:** your plan's limit, or the limit you set for WP2Astro in ChatGPT, is used up.

**What to do:** click **Usage & limits ↗** to check or raise the limit, wait for it to reset, or switch to
OpenRouter for now. The conversion itself still finishes, with the default layout.

Related messages:

- "Your ChatGPT session ended. Sign in with ChatGPT again." — click **Sign in with ChatGPT**.
- "This ChatGPT account or workspace can't use its plan in other apps." — your workspace doesn't allow plan usage in
  other apps. Use another account or OpenRouter.

## A sync stopped because the repository changed

**What you see:** "The repo changed while syncing (someone pushed). Run the sync again."

**Why:** someone pushed to the repository between the start and the end of the sync. WP2Astro never force-pushes, so
it stopped instead of overwriting their commit.

**What to do:** click **Sync now**. The new sync builds on their commit and keeps their changes.

## Another conversion is running

**What you see:** "Another conversion is running right now (job …). Wait for it to finish."

**Why:** a sync is already running, often an auto-sync after you published a post. A run that stopped responding stops
blocking new runs after 10 minutes.

**What to do:** wait a minute and try again. If you are sure nothing is running, choose to start anyway when asked.

## GitHub didn't accept the token

**What you see:** "GitHub didn't accept that token, so it wasn't saved." or a sync that stops with a GitHub error
such as "Bad credentials".

**What to do:** check that the token hasn't expired and has **Administration** and **Contents** set to read and write
for all repositories, then paste it again. See [Connect GitHub](github.md#connect-with-a-personal-access-token).

## Auto-sync doesn't run

- Check that the dashboard header shows **Auto-sync: on** and GitHub is connected.
- Only published posts, pages and exported custom post types start a sync; drafts don't.
- On quiet sites, WP-Cron waits for the next visit. See [Auto-sync on quiet sites](sync.md#auto-sync-on-quiet-sites).

## Still stuck?

Ask in [Q&A](https://github.com/HackersHostel/wp2astro/discussions/categories/q-a) or open a
[bug report](https://github.com/HackersHostel/wp2astro/issues/new/choose). Include the run log (click **Log**) and
`CONVERSION_REPORT.md`, with any keys or tokens removed.
