# GitHub OAuth app

**Connect GitHub** uses GitHub's [device flow](https://docs.github.com/apps/oauth-apps/building-oauth-apps/authorizing-oauth-apps#device-flow),
which needs the public client ID of an OAuth app with device flow enabled. No client secret is involved, so nothing
secret ships in the plugin. When no client ID is set, the dashboard only offers the personal access token option.

The Hackers Hostel app's client ID is `WPTA_Connect::GITHUB_CLIENT_ID` in `includes/class-wpta-connect.php`.

## Register the app

1. On GitHub, open the **HackersHostel** organization and go to
   **Settings → Developer settings → OAuth Apps → New OAuth App**.
2. Fill in:
   - **Application name:** `WP2Astro`
   - **Homepage URL:** `https://github.com/HackersHostel/wp2astro`
   - **Authorization callback URL:** `https://github.com/HackersHostel/wp2astro` (the form requires it; the device
     flow doesn't use it)
3. Click **Register application**, tick **Enable Device Flow** and click **Update application**.
4. Copy the **Client ID** into `WPTA_Connect::GITHUB_CLIENT_ID`. Don't generate a client secret.
5. Release a new version.

## Scope

The device flow asks for the `repo` scope, because OAuth apps can't create private repositories with less. Users who
want narrower access can use a fine-grained personal access token with **Administration** and **Contents** read and
write instead.

## Use your own app

White-label builds can use their own OAuth app with the `WP2ASTRO_GITHUB_CLIENT_ID` constant or the
[`wpta_github_client_id`](hooks.md#wpta_github_client_id) filter. Return `''` from the filter to hide
**Connect GitHub**.
