# Finish the site with Claude Code

A conversion gets your content, URLs and theme styles right, but a layout rebuilt in one AI request is rarely
pixel-perfect. Every repository WP2Astro creates includes an **agent kit**, so an AI coding agent on your computer can
finish the job: [Claude Code](https://claude.com/claude-code) on your Claude plan, or Codex, Cursor and others.

## Why a Claude plan isn't used inside the plugin

Anthropic only allows Claude Pro and Max plans in Claude's own apps, such as Claude Code. A WordPress plugin may not
reuse them, so WP2Astro never asks for Claude credentials. Instead, you convert in WordPress and then run Claude Code
yourself on the repository. That is allowed, runs on your plan, and gives better results than a single request,
because Claude Code can build the site and compare it with the original.

To use Claude models inside the plugin, pick **OpenRouter**; its default model is Claude Haiku. See
[Use OpenRouter](openrouter.md).

## What the agent kit contains

Leave **Agent kit** ticked in the **What to build** card (it is on by default). Each conversion then adds:

| File | For |
|---|---|
| `AGENTS.md` | Any coding agent: project layout, commands, rules, and the open tasks from this conversion |
| `CLAUDE.md` | Claude Code: loads `AGENTS.md` and lists the skills below |
| `.claude/skills/wp2astro-polish-layout/` | `/wp2astro-polish-layout` — make `src/layouts/BaseLayout.astro` match the live site |
| `.claude/skills/wp2astro-fix-shortcodes/` | `/wp2astro-fix-shortcodes` — replace dead shortcodes listed in `CONVERSION_REPORT.md` |
| `.claude/skills/wp2astro-verify/` | `/wp2astro-verify` — build the site and check that every old URL exists |

The open tasks in `AGENTS.md` come from `CONVERSION_REPORT.md`. For example, if the AI layout fell back to the
default, the first task is to rebuild the layout.

## Finish the site

1. Convert with any option in the **AI model** card. **No AI** is fine.
2. Clone the repository to your computer and open it in Claude Code:

   ```bash
   git clone https://github.com/your-name/your-site-astro.git
   cd your-site-astro
   claude
   ```

3. Run the skills you need, one at a time:
   - `/wp2astro-polish-layout`
   - `/wp2astro-fix-shortcodes`
   - `/wp2astro-verify`
4. Review the changes, commit and push.

With Codex or another agent, ask it to "do the open tasks in AGENTS.md".

## Your edits are kept

Later syncs from WordPress never overwrite a file you changed in the repository. If Claude Code rewrites
`BaseLayout.astro` or fixes a post, the next sync leaves those files alone and lists them as "kept". See
[Keep the site in sync](sync.md).

## Drive WP2Astro from an agent

Agents with shell access to the WordPress server can run conversions themselves:

```bash
wp wp2astro convert
wp wp2astro status
```

See [WP-CLI commands](developers/wp-cli.md).

## Related

- [Choose an AI model](providers.md)
- [Keep the site in sync](sync.md)
