# Run a local model

Use an open model such as Gemma, Llama or Qwen on a machine you control. It's free, and your content goes to your own
model server instead of an AI company.

## Before you begin

- A model server with an OpenAI-compatible API: [Ollama](https://ollama.com), [LM Studio](https://lmstudio.ai),
  the llama.cpp server or vLLM.
- Your **WordPress server** must be able to reach the model server. Your browser isn't involved:
  - Model server on the same machine as WordPress: use `localhost`.
  - Model server on another machine: use an address the WordPress server can reach, such as a private network IP.
- A model that fits your machine. Small models work: WP2Astro checks every answer and falls back to the default
  layout when an answer fails the checks.

## Set up Ollama

1. Install Ollama from [ollama.com/download](https://ollama.com/download).
2. Download a model:

   ```bash
   ollama pull gemma4
   ```

3. Give the model room for the layout prompt. Ollama's default context is 4096 tokens, which cuts off the page
   WP2Astro sends, and the answer is then rejected. Start Ollama with a larger context:

   ```bash
   OLLAMA_CONTEXT_LENGTH=8192 ollama serve
   ```

4. Ollama listens on `http://localhost:11434`. If WordPress runs on another machine, also set
   `OLLAMA_HOST=0.0.0.0` so it accepts connections from your network.

## Connect WP2Astro

1. Go to **WP2Astro** and, in **Settings → AI model**, select **Local model**.
2. In **Server URL**, enter your server's API address. For Ollama on the same machine, keep
   `http://localhost:11434/v1`.
3. Click **Load models**, then click the **Model** field and pick one, or type a model ID such as `gemma4`.
4. Click **Test**. You see the model's answer and how long it took.
5. Click **Save settings**.

## Other model servers

| Server | Default **Server URL** |
|---|---|
| Ollama | `http://localhost:11434/v1` |
| LM Studio (start its local server first) | `http://localhost:1234/v1` |
| llama.cpp (`llama-server`) | `http://localhost:8080/v1` |
| vLLM | `http://localhost:8000/v1` |

## If it's slow

A local model can take a few minutes for the layout step, depending on your hardware. WP2Astro waits up to 300
seconds for each answer. Developers can change that with the
[`wpta_llm_timeout` filter](developers/hooks.md#wpta_llm_timeout). Without a GPU, one layout answer can take longer
than that: on a 4-core CPU, Gemma 4 E4B took about five minutes, so allow 30 minutes or more there.

Small models answer the layout step less reliably. On that CPU, Gemma 4 E2B's layouts were rejected and the default
layout was used, while Gemma 4 E4B's passed. If the layout falls back, try the next size up. Results are cached, so syncs don't ask the model
again unless your theme, menu or a post with dead shortcodes changed.

## What is sent

The same prompts as with any other model (see [Choose an AI model](providers.md#what-is-sent-to-the-model)), but only
to the server you entered. See [Privacy and external services](privacy.md).

## Related

- [Choose an AI model](providers.md)
- [Troubleshooting](troubleshooting.md)
