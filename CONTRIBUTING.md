# Contributing to WP2Astro

Thanks for helping. By taking part you agree to the [Code of Conduct](CODE_OF_CONDUCT.md). Security problems go to
[SECURITY.md](SECURITY.md), not issues.

## Before you write code

- **Small, obvious fixes:** open a pull request straight away.
- **Anything larger** (a new provider, a new output feature, a UI change): open an issue or an
  [Ideas](https://github.com/HackersHostel/wp2astro/discussions/categories/ideas) discussion first.

## Set up

You need PHP 8.0+, Python 3 and Node 22.23.4+. No API keys or GitHub account: `dev/` has a mock OpenAI-compatible
server (also speaking the ChatGPT Responses format) and a mock GitHub API.

```bash
php tests/run.php                       # unit tests (no WordPress needed)
python3 dev/llm.py & python3 dev/gh.py &  # mocks on :11434 and :9999 (mkdir dev-out first)
python3 dev/drive.py openrouter         # full run through the admin endpoints against a local WP on :8899
```

`dev/README.md` describes the local WordPress test site. Never point `dev/drive.py` at a real site: it overwrites settings.

## Rules

- PHP 8.0+, WordPress 6.5+, no Composer dependencies. Classes `WPTA_*`, options and hooks `wpta_*`.
- Every AJAX handler checks `check_ajax_referer( 'wpta' )` and `manage_options`.
- Job steps stay short; slow work gets its own phase.
- AI is optional: every AI step needs a deterministic fallback and must log why it was used.
- Generated files must be deterministic (no timestamps), or every sync would commit.
- New external requests must be listed in [docs/privacy.md](docs/privacy.md).

## Pull requests

Short imperative subject ("Add Rank Math breadcrumbs"), what changes for users, and what you ran: `php tests/run.php`,
a conversion with `dev/drive.py`, and `npm install && npm run build` on the output. Screenshots for UI changes.
