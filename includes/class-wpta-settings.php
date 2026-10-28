<?php
defined( 'ABSPATH' ) || exit;

/**
 * Settings storage, model providers and branding.
 * Secrets (API keys, GitHub token) are encrypted at rest with WPTA_Secrets.
 */
class WPTA_Settings {

	const OPTION = 'wpta_settings';
	const SLUG   = 'wp2astro';

	/** Fields in `keys` and these top-level fields are stored encrypted. */
	const SECRETS = array( 'github_token' );

	/**
	 * Model providers.
	 * kind: openai (OpenAI-compatible /chat/completions) or chatgpt (Sign in with ChatGPT, Responses API).
	 * group: plan (the user's subscription) | credits (pay as you go) | off.
	 * Claude subscriptions can't be used from other apps; the generated repo's agent kit covers them through Claude Code.
	 */
	public static function providers(): array {
		return array(
			'openrouter' => array(
				'label'    => 'OpenRouter',
				'group'    => 'credits',
				'kind'     => 'openai',
				'base_url' => 'https://openrouter.ai/api/v1',
				'model'    => 'anthropic/claude-haiku-5.5',
				'key'      => true,
				'connect'  => 'openrouter',
				'note'     => 'Claude, GPT and hundreds of other models with one account. Pay as you go with credits, or use openrouter/free for free models.',
				'help'     => 'https://openrouter.ai/models',
			),
			'chatgpt'    => array(
				'label'    => 'ChatGPT plan',
				'group'    => 'plan',
				'kind'     => 'chatgpt',
				'base_url' => 'https://api.openai.com/v1',
				'model'    => '',
				'key'      => false,
				'connect'  => 'chatgpt',
				'note'     => 'Use your ChatGPT Plus or Pro plan through Sign in with ChatGPT. No API key needed.',
				'help'     => 'https://chatgpt.com/settings/usage',
			),
			'none'       => array(
				'label'    => 'No AI',
				'group'    => 'off',
				'kind'     => 'none',
				'base_url' => '',
				'model'    => '',
				'key'      => false,
				'note'     => 'Export only: a working Astro site with a clean default layout. Dead shortcodes are listed in the report, ready for Claude Code to fix.',
				'help'     => '',
			),
		);
	}

	public static function defaults(): array {
		return array(
			'provider'     => 'openrouter',
			'models'       => array(),
			'keys'         => array(),
			'ai_layout'    => 1,
			'ai_cleanup'   => 1,
			'github_token' => '',
			'github_login' => '',
			'github_auth'  => '',
			'repo_name'    => sanitize_title( get_bloginfo( 'name' ) ?: 'my-site' ) . '-astro',
			'repo_private' => 1,
			'auto_sync'    => 0,
			'seo'          => 1,
			'feeds'        => 1,
			'search'       => 1,
			'cpts'         => 1,
			'agent_kit'    => 1,
		);
	}

	/** Decrypted settings. */
	public static function get(): array {
		$saved = get_option( self::OPTION, array() );
		$s     = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		foreach ( self::SECRETS as $f ) {
			$s[ $f ] = WPTA_Secrets::open( (string) $s[ $f ] );
		}
		$s['keys'] = array_map( fn( $v ) => WPTA_Secrets::open( (string) $v ), (array) $s['keys'] );
		// 0.1 kept one plain-text key for the selected provider.
		if ( ! empty( $s['api_key'] ) && empty( $s['keys'][ $s['provider'] ] ) ) {
			$s['keys'][ $s['provider'] ] = (string) $s['api_key'];
		}
		return $s;
	}

