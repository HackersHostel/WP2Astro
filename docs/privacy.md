# Privacy and external services

WP2Astro sends nothing anywhere until you configure a service, and then only when you convert, sync, test a
connection, load a model list or check a balance. No analytics, no telemetry. API keys and tokens are stored encrypted
with a key derived from your site's salts and are only sent to the service they belong to.

| Service | When | What is sent | Terms / privacy |
|---|---|---|---|
| Your own site (loopback) | Every conversion | A GET of the homepage, to read the theme's rendered HTML | — |
| Ollama / LM Studio / custom endpoint | AI steps, Test, Load models | Trimmed homepage HTML (header, menu, footer; no scripts) and the menu; full Markdown of posts that contain dead shortcodes | Your server |
| OpenAI — Sign in with ChatGPT (`auth.openai.com`, `api.openai.com`) | Sign-in, token refresh, sign-out; AI steps | OAuth codes and tokens; the same prompts as above | [Terms](https://openai.com/policies/terms-of-use), [Privacy](https://openai.com/policies/privacy-policy) |
| OpenRouter (`openrouter.ai`) | Connect, balance, AI steps | OAuth code exchange; API key; the same prompts | [Terms](https://openrouter.ai/terms), [Privacy](https://openrouter.ai/privacy) |
| Anthropic (`api.anthropic.com`) | AI steps, Test, Load models | API key; the same prompts | [Terms](https://www.anthropic.com/legal/commercial-terms), [Privacy](https://www.anthropic.com/legal/privacy) |
| OpenAI API, DeepSeek | AI steps, Test, Load models | API key; the same prompts | [OpenAI](https://openai.com/policies/privacy-policy), [DeepSeek](https://platform.deepseek.com/downloads/DeepSeek%20Open%20Platform%20Privacy%20Policy.html) |
| GitHub (`github.com`, `api.github.com`) | Connect, Check repo, sync | Device-flow codes; token; every file of the generated site (posts, pages, media, theme assets) | [Terms](https://docs.github.com/site-policy/github-terms/github-terms-of-service), [Privacy](https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement) |
| GitHub releases (`api.github.com`) | Twice a day, by WordPress's update check | Nothing but the request for the latest release | as above |

Only published, non-password-protected content is exported. Drafts, private posts, comments and user data are never
read or sent. With a local model (or no AI) and no GitHub connection, nothing leaves your server.
