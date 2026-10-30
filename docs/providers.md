# Choose an AI model

AI is optional. It does two jobs during a conversion:

- **Layout:** it rebuilds your theme's page shell (header, menu, footer) as `src/layouts/BaseLayout.astro`, from the
  rendered homepage.
- **Shortcode cleanup:** it replaces shortcodes left behind by plugins you removed, such as `[vc_row]` or `[button]`.

Each job is a single request with strict checks. If the answer fails the checks, WP2Astro uses the default layout or
leaves the shortcode in place and lists it in `CONVERSION_REPORT.md`, so a bad answer never breaks the conversion.
Nothing heavy runs on your server: only a few HTTPS requests.

## Compare the options

The same five choices are step 1 of the first-run setup and the **AI model** section of the **Settings** tab.

| Option in **Settings → AI model** | You pay with | How to connect | Guide |
|---|---|---|---|
| **Local model** | Nothing: an open model (Gemma, Llama, Qwen) on a server you run | Enter its **Server URL** | [Run a local model](local-models.md) |
| **ChatGPT plan** | Your ChatGPT Plus or Pro plan | **Sign in with ChatGPT** | [Use your ChatGPT plan](chatgpt.md) |
| **OpenRouter** | OpenRouter credits (some models are free; the default is the open Gemma model) | **Connect OpenRouter**, or paste an API key | [Use OpenRouter](openrouter.md) |
| **Claude plan** | Your Claude Pro or Max plan | Nothing to connect: convert, then run Claude Code on the repo | [Finish the site with Claude Code](claude-code.md) |
| **No AI** | — | — | Default layout; leftover shortcodes listed in the report |

Claude plans can only be used in Claude's own apps, so choosing **Claude plan** doesn't connect anything in WordPress.
WordPress converts with the default layout and turns on the agent kit, and the panel shows the commands that let
Claude Code finish the site on your plan.

## Choose which AI steps run

At the bottom of **Settings → AI model**:

- **Rebuild the theme's header, menu and footer as an Astro layout**
- **Clean up dead shortcodes in posts**

Clear either one to skip it. Click **Save settings**.

## Check a model before you convert

The Local model, ChatGPT plan and OpenRouter panels have two buttons next to **Model**:

- **Load models** — fetches the models you can use and fills the list. Click the **Model** field to pick one.
- **Test** — sends a one-line prompt and shows the answer and how long it took.

## Results are cached

AI results are saved in `wp-content/uploads/wp2astro/cache/`. A sync only calls the model again when the theme, the
menu or a post with dead shortcodes changed, so repeated syncs don't spend credits or plan usage. Background syncs
(auto-sync) never call the model for the layout.

## What is sent to the model

The homepage HTML trimmed to the header, menu and footer (no scripts or styles), the menu items, and the full
Markdown of posts that contain dead shortcodes. With **Local model**, it only goes to your own server. See
[Privacy and external services](privacy.md).

## For developers

To route requests through your own OpenAI-compatible gateway, use the
[`wpta_provider` filter](developers/hooks.md#wpta_provider).
