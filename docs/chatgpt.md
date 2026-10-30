# Use your ChatGPT plan

With **Sign in with ChatGPT**, WP2Astro uses your ChatGPT Plus or Pro plan for the AI steps. You don't need an
OpenAI API key, and nothing is billed separately: usage counts toward your plan's limits.

WP2Astro uses OpenAI's [ChatGPT plan usage for open-source, self-hosted apps](https://developers.openai.com/siwc/token-sharing-open-source).
Each WordPress site registers as its own app the first time someone signs in.

## Before you begin

- A ChatGPT Plus or Pro plan. Some workspace plans don't allow plan usage in other apps.
- Pop-ups allowed for your WordPress admin, because sign-in opens a new tab.

## Sign in

1. Go to **WP2Astro** and, in **Settings → AI model**, select **ChatGPT plan**.
2. Click **Sign in with ChatGPT**. A ChatGPT tab opens, and a paste field appears in WordPress.
3. In the ChatGPT tab, sign in if asked and approve WP2Astro.
4. The tab now tries to open an address that starts with `http://127.0.0.1:1455/auth/callback` and shows an error
   such as "This site can't be reached". **This is expected.** See [Why you see a 127.0.0.1 page](#why-you-see-a-127001-page).
5. Copy the full address from that tab's address bar.
6. Back in WordPress, paste it into the field and click **Finish sign-in**.
7. WP2Astro shows **✓ Signed in as** your email, and the **Model** list fills in. Pick a model.
8. Click **Test** to check the connection, then **Save settings**.

The address is only valid for this sign-in attempt and for 30 minutes. If you see "This sign-in expired" or "That
address belongs to a different sign-in attempt", click **Sign in with ChatGPT** again and paste the newest address.

## Why you see a 127.0.0.1 page

OpenAI only allows apps like WP2Astro to return to `http://127.0.0.1:1455/auth/callback`. That address points at the
computer running your browser, not at your WordPress server, so the browser can't load it. The address still carries
a one-time code. When you paste it, WordPress exchanges the code for tokens directly with OpenAI.

The exchange is protected: the code only works together with a secret that never left your server (PKCE), and the
pasted address must match the sign-in attempt you started (state check).

## Check and limit your usage

Every AI request counts toward your ChatGPT plan, the same as using ChatGPT itself.

- Click **Usage & limits ↗** in **Settings → AI model** to open
  [ChatGPT → Settings → Usage](https://chatgpt.com/settings/usage). There you can see what WP2Astro used and set a
  limit for it.
- AI results are cached, so a sync only uses your plan again when the theme, the menu or a post with dead shortcodes
  changed.

If the limit is reached, the run log shows "Your ChatGPT plan usage limit for this app is reached". The conversion
still finishes, with the default layout. Try again later, raise the limit, or switch to another option.

## Sign out

Click **Sign out** in **Settings → AI model**. WP2Astro asks OpenAI to end the session, then deletes the tokens. If
OpenAI doesn't confirm it, you can also remove the app in ChatGPT under **Settings → Security**.

## How your tokens are stored

Tokens are encrypted in the WordPress database with a key derived from your site's salts. Access tokens last about an
hour and are refreshed automatically. They are only ever sent to OpenAI.

## Related

- [Choose an AI model](providers.md)
- [Troubleshooting](troubleshooting.md#chatgpt-usage-limit-reached)
- [Privacy and external services](privacy.md)
