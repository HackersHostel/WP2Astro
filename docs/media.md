# Choose where media goes

Images, PDFs and other uploads your posts use can live in four places. Pick one under **Settings → Media** and click
**Save settings**. The next run applies it to every entry.

| Option | Where the files are | Good for |
|---|---|---|
| **Copy into the repo** (default) | `public/wp-content/uploads/` in the repository | Small and medium sites; everything in one place |
| **Copy into the repo with Git LFS** | The repository, stored by GitHub's Git LFS | Sites with a lot of media that still want one repository |
| **Leave on WordPress** | Your WordPress site, as now | Keeping WordPress online as the media server |
| **Upload to storage (S3, R2, Bunny)** | Amazon S3, Cloudflare R2, an S3-compatible service or Bunny Storage | Large media libraries, served from a CDN |

Every option keeps the WordPress path of each file, such as `/wp-content/uploads/2024/01/photo.jpg`, so you can move
between them later.

## Size limit

Uploading a file takes a few times its size in PHP memory, so WP2Astro skips very large files:

- **Copy into the repo**: files over 25 MB.
- **Git LFS** and **Upload to storage**: files over 50 MB.

A skipped file keeps its WordPress address in the page, so it still shows as long as WordPress is online.
`CONVERSION_REPORT.md` lists every skipped file under **Media not copied**. Developers can change the limits with the
[`wpta_max_media_bytes`](developers/hooks.md#wpta_max_media_bytes) filter.

## Copy into the repo with Git LFS

WP2Astro uploads each file to GitHub's [Git LFS](https://git-lfs.com) storage and commits a small pointer file in its
place, plus a `.gitattributes` file that marks `public/wp-content/uploads/**` as LFS. The zip download always contains
the real files.

Before you choose it:

- Your host must download LFS files when it builds the site. Check its documentation for Git LFS; a host that doesn't
  will publish the pointer files instead of your images.
- GitHub's [storage and bandwidth quotas for Git LFS](https://docs.github.com/repositories/working-with-files/managing-large-files/about-storage-and-bandwidth-usage)
  apply to your account.

## Leave on WordPress

Nothing is copied. Links in your posts point at the files on your WordPress site (for example
`https://example.com/wp-content/uploads/2024/01/photo.jpg`), so WordPress must stay online at the same address.

## Upload to storage

WP2Astro uploads each file to a bucket or storage zone, then links it from the **Public URL** you enter. Files are only
uploaded again when they change.

1. Under **Settings → Media**, select **Upload to storage (S3, R2, Bunny)**.
2. Pick the **Storage** and fill in the fields for it (below).
3. Click **Test storage**. WP2Astro uploads a small file, `wp2astro-check.txt`, and reads it back from the
   **Public URL**.
4. Click **Save settings**.

The secret is stored encrypted, like your other keys.

### Amazon S3 and S3-compatible services

| Field | Value |
|---|---|
| **Endpoint** | Leave empty for Amazon S3. For other services, their S3 endpoint, such as `https://nyc3.digitaloceanspaces.com` |
| **Bucket or storage zone** | The bucket name |
| **Region** | The bucket's region, such as `eu-west-1` |
| **Access key ID** and **Secret access key or password** | An access key that may write to the bucket (`s3:PutObject`) |
| **Public URL** | Where the bucket's files are public: a CloudFront or other CDN address, or the bucket's website address |

### Cloudflare R2

| Field | Value |
|---|---|
| **Endpoint** | `https://<account id>.r2.cloudflarestorage.com`, from the bucket's settings in the Cloudflare dashboard |
| **Bucket or storage zone** | The bucket name |
| **Region** | Leave empty (R2 uses `auto`) |
| **Access key ID** and **Secret access key or password** | An R2 API token with **Object Read & Write** on the bucket |
| **Public URL** | The bucket's custom domain, or its `https://pub-….r2.dev` address |

### Bunny Storage

| Field | Value |
|---|---|
| **Endpoint** | Leave empty for the main region (`https://storage.bunnycdn.com`), or your zone's regional hostname, such as `https://ny.storage.bunnycdn.com` |
| **Bucket or storage zone** | The storage zone name |
| **Secret access key or password** | The zone's password, under **FTP & API Access** |
| **Public URL** | The pull zone linked to the storage zone, such as `https://example.b-cdn.net` |

## Change the option later

Switching sends the change on the next run: moving away from the repo removes the copied files from it (the files on
WordPress and in storage stay), and moving to the repo copies them in. Files already in storage are not deleted when
you switch away from it.

See also [Privacy and external services](privacy.md) for what is sent to each storage service.
