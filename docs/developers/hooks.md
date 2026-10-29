# Hooks

WP2Astro has seven filters. Each is documented in the code with a docblock that follows the
[WordPress PHP documentation standards](https://developer.wordpress.org/coding-standards/inline-documentation-standards/php/);
this page matches those docblocks and adds an example for each.

Put the examples in a small must-use plugin (`wp-content/mu-plugins/wp2astro-tweaks.php`) or your theme's
`functions.php`. There are no actions to hook into.

| Filter | Since | Changes |
|---|---|---|
| [`wpta_brand`](#wpta_brand) | 0.2.0 | Name and links in wp-admin and generated files |
| [`wpta_provider`](#wpta_provider) | 0.2.0 | An AI provider's endpoint, model or key |
| [`wpta_post_types`](#wpta_post_types) | 0.2.0 | Which post types are exported and watched by auto-sync |
| [`wpta_llm_timeout`](#wpta_llm_timeout) | 0.2.0 | Seconds one AI request may take |
| [`wpta_github_api`](#wpta_github_api) | 0.2.0 | The GitHub REST API base URL |
| [`wpta_github_client_id`](#wpta_github_client_id) | 0.2.0 | The OAuth app behind **Connect GitHub** |
| [`wpta_update_repo`](#wpta_update_repo) | 0.2.0 | The GitHub repository plugin updates come from |

## wpta_brand

Filters the name and links shown in wp-admin and written into generated files. White-label builds use it, or the
`WP2ASTRO_BRAND_*` constants, to ship the plugin under their own name. See [White-label](white-label.md).

Defined in `includes/class-wpta-settings.php`, `WPTA_Settings::brand()`.

**Parameters**

- `$brand` *(array)*
  - `name` *(string)* Product name: admin menu, page title, generated README, commit messages.
  - `by` *(string)* Credit in the dashboard footer. `''` hides it.
  - `url` *(string)* Product homepage.
  - `docs` *(string)* Base URL of the documentation. Help links append `/{page}.md`.
  - `support` *(string)* Where to report problems.
  - `forum` *(string)* Where to ask questions. `''` hides the link.
  - `logo` *(string)* Logo URL, shown at 40×40 px.

**Example**

```php
add_filter( 'wpta_brand', function ( array $brand ): array {
	return array_merge(
		$brand,
		array(
			'name' => 'SiteShift',
			'by'   => 'Acme Agency',
			'docs' => 'https://github.com/acme/siteshift/blob/main/docs',
		)
	);
} );
```

## wpta_provider

Filters a resolved AI provider before it is used: to point it at an OpenAI-compatible gateway (LiteLLM, a company
proxy) or a test server, or to force a model.

Defined in `includes/class-wpta-settings.php`, `WPTA_Settings::provider()`.

**Parameters**

- `$p` *(array)* The provider definition, resolved with the saved settings.
  - `id` *(string)* Provider ID: `'local'`, `'openrouter'`, `'chatgpt'` or `'none'`.
  - `label` *(string)* Name shown in the dashboard.
  - `group` *(string)* `'local'`, `'plan'`, `'credits'` or `'off'`.
  - `kind` *(string)* Wire format: `'openai'` (`/chat/completions`), `'chatgpt'` (`/responses`) or `'none'`.
  - `base_url` *(string)* API base URL, without a trailing slash.
  - `model` *(string)* Model ID in use.
  - `key` *(bool)* Whether the provider needs an API key.
  - `server` *(bool)* Whether the user sets the base URL (local model servers). Optional.
  - `api_key` *(string)* The decrypted API key, or `''`.

**Example**

```php
// Send OpenRouter-style requests through your own gateway.
add_filter( 'wpta_provider', function ( array $p ): array {
	if ( 'openrouter' === $p['id'] ) {
		$p['base_url'] = 'https://llm-gateway.example.com/v1';
	}
	return $p;
} );
```

## wpta_post_types

Filters the post types exported to the Astro site and watched by auto-sync. Posts go to `src/content/posts/`, pages
to `src/content/pages/`, and every other type to `src/content/items/{type}/`. Only published, non-password-protected
entries are exported.

Defined in `includes/class-wpta-exporter.php`, `WPTA_Exporter::post_types()`.

**Parameters**

- `$types` *(string[])* Post type names: `'post'`, `'page'` and, when **Custom post types** is on, every public,
  viewable custom post type except commerce and builder internals.

**Example**

```php
// Export a non-public "docs" post type, and leave out "testimonial".
add_filter( 'wpta_post_types', function ( array $types ): array {
	$types[] = 'docs';
	return array_diff( $types, array( 'testimonial' ) );
} );
```

## wpta_llm_timeout

Filters how long one AI request may take, in seconds. Raise it for slow models; lower it to fail faster on a host with
a short PHP time limit.

Defined in `includes/class-wpta-llm.php`, `WPTA_LLM::timeout()`.

**Parameters**

- `$seconds` *(int)* Timeout in seconds. Default `300`.

**Example**

```php
add_filter( 'wpta_llm_timeout', fn() => 120 );
```

## wpta_github_api

Filters the base URL of the GitHub REST API: point it at GitHub Enterprise Server or a test server.

Defined in `includes/class-wpta-github.php`, `WPTA_GitHub::__construct()`.

**Parameters**

- `$api` *(string)* API base URL. Default `'https://api.github.com'`.

**Example**

```php
add_filter( 'wpta_github_api', fn() => 'https://github.example.com/api/v3' );
```

**Connect GitHub** always talks to `github.com`. With GitHub Enterprise Server, connect with a personal access token.

## wpta_github_client_id

Filters the client ID of the GitHub OAuth app used by **Connect GitHub** (device flow). Return `''` to hide
**Connect GitHub** and offer only a personal access token. See [GitHub OAuth app](github-oauth-app.md).

Defined in `includes/class-wpta-connect.php`, `WPTA_Connect::github_client_id()`.

**Parameters**

- `$id` *(string)* OAuth app client ID. Default: the `WP2ASTRO_GITHUB_CLIENT_ID` constant, else the Hackers Hostel
  app.

**Example**

```php
add_filter( 'wpta_github_client_id', fn() => 'Ov23liYourOwnApp' );
```

## wpta_update_repo

Filters the GitHub repository that plugin updates are read from. The repository's latest release must carry a
`wp2astro.zip` asset whose top folder is `wp2astro/`. Not available in the WordPress.org build, which has no GitHub
updater (see [Releasing](releasing.md)).

Defined in `includes/class-wpta-updater.php`, `WPTA_Updater::repo()`.

**Parameters**

- `$repo` *(string)* Repository as `owner/name`. Default `'HackersHostel/wp2astro'`.

**Example**

```php
add_filter( 'wpta_update_repo', fn() => 'acme/siteshift' );
```

## Constants

Define these in `wp-config.php`. Each one is the default value of the matching filter.

| Constant | Sets |
|---|---|
| `WP2ASTRO_BRAND_NAME`, `WP2ASTRO_BRAND_BY`, `WP2ASTRO_BRAND_URL`, `WP2ASTRO_BRAND_DOCS`, `WP2ASTRO_BRAND_SUPPORT`, `WP2ASTRO_BRAND_FORUM`, `WP2ASTRO_BRAND_LOGO` | The keys of `wpta_brand` |
| `WP2ASTRO_GITHUB_CLIENT_ID` | `wpta_github_client_id` |
