# Privacy and external services

WP2Astro sends nothing anywhere until you set up a service, and then only when you convert, sync, test a connection,
load a model list or check a balance. There is no analytics and no telemetry. API keys and tokens are stored encrypted
with a key derived from your site's salts, and each is only sent to the service it belongs to.

Only published, non-password-protected content is exported. Drafts, private posts, comments and user data are never
read or sent. With **No AI** and no GitHub connection, nothing leaves your server.

The same list, in WordPress.org format, is in the **External services** section of `readme.txt`. Keep both in step
when a request is added or changed.

| Service | When | What is sent | Terms / privacy |
|---|---|---|---|
| Your own site (loopback) | Every conversion and sync | A GET request for the homepage, to read the theme's rendered HTML. It stays on your server. | — |
| Your own model server (**Local model**) | AI steps, **Test**, **Load models** | The AI prompts below, sent only to the **Server URL** you entered | — |
| OpenAI — Sign in with ChatGPT (`auth.openai.com`, `api.openai.com`) | Sign-in, token refresh, sign-out; AI steps, **Test**, **Load models** | OAuth codes and tokens; the homepage HTML trimmed to header, menu and footer (no scripts or styles) and the menu; the full Markdown of posts that contain dead shortcodes | [Terms](https://openai.com/policies/terms-of-use), [Privacy](https://openai.com/policies/privacy-policy) |
| OpenRouter (`openrouter.ai`) and the model provider it routes to | **Connect OpenRouter**, balance; AI steps, **Test**, **Load models** | OAuth code exchange; API key; your site's address (as the app referrer); the same prompts | [Terms](https://openrouter.ai/terms), [Privacy](https://openrouter.ai/privacy) |
| GitHub (`github.com`, `api.github.com`) | **Connect GitHub**, **Check repo**, saving a token, conversions and syncs | Device-flow codes; token; every file of the generated site (posts, pages, media, theme assets) | [Terms](https://docs.github.com/site-policy/github-terms/github-terms-of-service), [Privacy](https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement) |
| GitHub releases (`api.github.com`), Hackers Hostel build only | When WordPress checks for plugin updates (the answer is cached for 12 hours) | A request for the latest release of `HackersHostel/wp2astro`; no site data | as above |

The WordPress.org build of WP2Astro doesn't contain the GitHub release updater, so it never makes the last request, and
its `readme.txt` leaves that service out.
See [Releasing](developers/releasing.md).

## Suggested text for your privacy policy

WP2Astro only processes content you have already published. If you use it, you can add a sentence like this to your
site's privacy policy:

> We publish a static copy of this site's public content. Published posts and pages, and the media they use, are
> stored in a repository on GitHub and may be processed by an AI service to rebuild the page layout.
