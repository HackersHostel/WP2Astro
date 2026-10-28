# White-label

WP2Astro is free to use as it ships, for any number of sites. Shipping it to clients under your own name, logo and
links is what the Hackers Hostel white-label licence covers — [ask about a licence](https://hackershostel.com).

The code is GPL-2.0-or-later, like all WordPress plugins; the licence covers the Hackers Hostel and WP2Astro names and
logo, rebranded builds, and support.

## Rebrand

Define constants in `wp-config.php` (or a must-use plugin):

```php
define( 'WP2ASTRO_BRAND_NAME', 'SiteShift' );            // menu, page title, generated README, commit messages
define( 'WP2ASTRO_BRAND_BY', 'Acme Agency' );             // footer credit ('' to hide)
define( 'WP2ASTRO_BRAND_URL', 'https://acme.example' );
define( 'WP2ASTRO_BRAND_DOCS', 'https://acme.example/docs' );
define( 'WP2ASTRO_BRAND_SUPPORT', 'https://acme.example/support' );
define( 'WP2ASTRO_BRAND_LOGO', 'https://acme.example/logo.svg' );
define( 'WP2ASTRO_GITHUB_CLIENT_ID', 'Iv1.…' );           // your own GitHub OAuth app (device flow)
```

Or filter everything at once:

```php
add_filter( 'wpta_brand', fn( $b ) => array_merge( $b, array( 'name' => 'SiteShift', 'by' => 'Acme Agency' ) ) );
```

Updates come from `HackersHostel/wp2astro` releases. To serve your own builds, publish releases with a `wp2astro.zip`
asset in your repo and point the updater at it:

```php
add_filter( 'wpta_update_repo', fn() => 'acme/siteshift' );
```

## Other filters

| Filter | Default | Use |
|---|---|---|
| `wpta_post_types` | posts, pages, public custom post types | Add or remove exported post types |
| `wpta_llm_timeout` | `300` | Seconds per AI request |
| `wpta_github_api` | `https://api.github.com` | GitHub Enterprise or a test server |
