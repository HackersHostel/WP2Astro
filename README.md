# WP to Astro

A WordPress plugin that converts the site it runs on into an Astro project, then pushes it to a new GitHub repo.

- **Same URLs.** Every post, page, category and tag keeps its WordPress permalink.
- **Same look, mostly.** The theme's CSS, images and fonts are copied, and an AI model rebuilds the header, nav and footer as an Astro layout.
- **Dead shortcodes fixed.** Leftovers from removed plugins (`[vc_row]`, `[button]`, …) are cleaned up by the model, and every change is listed in `CONVERSION_REPORT.md`.
- **Any model.** Works with any OpenAI-compatible `/chat/completions` API. The default is **Gemma, running locally with Ollama**; OpenRouter, DeepSeek or anything else also work.
- **AI is optional.** With no model configured you still get a working Astro site with a default layout.

## Install

1. Copy this folder to `wp-content/plugins/wp-to-astro` (or upload the zip in **Plugins → Add New**).
2. Activate it and open **Tools → WP to Astro**.

Requires WordPress 6.5+ and PHP 8.0+. No Composer dependencies.

## Pick a model

| Provider | Base URL | Model |
|---|---|---|
| Ollama (local, default) | `http://localhost:11434/v1` | `gemma3` (run `ollama pull gemma3` first) |
| OpenRouter | `https://openrouter.ai/api/v1` | any model ID, e.g. `google/gemma-3-27b-it` |
| DeepSeek | `https://api.deepseek.com` | a model ID from DeepSeek's docs |
| Custom | any OpenAI-compatible URL | — |

With Ollama, your content never leaves your machine. WordPress has to be able to reach Ollama, so run both on the same machine (Local, wp-env, Docker) or point the Base URL at the Ollama host.

## Connect GitHub (optional)

Create a [fine-grained personal access token](https://github.com/settings/personal-access-tokens/new) with **Administration** and **Contents** set to read & write, and paste it into the settings. The plugin creates the repo, private by default, and pushes everything as one commit. Running it again adds a new commit to the same repo.

Without a token you get a `.zip` download instead.

## What you get

```
src/content/posts/*.md   posts as Markdown with frontmatter
src/content/pages/*.md   pages
src/site.json            title, menu, categories, tags, stylesheets
src/layouts/             BaseLayout (AI or default) + PostLayout
src/pages/               home, catch-all for permalinks, pagination, archives, 404
public/theme/            the old theme's CSS, images and fonts
public/wp-content/uploads/  media that the content references
server.mjs               zero-dependency static server (`npm start`)
```

## Host it: FlyWP + DigitalOcean

1. In [FlyWP](https://flywp.com), connect DigitalOcean and create a server.
2. Add a [Node.js site](https://flywp.com/features/nodejs-deployments/) from the GitHub repo.
3. Build: `npm install && npm run build` · Start: `npm start`.

FlyWP's free plan covers one server and one site. The droplet is billed by DigitalOcean.

## How it works

The conversion runs as short AJAX steps (`init → content → media → ai_layout → ai_cleanup → package → github`), so it doesn't hit PHP time limits. The AI steps are single-shot prompts with strict validation and a fallback, with no tool calling, so small local models work. See [`PRD.md`](PRD.md) for the full spec.

## Not converted

Comments, forms, search, WooCommerce and other features that need a server.

## License

GPL-2.0-or-later
