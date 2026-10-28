# Security policy

WP2Astro runs inside wp-admin with administrator rights, stores API keys and tokens, and pushes your site to GitHub.
We take reports about any of that seriously.

## Report a vulnerability

**Don't open a public issue, discussion or pull request.** Report it privately through GitHub:
[**Report a vulnerability**](https://github.com/HackersHostel/wp2astro/security/advisories/new).

Include what you can of: what an attacker can do and what they need first, steps or a proof of concept against a local
install, and the version you tested. We'll acknowledge the report, keep you updated, and credit you in the advisory
unless you'd rather we didn't. Please give us a reasonable chance to release a fix before disclosing.

## Supported versions

Only the latest release and `main` are supported.

## In scope

- Any WP2Astro action or AJAX endpoint usable without `manage_options` or without a valid nonce
- Keys or tokens (AI providers, ChatGPT, OpenRouter, GitHub) reaching the browser, logs, generated files, or any host
  other than the one they belong to
- Reading job files or downloading another job's zip without permission
- Exporting drafts, private or password-protected content
- OAuth flaws: state/PKCE bypass, accepting a callback from another user's sign-in, token mix-ups
- A sync overwriting or deleting files in the repo that it promised to keep
- Script injection through content, model output or settings into wp-admin

## Not in scope

- What a site administrator can already do in WordPress
- Content of the generated site that came from the WordPress site itself
- Issues in WordPress core, other plugins, or the AI and Git hosting services
