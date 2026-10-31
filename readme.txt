=== WP2Astro ===
Contributors: mahmudurrahmanlabib
Tags: static site, astro, export, migration, github
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn your WordPress site into a fast Astro site with the same URLs and the same look, push it to GitHub, and keep it in sync.

== Description ==

WP2Astro converts the WordPress site it runs on into an [Astro](https://astro.build) project: a fast static site that keeps every URL and the look of your theme. It pushes the project to a GitHub repository and keeps it in sync as you publish, so you can keep writing in WordPress and serve visitors a static site.

Everything happens in wp-admin. You don't need Node.js, a terminal or an application password on your side: open **WP2Astro**, click **Convert to Astro**, and get a repository or a zip.

= Same URLs =

Every post, page, custom post type entry, category and tag keeps its permalink, and so do paginated archives and year, month and author archives. Old links keep working too: changed slugs, `?p=123`, `?page_id=7`, `?s=search` and `/feed/` redirect to the right place.

= Same look =

Your theme's CSS, images, fonts and scripts are copied. An AI model rebuilds the header, menu and footer as an Astro layout from your rendered homepage. Without AI you get a clean default layout.

= Smart sync =

Each sync pushes only the files that changed and removes posts you deleted or unpublished. Files you or a developer edit in the repository are never overwritten. Turn on auto-sync, and the repository updates a couple of minutes after you publish.

= Features =

* **Open models, the AI you already pay for, or none.** Run an open model such as Gemma, Llama or Qwen on your own server (Ollama, LM Studio, llama.cpp, vLLM), sign in with your ChatGPT Plus or Pro plan (no API key), or connect OpenRouter with one click for Gemma, Claude, GPT and free models, with your credit balance and a **Buy credits** button in the dashboard.
* **Finish with Claude Code.** Every repository includes an agent kit (`AGENTS.md`, `CLAUDE.md` and Claude Code skills), so Claude Code on your Claude plan, Codex or another agent can polish what the conversion couldn't.
* **GitHub in one click.** Connect with a one-time code, or a fine-grained token. WP2Astro creates a private repository and pushes to it.
* **SEO kept.** Titles, descriptions, noindex and canonical URLs from Yoast SEO, Rank Math or All in One SEO, plus Open Graph tags on every page.
* **Feeds and sitemap.** An RSS feed, `sitemap.xml` and `robots.txt`; old feed and sitemap URLs redirect.
* **Search.** Static full-text search with Pagefind.
* **Custom post types.** Public custom post types get their own folders and archives.
* **Media where you want it.** Copy media into the repository, store it with Git LFS, leave it on WordPress, or upload it to Amazon S3, Cloudflare R2 or Bunny Storage, with a **Test storage** button.
* **Custom fields, if you want them.** Turn on **Custom fields** to write public post meta and ACF fields to each entry's frontmatter. Off by default.
* **Light on your server.** Work runs in short steps, so it fits shared hosting. AI results are cached, so syncs don't spend credits again.
* **Private by design.** Keys and tokens are encrypted in your database. Nothing is sent anywhere until you connect a service.
* **WP-CLI.** `wp wp2astro convert` and `wp wp2astro status`.

= Documentation =

Click **Help** on the WP2Astro screen for a short guide, or read the [full documentation](https://github.com/HackersHostel/wp2astro/tree/main/docs): getting started, the AI options, GitHub, sync, media, deploying and troubleshooting.

WP2Astro is free and open source, made by [Hackers Hostel](https://hackershostel.com). Development happens on [GitHub](https://github.com/HackersHostel/wp2astro).

== Installation ==

1. In WordPress, go to **Plugins → Add New Plugin**. Search for "WP2Astro" and click **Install Now**, or click **Upload Plugin** and choose `wp2astro.zip`.
2. Click **Activate Plugin**.
3. Click **WP2Astro** in the admin menu.

Then follow the three-step setup:

1. **Choose AI** (optional): select **Local model** and enter your model server's address, select **ChatGPT plan** and click **Sign in with ChatGPT**, select **Claude plan** to finish the site in Claude Code, select **OpenRouter** and click **Connect OpenRouter**, or select **No AI**. Click **Continue**.
2. **Connect GitHub** (optional): click **Connect GitHub**, enter the code on github.com and approve, or click **Skip — download a zip**.
3. **Convert**: click **Convert to Astro**. When it finishes, click **Open repo** or **Download zip**.

Later runs are a click on **Sync now** on the **Overview** tab, or automatic with **Sync to GitHub automatically**.

Requirements: WordPress 6.5 or later, PHP 8.0 or later, and an administrator account. To build or run the generated site, you or your host need Node.js 22.23.4 or later.

See [Getting started](https://github.com/HackersHostel/wp2astro/blob/main/docs/getting-started.md) for every step.

== Frequently Asked Questions ==

= Can I use my Claude subscription? =

Not inside the plugin: Anthropic only allows Claude Pro and Max plans in Claude's own apps. Instead, every repository WP2Astro creates includes an agent kit. Open it in Claude Code and run `/wp2astro-polish-layout` or `/wp2astro-fix-shortcodes`; it runs on your Claude plan. To use Claude models inside the plugin, pick OpenRouter and choose a Claude model.

= Does my content leave my server? =

Only when you connect a service. With **No AI** and no GitHub connection, nothing leaves your server: you download a zip. With GitHub connected, the generated site (published posts, pages, the media they use and your theme's assets) is pushed to your repository. With an AI model, the trimmed homepage HTML, the menu and posts that contain dead shortcodes are sent to that model. With **Local model**, they only go to your own model server. If you upload media to storage, the media files go to the storage service you set up. Drafts, private posts, comments and user data are never read. See the External services section.

= What does OpenRouter cost? =

You pay OpenRouter's price for the model you pick. A conversion makes one layout request, plus one request per post with dead shortcodes; converting a small blog with the default open Gemma model costs a fraction of a US cent. Models with `:free` in their ID cost nothing, and syncs reuse cached results.

= Why does ChatGPT sign-in show a 127.0.0.1 page? =

OpenAI only lets apps like WP2Astro return to `http://127.0.0.1:1455/auth/callback`, which points at your own computer, not your server, so the tab can't load it. That's expected. Copy the full address from that tab, paste it into WP2Astro and click **Finish sign-in**. WordPress then completes the sign-in with OpenAI.

= What happens to files I edit in the repo? =

They are kept. A sync never overwrites a file that changed in the repository since the last sync; it lists it as "kept". To hand a file back to WordPress, delete it in the repository and sync again.

= Do comments, forms or WooCommerce carry over? =

No. A static site has no server-side code, so comments, forms, WooCommerce and other features that need a server aren't converted, and neither are sidebar widgets. Posts, pages, custom post types, categories, tags, menus, media and SEO metadata are, and custom fields (post meta and ACF) when you turn on **Custom fields**.

= Do I need GitHub? =

No. Without GitHub, each conversion gives you a zip of the Astro project to download. GitHub adds sync and lets most hosts deploy on every push.

= Does it work without AI? =

Yes. With **No AI** you get a complete, working site with a clean default layout, and any leftover shortcodes are listed in `CONVERSION_REPORT.md`.

= Where can I host the Astro site? =

Anywhere that runs Node.js or serves static files. Run `npm start` on a Node.js host such as FlyWP with DigitalOcean, or Render, to keep every old WordPress link redirecting, or publish the `dist/` folder on Netlify, Cloudflare Pages or any static host.

= Where do I get help? =

Ask in [Q&A on GitHub](https://github.com/HackersHostel/wp2astro/discussions/categories/q-a), or report a bug in the [issue tracker](https://github.com/HackersHostel/wp2astro/issues). Include the run log (click **Log** on the dashboard).

== Screenshots ==

1. The first-run setup: choose how AI helps (a local model, your ChatGPT or Claude plan, OpenRouter, or none).
2. The Overview tab: status, Sync now, what was converted, your connections and the Claude Code commands.
3. Connect GitHub with a one-time code.
4. A sync in progress: each step, with a Stop button.
5. Settings: AI model, GitHub, what to build and auto-sync on one page.
6. Before and after: a WordPress site and its Astro version.

== Changelog ==

= 0.3.1 =

* First release in the WordPress.org plugin directory.
* Job folders, the AI cache and uninstalling now use WordPress's own file functions.
* The local model's Server URL is cleaned up before Test and Load models use it.

= 0.3.0 =

* New dashboard: a three-step setup on the first visit (Choose AI, Connect GitHub, Convert), then Overview, Activity and Settings tabs. Runs show each step live, with a Stop button.
* Local model provider: run open models such as Gemma, Llama or Qwen on your own server (Ollama, LM Studio, llama.cpp, vLLM). OpenRouter's default model is now the open Gemma 4 31B.
* Claude plan is a choice under AI model: convert, then finish the site in Claude Code with the agent kit.
* Media: copy it into the repository, store it with Git LFS, leave it on WordPress, or upload it to S3, Cloudflare R2 or Bunny Storage.
* Year, month and author archives, and paginated category, tag and custom post type archives, at their WordPress URLs.
* The theme's JavaScript is copied, so menu toggles keep working.
* Optional custom fields (public post meta and ACF) in each entry's frontmatter.
* Netlify settings with 301s for ?p=, ?page_id= and ?s= links.
* Faster syncs on large sites: only changed posts are rendered and only changed files are committed.
* Fixed: non-ASCII slugs, .html permalinks, Markdown-looking text, echoing shortcodes, AI layouts that broke the build, and two runs starting at once.
* New logo.

= 0.2.0 =

First Hackers Hostel release, renamed from "WP to Astro" to WP2Astro.

* New dashboard under its own WP2Astro menu: connect a model and GitHub, convert, sync and see recent runs.
* AI providers: your ChatGPT plan (Sign in with ChatGPT) or OpenRouter (one-click connect, credit balance, Buy credits; Claude, GPT and free models), or no AI. Claude plans work through the agent kit in Claude Code. Local model servers (Ollama) are no longer offered.
* GitHub: one-click connect with the device flow; tokens are checked on save.
* Smart sync: only changed files are committed, removed entries are removed, files edited in the repository are kept, and nothing is committed when nothing changed. Optional auto-sync after publishing. Run history in the dashboard and WP-CLI.
* AI results are cached, so syncs don't spend credits again on an unchanged theme or post.
* SEO: titles, descriptions, noindex and canonical URLs from Yoast SEO, Rank Math or All in One SEO; canonical, Open Graph and Twitter tags on every page.
* RSS feed, sitemap and robots.txt; old feed, sitemap, changed-slug, `?p=`, `?page_id=` and `?s=` URLs redirect.
* Public custom post types with their archives.
* Static search with Pagefind.
* Agent kit in the generated repository: `AGENTS.md`, `CLAUDE.md` and Claude Code skills.
* WP-CLI: `wp wp2astro convert` and `wp wp2astro status`.
* API keys and tokens are encrypted at rest.
* The loopback request to the homepage verifies SSL like WordPress core does.

= 0.1.0 =

* Convert posts and pages to an Astro project with the same URLs, theme CSS, AI layout and shortcode cleanup; push to GitHub or download a zip.

== Upgrade Notice ==

= 0.3.1 =

The first release in the WordPress.org plugin directory. Conversions and syncs work as in 0.3.0.

= 0.3.0 =

New dashboard with a guided setup, open models on your own server (Local model), media options, more archives and many fixes.

= 0.2.0 =

Renamed to WP2Astro, with a new dashboard, ChatGPT plan and OpenRouter sign-in, one-click GitHub, smart sync and auto-sync. Local model servers (Ollama) are no longer offered.

== External services ==

WP2Astro connects to the services below only after you set them up, and only when you convert, sync, test a connection, load a model list or check a balance. It has no analytics or telemetry. Keys and tokens are stored encrypted and each is only sent to the service it belongs to.

= Your own site (loopback request) =

On every conversion and sync, WP2Astro requests your site's homepage from your own server to read the theme's rendered HTML. This request stays on your server and sends nothing to third parties.

= Your own model server (Local model) =

Used when you select **Local model**, to run the AI steps on an open model you host (Ollama, LM Studio, llama.cpp, vLLM).

* When: when you click **Test** or **Load models**, and during conversions and syncs that run an AI step.
* What is sent: the AI prompts described below, only to the **Server URL** you entered. No third party is involved unless that address points to one.

= OpenAI (Sign in with ChatGPT) =

Used when you select **ChatGPT plan** and click **Sign in with ChatGPT**, to run the AI steps on your ChatGPT plan.

* When: when you sign in or out, when tokens are refreshed, when you click **Test** or **Load models**, and during conversions and syncs that run an AI step.
* What is sent: OAuth codes and tokens to `auth.openai.com`; to `api.openai.com`, your homepage HTML trimmed to the header, menu and footer (without scripts or styles), your menu items, and the full text of posts that contain dead shortcodes.
* [Terms of use](https://openai.com/policies/terms-of-use), [Privacy policy](https://openai.com/policies/privacy-policy)

= OpenRouter =

Used when you select **OpenRouter**, to run the AI steps with the model you pick. OpenRouter forwards each request to that model's provider.

* When: when you click **Connect OpenRouter**, when the dashboard shows your credit balance, when you click **Test** or **Load models**, and during conversions and syncs that run an AI step.
* What is sent to `openrouter.ai`: the OAuth code exchange, your API key, your site's address as the app referrer, and the same AI prompts as above.
* [Terms of service](https://openrouter.ai/terms), [Privacy policy](https://openrouter.ai/privacy)

= GitHub =

Used when you connect GitHub, to create your repository and push the generated site to it.

* When: when you click **Connect GitHub** or **Check repo**, when you save a token, and during conversions and syncs.
* What is sent: device-flow codes to `github.com`; your token and every file of the generated site (published posts and pages as Markdown, the media they use and your theme's assets) to `api.github.com`.
* [Terms of service](https://docs.github.com/site-policy/github-terms/github-terms-of-service), [Privacy statement](https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement)

= GitHub Git LFS =

Used when you choose **Copy into the repo with Git LFS** under **Settings → Media**, to store media in your repository's Git LFS storage.

* When: during conversions and syncs.
* What is sent: your token and the media files your posts use, to `github.com` and the storage address GitHub returns for each file.
* [Terms of service](https://docs.github.com/site-policy/github-terms/github-terms-of-service), [Privacy statement](https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement)

= Object storage (Amazon S3, S3-compatible services, Cloudflare R2, Bunny Storage) =

Used when you choose **Upload to storage** under **Settings → Media**, to upload media to the bucket or storage zone you set up.

* When: when you click **Test storage**, and during conversions and syncs.
* What is sent: your access key with a request signature (S3, R2) or your storage password (Bunny), and the media files your posts use, only to the **Endpoint** you entered. **Test storage** also reads one small test file back from your **Public URL**.
* Terms: [AWS service terms](https://aws.amazon.com/service-terms/), [Cloudflare terms](https://www.cloudflare.com/website-terms/), [Bunny terms](https://bunny.net/tos/), or those of the S3-compatible provider you use.

= GitHub releases (only in the build downloaded from GitHub) =

The version downloaded from GitHub checks the `HackersHostel/wp2astro` releases on `api.github.com` for plugin updates when WordPress checks for updates. No site data is sent. The version from WordPress.org doesn't make this request; it updates from WordPress.org.
