# AI providers

AI does two jobs: it rebuilds the theme's page shell (header, menu, footer) as `src/layouts/BaseLayout.astro`, and it
replaces dead shortcodes left by removed plugins. Both are single prompts with strict validation and a fallback, so
cheap models work and a failed answer never breaks the conversion.

WP2Astro keeps this lightweight: nothing runs on your server except a few HTTPS requests. Results are cached in
`wp-content/uploads/wp2astro/cache/`, so a sync only calls the model again when the theme, the menu or a post with dead
shortcodes changed.

| Provider | Pays with | Connect | Notes |
|---|---|---|---|
| ChatGPT plan | ChatGPT Plus/Pro | Sign in with ChatGPT | No API key. Counts toward the plan's limits. |
| Claude plan | Claude Pro/Max | Claude Code + the agent kit | Runs in Claude Code, not in the plugin (see below). |
| OpenRouter | Credits (some models free) | Connect button or key | Default model `anthropic/claude-haiku-5.5`. Balance and **Buy credits** in the dashboard. |
| No AI | — | — | Default layout, dead shortcodes listed in the report. |

OpenRouter and ChatGPT have **Load models** (asks for the model list) and **Test** (sends a one-line prompt and shows
the answer and timing).

To route OpenRouter-style requests through your own OpenAI-compatible gateway (LiteLLM, a company proxy), filter the
provider:

```php
add_filter( 'wpta_provider', function ( $p ) {
	if ( 'openrouter' === $p['id'] ) {
		$p['base_url'] = 'https://llm-gateway.example.com/v1';
	}
	return $p;
} );
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

- **Your subscription through Claude Code.** Every generated repo ships `CLAUDE.md` and skills. Convert with *No AI*
  (or any provider), open the repo in Claude Code and run `/wp2astro-polish-layout`, `/wp2astro-fix-shortcodes` or
  `/wp2astro-verify`. Later syncs from WordPress keep the files Claude Code changed.
- **Claude models through OpenRouter** (the default model is Claude Haiku), paid with OpenRouter credits.

## OpenRouter

**Connect OpenRouter** uses OpenRouter's OAuth (PKCE): you log in on openrouter.ai and come back with an API key tied to
your account. No key copying. The dashboard then shows your credit balance and a **Buy credits** button (credits are
bought on OpenRouter; the balance refreshes when you come back to the tab). Models with `:free` in their ID cost
nothing, and `openrouter/free` picks a free model for you. Your site must be reachable by your browser at its admin
URL for the callback; otherwise paste a key from openrouter.ai.
