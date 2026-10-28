# Connect GitHub

When GitHub is connected, WP2Astro creates a repository for your Astro site and pushes to it on every conversion and
sync. It uses GitHub's API directly, so your server doesn't need `git`. Without GitHub you download a zip instead.

## Connect with one click

1. Go to **WP2Astro** and find the **GitHub** card.
2. Click **Connect GitHub**. A code such as `ABCD-1234` appears.
3. Click **Copy**, then **Open github.com/login/device ↗**.
4. On GitHub, paste the code, click **Continue**, and then **Authorize**.
5. Back in WordPress, wait a few seconds. The page reloads and shows **✓ Connected as @your-name (GitHub app)**.

The code is valid for about 15 minutes. If it expires, reload the page and click **Connect GitHub** again.

One-click connect asks GitHub for the `repo` permission, because GitHub only lets apps create private repositories
with it. The token is stored encrypted and only sent to GitHub.

## Connect with a personal access token

Use a token if you prefer to limit access, or if **Connect GitHub** isn't available.

1. Open [Fine-grained personal access tokens](https://github.com/settings/personal-access-tokens/new) on GitHub.
2. Give it a name and an expiry date.
3. Under **Repository access**, choose **All repositories**, so the token can create the new repository.
4. Under **Permissions → Repository permissions**, set:
   - **Administration:** Read and write (to create the repository)
   - **Contents:** Read and write (to push)
5. Click **Generate token** and copy it.
6. In the **GitHub** card, open **Use a personal access token instead**, paste the token and click
   **Save settings**.

WP2Astro checks the token right away and shows whose it is. If GitHub refuses it, you see "GitHub didn't accept that
token, so it wasn't saved" and nothing changes. Check the two permissions and the expiry date.

When the token expires, syncs stop with a GitHub error. Create a new token and paste it the same way.

## Choose the repository

| Setting | What it does |
|---|---|
| **Repository** | The repository name, under your GitHub account. The default is your site name followed by `-astro`. |
| **Private** | Creates a private repository. Ticked by default. |

The repository is created on the first run if it doesn't exist. If it already exists, WP2Astro uses it: the first
sync overwrites files that have the same path as generated files (such as `README.md`) and leaves other files alone.
A new name is the safest choice.

Changing **Private** later doesn't change an existing repository. Change its visibility on GitHub under
**Settings → General → Danger Zone**.

WP2Astro creates and syncs repositories under the account that connected. Organization repositories aren't supported
yet.

## Check the connection

Click **Check repo**. You see either "Token works (@your-name). The repo will be created on the first run." or the
repository's name, visibility and last push.

## Disconnect

1. Click **Disconnect** and confirm. The repository itself is not touched.
2. To revoke access completely:
   - one-click connect: remove **WP2Astro** at [github.com/settings/applications](https://github.com/settings/applications);
   - token: delete it at [github.com/settings/personal-access-tokens](https://github.com/settings/personal-access-tokens).

## Related

- [Keep the site in sync](sync.md)
- [Deploy the site](deploy.md)
- [Privacy and external services](privacy.md)
