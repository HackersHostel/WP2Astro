# Getting started

This page takes you from installing WP2Astro to a working Astro site: either a zip you download or a GitHub repository
that stays in sync with WordPress. It takes about ten minutes, and you don't need Node.js, a terminal or any coding
on your side.

## Before you begin

- WordPress 6.5 or later and PHP 8.0 or later.
- An administrator account (WP2Astro needs the `manage_options` capability).
- Optional: a ChatGPT Plus or Pro plan, or an OpenRouter account, for the AI steps. See
  [Choose an AI model](providers.md).
- Optional: a GitHub account, to get a repository instead of a zip.

## Install the plugin

1. Download `wp2astro.zip` from the [latest release](https://github.com/HackersHostel/wp2astro/releases/latest).
2. In WordPress, go to **Plugins → Add New Plugin** and click **Upload Plugin**.
3. Choose `wp2astro.zip`, click **Install Now**, then **Activate Plugin**.
4. Click **WP2Astro** in the admin menu.

The first time, a three-step setup opens: **Choose AI**, **Connect GitHub** and **Convert**. Both AI and GitHub are
optional, and **Skip setup** in the corner takes you straight to the dashboard. Click **Help** at the top of the
screen at any time for a short explanation of each part.

## Step 1: pick an AI model (optional)

AI rebuilds your theme's header, menu and footer as an Astro layout, and replaces shortcodes whose plugins are gone.
Pick one of the five cards:

- **Local model** — enter the address of your model server, such as Ollama with Gemma. See
  [Run a local model](local-models.md).
- **ChatGPT plan** — click **Sign in with ChatGPT**. See [Use your ChatGPT plan](chatgpt.md).
- **Claude plan** — nothing to connect. WordPress converts with the default layout, and you finish the site in
  Claude Code on your Pro or Max plan. See [Finish the site with Claude Code](claude-code.md).
- **OpenRouter** — click **Connect OpenRouter**. See [Use OpenRouter](openrouter.md).
- **No AI** — nothing to set up. You get a working site with a clean default layout.

Click **Continue**.

## Step 2: connect GitHub (optional)

If you only want a zip, click **Skip — download a zip**.

1. Click **Connect GitHub**.
2. Click **Copy**, then **Open github.com/login/device ↗**, paste the code and approve.
3. Back in WordPress, the page shows **✓ Connected as @your-name**.
4. Check the **Repository** name. WP2Astro creates it on the first run. Leave **Private repository** on to keep it
   private.
5. Click **Continue**.

See [Connect GitHub](github.md) for the token option and details.

## Step 3: choose what to build

The last step lists what gets built. Everything is on by default:

| Option | What you get |
|---|---|
| **SEO titles and descriptions** | Titles and descriptions from Yoast SEO, Rank Math or All in One SEO, plus canonical and Open Graph tags |
| **RSS feed, sitemap and robots.txt** | `feed.xml`, `sitemap.xml` and `robots.txt`; old `/feed/` and `/wp-sitemap.xml` links redirect |
| **Site search** | Static full-text search with Pagefind; old `?s=` links land on it |
| **Custom post types** | Public custom post types with their own folders and archives |
| **Agent kit** | `AGENTS.md`, `CLAUDE.md` and Claude Code skills, so an AI agent can finish the site |
| **Custom fields** | Public post meta and ACF fields in each entry's frontmatter. Off by default, because fields can hold private data |

Media is copied into the repository by default. To use Git LFS, leave it on WordPress or upload it to S3, Cloudflare
R2 or Bunny, see [Choose where media goes](media.md).

Once GitHub is connected, turn on **Sync after I publish** to sync automatically. See
[Keep the site in sync](sync.md).

## Step 4: convert

1. Check the summary on the left: your content, the AI model and where the output goes.
2. Click **Convert to Astro**.
3. Keep the tab open while the steps run. A small blog takes under a minute; the AI layout step can take a
   minute or two on its own. **Stop** ends the run after the current step; nothing is pushed.

When the run finishes:

- **With GitHub**, click **Open repo** to see the new repository.
- **Without GitHub**, click **Download zip**. The download is kept for 24 hours.

Click **Go to the dashboard**. The **Overview** tab shows the status, what was converted, your connections and the
Claude Code commands; **Activity** lists every run; **Settings** holds everything from the setup, saved with
**Save settings**. From now on the main button reads **Sync now**: each sync pushes only what changed.

## Step 5: check the result

Open `CONVERSION_REPORT.md` in the repository or the zip. It lists the model used, how many entries and media files
were converted, and anything that needs a look, such as shortcodes the AI could not replace.

To see the site, either:

- deploy it (see [Deploy the site](deploy.md)), or
- run it on your computer with Node.js 22.23.4 or later:

  ```bash
  npm install
  npm run dev
  ```

  then open `http://localhost:4321`.

## Next steps

- [Keep the site in sync](sync.md) — what is pushed, what is kept, auto-sync.
- [Finish the site with Claude Code](claude-code.md) — polish the layout and fix leftovers.
- [Deploy the site](deploy.md) — FlyWP and DigitalOcean, or any static host.
- [Troubleshooting](troubleshooting.md) — when a run stops or the layout doesn't look right.

## What isn't converted

A static site has no server-side code, so these don't carry over: comments, forms, WooCommerce and other features that
need a server, sidebar widgets, and custom fields (such as ACF) beyond what the post content renders. Drafts, private
and password-protected posts are never exported.
