# AI providers

AI does two jobs: it rebuilds the theme's page shell (header, menu, footer) as `src/layouts/BaseLayout.astro`, and it
replaces dead shortcodes left by removed plugins. Both are single prompts with strict validation and a fallback, so
small local models work and a failed answer never breaks the conversion.

Results are cached in `wp-content/uploads/wp2astro/cache/`. A sync only calls the model again when the theme, the menu
or a post with dead shortcodes changed.

| Provider | Pays with | Connect | Notes |
|---|---|---|---|
| Ollama | Free | Server URL | Default. `ollama pull gemma3`. WordPress must reach it. |
| LM Studio | Free | Server URL | Start the local server, load a model. |
| No AI | Free | — | Default layout, dead shortcodes listed in the report. |
| ChatGPT plan | ChatGPT Plus/Pro | Sign in with ChatGPT | No API key. Counts toward the plan's limits. |
| OpenRouter | Credits (some models free) | Connect button or key | Balance and **Buy credits** in the dashboard. |
| Claude API | Anthropic API key | Key | For a Claude *subscription*, see below. |
| OpenAI API | OpenAI API key | Key | |
| DeepSeek | DeepSeek API key | Key | |
| Custom | Whatever the endpoint needs | URL + optional key | Any OpenAI-compatible `/chat/completions`. |

Every provider has **Load models** (asks the provider for its list) and **Test** (sends a one-line prompt and shows the
answer and timing).

## Local models (Ollama, LM Studio)

The request comes from the WordPress server, not your browser. If WordPress runs on your laptop (Local, wp-env,
Docker), the default URLs work. On a remote host, run the model server somewhere WordPress can reach and set
**Server URL** to it — for Ollama, set `OLLAMA_HOST=0.0.0.0` and protect the port.

On a laptop, a 4B–12B model takes from seconds to a couple of minutes for the layout. If it times out, raise the limit:

```php
add_filter( 'wpta_llm_timeout', fn() => 600 );
```

## ChatGPT plan (Sign in with ChatGPT)

WP2Astro uses OpenAI's [ChatGPT plan usage](https://developers.openai.com/siwc/token-sharing-open-source) for
open-source, self-hosted apps. Each WordPress install registers itself as its own client the first time someone signs
in.

OpenAI only allows a `http://127.0.0.1:1455/auth/callback` callback, which points at the computer running the browser,
not at your server. So:

1. Click **Sign in with ChatGPT**. A ChatGPT tab opens.
2. Approve WP2Astro. The tab then fails to load `127.0.0.1:1455`. That is expected.
3. Copy that address and paste it into the dashboard. WordPress exchanges the one-time code (protected by PKCE and a
   state check) for tokens.

Tokens are encrypted in the database and refreshed automatically (access tokens last an hour, refresh tokens rotate).
Signing out revokes the session at OpenAI. Usage counts toward your plan; set a per-app limit in
[ChatGPT → Settings → Usage](https://chatgpt.com/settings/usage).

## Claude subscriptions

Anthropic doesn't allow Claude Pro/Max subscriptions to be used from other apps, so WP2Astro won't ask for one. Two
supported ways to use Claude:

- **Claude API key** in the dashboard (billed by Anthropic per token).
- **Your subscription through Claude Code.** Every generated repo ships `CLAUDE.md` and skills. Open the repo in Claude
  Code and run `/wp2astro-polish-layout`, `/wp2astro-fix-shortcodes` or `/wp2astro-verify`. Later syncs from WordPress
  keep the files Claude Code changed.

## OpenRouter

**Connect OpenRouter** uses OpenRouter's OAuth (PKCE): you log in on openrouter.ai and come back with an API key tied to
your account. No key copying. The dashboard then shows your credit balance and a **Buy credits** button (credits are
bought on OpenRouter; the balance refreshes when you come back to the tab). Models with `:free` in their ID cost
nothing. Your site must be reachable by your browser at its admin URL for the callback; otherwise paste a key.
