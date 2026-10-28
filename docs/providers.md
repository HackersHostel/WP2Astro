# Choose an AI model

AI is optional. It does two jobs during a conversion:

- **Layout:** it rebuilds your theme's page shell (header, menu, footer) as `src/layouts/BaseLayout.astro`, from the
  rendered homepage.
- **Shortcode cleanup:** it replaces shortcodes left behind by plugins you removed, such as `[vc_row]` or `[button]`.

Each job is a single request with strict checks. If the answer fails the checks, WP2Astro uses the default layout or
leaves the shortcode in place and lists it in `CONVERSION_REPORT.md`, so a bad answer never breaks the conversion.
Nothing heavy runs on your server: only a few HTTPS requests.

## Compare the options

| Option in the **AI model** card | You pay with | How to connect | Guide |
|---|---|---|---|
| **ChatGPT plan** | Your ChatGPT Plus or Pro plan | **Sign in with ChatGPT** | [Use your ChatGPT plan](chatgpt.md) |
| **OpenRouter** | OpenRouter credits (some models are free) | **Connect OpenRouter**, or paste an API key | [Use OpenRouter](openrouter.md) |
| **Claude plan** | Your Claude Pro or Max plan | Claude Code and the agent kit, after converting | [Finish the site with Claude Code](claude-code.md) |
| **No AI** | — | — | Default layout; leftover shortcodes listed in the report |

Claude plans can only be used in Claude's own apps, so the **Claude plan** tile doesn't connect anything in WordPress.
It points you to the agent kit instead.

## Choose which AI steps run

Under **AI steps** in the **AI model** card:

- **Rebuild the theme's header, menu and footer as an Astro layout**
- **Clean up dead shortcodes in posts**

Clear either one to skip it. Click **Save settings**.

## Check a model before you convert

The ChatGPT plan and OpenRouter panels have two buttons next to **Model**:

- **Load models** — fetches the models you can use and fills the list. Click the **Model** field to pick one.
- **Test** — sends a one-line prompt and shows the answer and how long it took.

## Results are cached

AI results are saved in `wp-content/uploads/wp2astro/cache/`. A sync only calls the model again when the theme, the
menu or a post with dead shortcodes changed, so repeated syncs don't spend credits or plan usage. Background syncs
(auto-sync) never call the model for the layout.

## What is sent to the model

The homepage HTML trimmed to the header, menu and footer (no scripts or styles), the menu items, and the full
Markdown of posts that contain dead shortcodes. See [Privacy and external services](privacy.md).

## For developers

To route requests through your own OpenAI-compatible gateway, use the
[`wpta_provider` filter](developers/hooks.md#wpta_provider).
