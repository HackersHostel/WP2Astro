# GitHub

WP2Astro creates the repo (private by default) and pushes over the GitHub API. No `git` binary is needed on the
server. Each run is one commit; unchanged files are not uploaded again (see [sync.md](sync.md)).

## Connect

**Connect GitHub** uses GitHub's device flow: the dashboard shows a code, you enter it at
`github.com/login/device`, approve, and the dashboard picks up the connection. The token is stored encrypted.

**Or use a token:** create a [fine-grained personal access token](https://github.com/settings/personal-access-tokens/new)
with access to *All repositories* (so it can create the new repo) and these permissions:

- Administration: Read and write (create the repo)
- Contents: Read and write (push)

WP2Astro checks the token when you save it and shows whose it is.

To disconnect, use **Disconnect** in the dashboard, then revoke the app at
[github.com/settings/applications](https://github.com/settings/applications) or delete the token.

## If someone pushes while a sync runs

The ref update is not forced. If the branch moved, the sync stops with "the repo changed while syncing"; run it again
and it builds on the new commit.

## For maintainers: the OAuth app

The **Connect GitHub** button needs the public client ID of an OAuth app with device flow enabled. Until one is set,
the dashboard only offers the token option.

1. GitHub → the **HackersHostel** organization → **Settings → Developer settings → OAuth Apps → New OAuth App**.
2. Application name `WP2Astro`, homepage `https://github.com/HackersHostel/wp2astro`, callback URL
   `https://github.com/HackersHostel/wp2astro` (required by the form, unused by the device flow).
3. Create it, then tick **Enable Device Flow** and save.
4. Copy the **Client ID** (no client secret is needed — don't create one) into `WPTA_Connect::GITHUB_CLIENT_ID` in
   `includes/class-wpta-connect.php`, and release.

The device flow requests the `repo` scope, because OAuth apps can't create private repos with less. White-label builds
can use their own app through the `WP2ASTRO_GITHUB_CLIENT_ID` constant or the `wpta_github_client_id` filter.
