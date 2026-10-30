# Use OpenRouter

[OpenRouter](https://openrouter.ai) gives you open models such as Gemma, Llama and Qwen, plus Claude, GPT and hundreds
of other models, with one account. You pay as
you go with credits, and some models are free. WP2Astro connects to your account with one click, shows your balance
and links to **Buy credits** from the dashboard.

## Before you begin

- An OpenRouter account. You can create one during connect.
- Your browser must be able to open your WordPress admin at its normal address, because OpenRouter sends you back
  there. If it can't, use an API key instead (see [Use an API key instead](#use-an-api-key-instead)).

## Connect

1. Go to **WP2Astro** and, in **Settings → AI model**, select **OpenRouter**.
2. Click **Connect OpenRouter**. You go to openrouter.ai.
3. Log in or create an account, and approve the request.
4. OpenRouter sends you back to WordPress, which shows "OpenRouter is connected. Pick a model and save."

OpenRouter creates an API key for WP2Astro, named after your site (for example "WP2Astro – example.com"). WordPress
stores it encrypted. You can see or delete it on openrouter.ai under **Settings → Keys**.

## Pick a model

The default model is `google/gemma-4-31b-it`, an open model: cheap, fast and good at layouts. To run an open model on
your own hardware instead, see [Run a local model](local-models.md).

1. Click **Load models**. Free models are listed first and marked "free".
2. Click the **Model** field and pick one, or type a model ID.
3. Click **Test** to send a one-line prompt and see the answer.
4. Click **Save settings**.

## Use free models

Models with `:free` in their ID cost nothing. You can also type `openrouter/free` as the model, and OpenRouter picks
an available free model for each request. Free models have lower rate limits and their layouts vary in quality, so
use **Test** and check the result.

## Check your balance and buy credits

When OpenRouter is connected, **Settings → AI model** and **Connections** on the **Overview** tab show your balance, for example "$4.20 in credits".
A balance under $0.50 is shown in red.

1. Click **Buy credits ↗**. OpenRouter's credits page opens in a new tab.
2. Buy credits there.
3. Come back to the WordPress tab. The balance refreshes on its own, or click **Refresh balance**.

## What it costs

You pay OpenRouter's price for the model you pick. A conversion makes one layout request, plus one request per post
that contains dead shortcodes. Converting a small blog with the default Gemma model costs a fraction of a US cent.
Syncs reuse cached
results, so they usually cost nothing.

## Use an API key instead

If **Connect OpenRouter** doesn't bring you back (for example, the admin is only reachable on a private network, or
the site runs on plain HTTP), paste a key instead:

1. On openrouter.ai, go to **Settings → Keys** and create a key.
2. In **Settings → AI model**, paste it into **API key** and click **Save settings**.

## Disconnect

Tick **Remove** next to **API key** and click **Save settings**. Then delete the key on openrouter.ai under
**Settings → Keys**. To connect a different account, click **Reconnect**.

## Related

- [Choose an AI model](providers.md)
- [Troubleshooting](troubleshooting.md#openrouter-doesnt-bring-you-back)
- [Privacy and external services](privacy.md)
