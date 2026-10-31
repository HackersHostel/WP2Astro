# WP2Astro documentation

WP2Astro is a WordPress plugin that turns your site into a fast Astro site with the same URLs and the same look,
pushes it to GitHub and keeps it in sync. These pages show you how to use it, one task per page.

Inside WordPress, click **Help** next to the tabs at the top of the **WP2Astro** screen for a short version of each page.

## For site owners

- [Getting started](getting-started.md) — install, convert, and download the zip or open the repository
- [Choose an AI model](providers.md) — what AI does and how the options compare
  - [Run a local model](local-models.md) — Gemma, Llama or Qwen on Ollama, LM Studio, llama.cpp or vLLM
  - [Use your ChatGPT plan](chatgpt.md) — Sign in with ChatGPT, the 127.0.0.1 step, usage limits
  - [Use OpenRouter](openrouter.md) — connect, pick a model, free models, **Buy credits**, balance
  - [Finish the site with Claude Code](claude-code.md) — the agent kit and the `/wp2astro-*` skills
- [Connect GitHub](github.md) — one-click connect, fine-grained tokens, private repositories
- [Keep the site in sync](sync.md) — what is pushed, what is kept, auto-sync
- [Choose where media goes](media.md) — the repo, Git LFS, WordPress, or S3, Cloudflare R2 and Bunny
- [Deploy the site](deploy.md) — FlyWP and DigitalOcean, Render, or any static host
- [Troubleshooting](troubleshooting.md) — fixes for the messages you are most likely to see
- [Privacy and external services](privacy.md) — what is sent where, and when

## For developers

- [Hooks](developers/hooks.md) — filters to change providers, post types, custom fields, media limits, timeouts, GitHub and updates
- [WP-CLI commands](developers/wp-cli.md) — `wp wp2astro convert` and `wp wp2astro status`
- [White-label](developers/white-label.md) — ship WP2Astro under your own brand
- [GitHub OAuth app](developers/github-oauth-app.md) — register the app behind **Connect GitHub**
- [Releasing](developers/releasing.md) — the Hackers Hostel and WordPress.org builds
- [Contributing](../CONTRIBUTING.md) and the [local test setup](../dev/README.md)