	/** Merge $changes into the stored settings (plain values in, encrypted at rest). */
	public static function update( array $changes ): void {
		$s = array_merge( self::get(), $changes );
		unset( $s['api_key'], $s['base_url'], $s['model'], $s['base_urls'] ); // Fields of earlier versions.
		foreach ( self::SECRETS as $f ) {
			$s[ $f ] = WPTA_Secrets::seal( (string) $s[ $f ] );
		}
		$s['keys'] = array_map( fn( $v ) => WPTA_Secrets::seal( (string) $v ), array_filter( (array) $s['keys'], 'strlen' ) );
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $s, '', false );
		} else {
			update_option( self::OPTION, $s, false );
		}
	}

	/**
	 * The active provider, resolved: definition + base_url, model and key in use.
	 *
	 * @param string $id Provider id; defaults to the selected one.
	 */
	public static function provider( string $id = '' ): array {
		$s   = self::get();
		$id  = $id ?: $s['provider'];
		$all = self::providers();
		$p   = $all[ $id ] ?? $all['none'];

		$p['id']      = isset( $all[ $id ] ) ? $id : 'none';
		$p['model']   = trim( (string) ( $s['models'][ $id ] ?? '' ) ) ?: $p['model'];
		$p['api_key'] = (string) ( $s['keys'][ $id ] ?? '' );

		/**
		 * Filters a resolved AI provider before it is used.
		 *
		 * Use it to point a provider at an OpenAI-compatible gateway (LiteLLM, a company proxy) or a test server,
		 * or to force a model.
		 *
		 * @since 0.2.0
		 *
		 * @param array $p {
		 *     The provider definition from WPTA_Settings::providers(), resolved with the saved settings.
		 *
		 *     @type string $id       Provider ID: 'openrouter', 'chatgpt' or 'none'.
		 *     @type string $label    Name shown in the dashboard.
		 *     @type string $group    'plan', 'credits' or 'off'.
		 *     @type string $kind     Wire format: 'openai' (/chat/completions), 'chatgpt' (/responses) or 'none'.
		 *     @type string $base_url API base URL, without a trailing slash.
		 *     @type string $model    Model ID in use.
		 *     @type bool   $key      Whether the provider needs an API key.
		 *     @type string $api_key  The decrypted API key, or ''.
		 * }
		 */
		return (array) apply_filters( 'wpta_provider', $p );
	}

	/** Whether the selected provider is configured enough to try. */
	public static function ai_enabled(): bool {
		$p = self::provider();
		if ( 'none' === $p['kind'] || '' === $p['base_url'] || '' === $p['model'] ) {
			return false;
		}
		if ( 'chatgpt' === $p['kind'] ) {
			return WPTA_ChatGPT::connected();
		}
		return ! $p['key'] || '' !== $p['api_key'];
	}

	public static function github_connected(): bool {
		return '' !== self::get()['github_token'];
	}

	/** Sanitize the settings form (admin-post). Secrets: blank = keep. */
	public static function from_form( array $in ): array {
		$old       = self::get();
		$providers = self::providers();
		$out       = array();

		$out['provider'] = isset( $providers[ $in['provider'] ?? '' ] ) ? $in['provider'] : $old['provider'];

		$out['models'] = $old['models'];
		foreach ( (array) ( $in['models'] ?? array() ) as $id => $model ) {
			if ( isset( $providers[ $id ] ) ) {
				$out['models'][ $id ] = sanitize_text_field( wp_unslash( $model ) );
			}
		}
		$out['keys'] = $old['keys'];
		foreach ( (array) ( $in['keys'] ?? array() ) as $id => $key ) {
			$key = trim( sanitize_text_field( wp_unslash( $key ) ) );
			if ( isset( $providers[ $id ] ) && '' !== $key ) {
				$out['keys'][ $id ] = $key;
			}
		}
		foreach ( (array) ( $in['clear_keys'] ?? array() ) as $id => $on ) {
			unset( $out['keys'][ $id ] );
		}

		$tok = trim( sanitize_text_field( wp_unslash( $in['github_token'] ?? '' ) ) );
		if ( '' !== $tok ) {
			$out['github_token'] = $tok;
			$out['github_auth']  = 'pat';
			$out['github_login'] = '';
		}

		$repo              = preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) wp_unslash( $in['repo_name'] ?? '' ) );
		$out['repo_name']  = $repo ?: $old['repo_name'];
		foreach ( array( 'ai_layout', 'ai_cleanup', 'repo_private', 'auto_sync', 'seo', 'feeds', 'search', 'cpts', 'agent_kit' ) as $flag ) {
			$out[ $flag ] = empty( $in[ $flag ] ) ? 0 : 1;
		}
		return $out;
	}

	/**
	 * Name and links shown in the admin and in generated files.
	 * Licensed white-label builds override this with the `wpta_brand` filter or the WP2ASTRO_BRAND_* constants.
	 */
	public static function brand(): array {
		$brand = array(
			'name'    => defined( 'WP2ASTRO_BRAND_NAME' ) ? WP2ASTRO_BRAND_NAME : 'WP2Astro',
			'by'      => defined( 'WP2ASTRO_BRAND_BY' ) ? WP2ASTRO_BRAND_BY : 'Hackers Hostel',
			'url'     => defined( 'WP2ASTRO_BRAND_URL' ) ? WP2ASTRO_BRAND_URL : 'https://github.com/HackersHostel/wp2astro',
			'docs'    => defined( 'WP2ASTRO_BRAND_DOCS' ) ? WP2ASTRO_BRAND_DOCS : 'https://github.com/HackersHostel/wp2astro/blob/main/docs',
			'support' => defined( 'WP2ASTRO_BRAND_SUPPORT' ) ? WP2ASTRO_BRAND_SUPPORT : 'https://github.com/HackersHostel/wp2astro/issues',
			'forum'   => defined( 'WP2ASTRO_BRAND_FORUM' ) ? WP2ASTRO_BRAND_FORUM : 'https://github.com/HackersHostel/wp2astro/discussions/categories/q-a',
			'logo'    => defined( 'WP2ASTRO_BRAND_LOGO' ) ? WP2ASTRO_BRAND_LOGO : WPTA_URL . 'assets/logo.svg',
		);

		/**
		 * Filters the name and links shown in wp-admin and written into generated files.
		 *
		 * Licensed white-label builds use it (or the WP2ASTRO_BRAND_* constants) to ship the plugin under their own name.
		 *
		 * @since 0.2.0
		 *
		 * @param array $brand {
		 *     @type string $name    Product name: admin menu, page title, generated README, commit messages.
		 *     @type string $by      Credit in the dashboard footer. '' hides it.
		 *     @type string $url     Product homepage.
		 *     @type string $docs    Base URL of the documentation. Help links append "/{page}.md".
		 *     @type string $support Where to report problems.
		 *     @type string $forum   Where to ask questions. '' hides the link.
		 *     @type string $logo    Logo URL, shown at 40×40 px.
		 * }
		 */
		return apply_filters( 'wpta_brand', $brand );
	}
}
