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
	 * kind: openai (OpenAI-compatible /chat/completions), anthropic (Messages API), chatgpt (Sign in with ChatGPT, Responses API).
	 * group: free | credits | plan | key — how the user pays.
	 */
	public static function providers(): array {
		return array(
			'ollama'     => array(
				'label'    => 'Ollama',
				'group'    => 'free',
				'kind'     => 'openai',
				'base_url' => 'http://localhost:11434/v1',
				'model'    => 'gemma3',
				'key'      => false,
				'note'     => 'Free. Runs open models such as Gemma on your own machine or server; content never leaves it.',
				'help'     => 'https://ollama.com/download',
			),
			'lmstudio'   => array(
				'label'    => 'LM Studio',
				'group'    => 'free',
				'kind'     => 'openai',
				'base_url' => 'http://localhost:1234/v1',
				'model'    => '',
				'key'      => false,
				'note'     => 'Free. Start the local server in LM Studio and load a model.',
				'help'     => 'https://lmstudio.ai/',
			),
			'openrouter' => array(
				'label'    => 'OpenRouter',
				'group'    => 'credits',
				'kind'     => 'openai',
				'base_url' => 'https://openrouter.ai/api/v1',
				'model'    => 'google/gemma-3-27b-it',
				'key'      => true,
				'connect'  => 'openrouter',
				'note'     => 'One account for hundreds of models, including free ones. Pay as you go with credits.',
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
			'anthropic'  => array(
				'label'    => 'Claude API',
				'group'    => 'key',
				'kind'     => 'anthropic',
				'base_url' => 'https://api.anthropic.com/v1',
				'model'    => 'claude-sonnet-5-5',
				'key'      => true,
				'note'     => 'Claude with an Anthropic API key. To use a Claude subscription instead, finish the site in Claude Code with the agent kit.',
				'help'     => 'https://console.anthropic.com/settings/keys',
			),
			'openai'     => array(
				'label'    => 'OpenAI API',
				'group'    => 'key',
				'kind'     => 'openai',
				'base_url' => 'https://api.openai.com/v1',
				'model'    => '',
				'key'      => true,
				'note'     => 'OpenAI models with an API key.',
				'help'     => 'https://platform.openai.com/api-keys',
			),
			'deepseek'   => array(
				'label'    => 'DeepSeek',
				'group'    => 'key',
				'kind'     => 'openai',
				'base_url' => 'https://api.deepseek.com',
				'model'    => 'deepseek-chat',
				'key'      => true,
				'note'     => 'DeepSeek models with an API key.',
				'help'     => 'https://platform.deepseek.com/api_keys',
			),
			'custom'     => array(
				'label'    => 'Custom',
				'group'    => 'key',
				'kind'     => 'openai',
				'base_url' => '',
				'model'    => '',
				'key'      => true,
				'note'     => 'Any OpenAI-compatible /chat/completions endpoint (vLLM, llama.cpp, LiteLLM, Groq, Together…).',
				'help'     => '',
			),
			'none'       => array(
				'label'    => 'No AI',
				'group'    => 'free',
				'kind'     => 'none',
				'base_url' => '',
				'model'    => '',
				'key'      => false,
				'note'     => 'Export only: a working Astro site with a clean default layout. Dead shortcodes are listed in the report.',
				'help'     => '',
			),
		);
	}

	public static function defaults(): array {
		return array(
			'provider'     => 'ollama',
			'models'       => array(),
			'base_urls'    => array(),
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
		unset( $s['api_key'], $s['base_url'], $s['model'] ); // 0.1 fields, migrated in get().
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

		$p['id']       = isset( $all[ $id ] ) ? $id : 'none';
		$p['base_url'] = rtrim( trim( (string) ( $s['base_urls'][ $id ] ?? '' ) ) ?: $p['base_url'], '/' );
		$p['model']    = trim( (string) ( $s['models'][ $id ] ?? '' ) ) ?: $p['model'];
		$p['api_key']  = (string) ( $s['keys'][ $id ] ?? '' );
		return $p;
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
		return ! $p['key'] || '' !== $p['api_key'] || 'custom' === $p['id'];
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
		$out['base_urls'] = $old['base_urls'];
		foreach ( (array) ( $in['base_urls'] ?? array() ) as $id => $url ) {
			if ( isset( $providers[ $id ] ) ) {
				$out['base_urls'][ $id ] = esc_url_raw( trim( wp_unslash( $url ) ) );
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
		return apply_filters(
			'wpta_brand',
			array(
				'name'    => defined( 'WP2ASTRO_BRAND_NAME' ) ? WP2ASTRO_BRAND_NAME : 'WP2Astro',
				'by'      => defined( 'WP2ASTRO_BRAND_BY' ) ? WP2ASTRO_BRAND_BY : 'Hackers Hostel',
				'url'     => defined( 'WP2ASTRO_BRAND_URL' ) ? WP2ASTRO_BRAND_URL : 'https://github.com/HackersHostel/wp2astro',
				'docs'    => defined( 'WP2ASTRO_BRAND_DOCS' ) ? WP2ASTRO_BRAND_DOCS : 'https://github.com/HackersHostel/wp2astro/tree/main/docs',
				'support' => defined( 'WP2ASTRO_BRAND_SUPPORT' ) ? WP2ASTRO_BRAND_SUPPORT : 'https://github.com/HackersHostel/wp2astro/issues',
				'logo'    => defined( 'WP2ASTRO_BRAND_LOGO' ) ? WP2ASTRO_BRAND_LOGO : WPTA_URL . 'assets/logo.svg',
			)
		);
	}
}
