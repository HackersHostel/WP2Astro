# Troubleshooting

Start with the log. When a run stops, the progress card says **The run stopped**, the message under it turns red, and the **Log** below the steps opens. Each line is one
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

## Load models can't reach the local model server

**What you see:** an error after **Load models** or **Test** with **Local model** selected, ending in "Is the model
server running, and can this WordPress server reach …?"

**Why:** the request comes from your WordPress server, not your browser. `localhost` means the WordPress server itself.

**What to do:**

1. Check that the model server is running (for Ollama, `ollama list`).
2. If it runs on another machine, enter that machine's address in **Server URL** and make the server listen on your
   network (for Ollama, `OLLAMA_HOST=0.0.0.0`).
3. Click **Load models** again. See [Run a local model](local-models.md).

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

## A sync stopped because GitHub listed only part of the repository

**What you see:** "GitHub returned only part of the file list for this repo (it has too many files), so a sync could
delete files. Sync stopped; nothing was changed."

**Why:** for very large repositories, GitHub shortens the file list it returns. Syncing with a partial list could
remove files from the repository, so WP2Astro stops instead.

**What to do:** remove files the site doesn't need from the repository (large media, for example), or sync to a new,
empty repository by changing **Repository** in **Settings → GitHub**.

## Another conversion is running

**What you see:** "Another conversion is running right now (job …). Wait for it to finish."

**Why:** a sync is already running, often an auto-sync after you published a post. A run that stopped responding stops
blocking new runs after 10 minutes.

**What to do:** wait a minute and try again. If you are sure nothing is running, choose to start anyway when asked.
If you closed or reloaded the tab of a run you started, the **Overview** tab says **A run from this screen hasn't
finished**: click **Start again**. The old run stops with "Stopped: a newer run took over" and pushes nothing.

## Media upload failed

**What you see:** **Test storage** says "Upload failed", or `CONVERSION_REPORT.md` lists files under **Media not
copied** with "upload failed" and the provider's message, such as `SignatureDoesNotMatch` or `HTTP 401`.

**Why:** the endpoint, bucket, region or key is wrong, or the key may not write to the bucket. A message such as
"Operation timed out" means the **Endpoint** didn't answer: each upload waits about 2 seconds per MB of the file
(at least 20 seconds, at most 2 minutes). Files that fail stay linked from WordPress, so the page still shows them.

**What to do:** check the fields in **Settings → Media** against [Choose where media goes](media.md#upload-to-storage),
click **Test storage**, then sync again. If the test uploads but can't read the file back, check the **Public URL** and
that the bucket or zone allows public reads.

## Images show as small text files after deploying

**Why:** media is stored with Git LFS, and the host published the pointer files instead of downloading the images.

**What to do:** turn on Git LFS support in your host's build settings, or pick another option in
**Settings → Media**. See [Choose where media goes](media.md#copy-into-the-repo-with-git-lfs).

## The menu doesn't open on small screens

**Why:** WP2Astro copies the scripts your theme loads on the homepage, so a theme that loads its menu script only on
other pages, or from a plugin, has no menu script on the Astro site. An AI layout can also use markup the theme's
script doesn't recognize. The default layout adds a **Menu** button that works with or without the theme's script.

**What to do:**

1. Check that the menu works on your WordPress homepage on a phone; WP2Astro copies what that page loads.
2. Click **Sync now** to copy the theme's scripts again.
3. If an AI layout still has a broken menu, run `/wp2astro-polish-layout` in Claude Code, or pick **No AI** in
   **Settings → AI model** and click **Sync now** to use the default layout. See
   [Finish the site with Claude Code](claude-code.md).

## GitHub didn't accept the token

**What you see:** "GitHub didn't accept that token, so it wasn't saved." or a sync that stops with a GitHub error
such as "Bad credentials".

**What to do:** check that the token hasn't expired and has **Administration** and **Contents** set to read and write
for all repositories, then paste it again. See [Connect GitHub](github.md#connect-with-a-personal-access-token).

## Auto-sync doesn't run

- Check that **Auto-sync** is on under **Connections** on the **Overview** tab and GitHub is connected.
- Only published posts, pages and exported custom post types start a sync; drafts don't.
- On quiet sites, WP-Cron waits for the next visit. See [Auto-sync on quiet sites](sync.md#auto-sync-on-quiet-sites).

## Still stuck?

Ask in [Q&A](https://github.com/HackersHostel/wp2astro/discussions/categories/q-a) or open a
[bug report](https://github.com/HackersHostel/wp2astro/issues/new/choose). Include the run log (click **Log**) and
`CONVERSION_REPORT.md`, with any keys or tokens removed.
