<?php
defined( 'ABSPATH' ) || exit;

/**
 * Settings storage + the Tools → WP to Astro admin page.
 */
class WPTA_Settings {

	const OPTION = 'wpta_settings';
	const SLUG   = 'wp-to-astro';

	/** Provider presets: base URL + example model. */
	public static function presets(): array {
		return array(
			'ollama'     => array(
				'label'    => 'Ollama (local) — Gemma',
				'base_url' => 'http://localhost:11434/v1',
				'model'    => 'gemma3',
			),
			'openrouter' => array(
				'label'    => 'OpenRouter',
				'base_url' => 'https://openrouter.ai/api/v1',
				'model'    => 'google/gemma-3-27b-it',
			),
			'deepseek'   => array(
				'label'    => 'DeepSeek',
				'base_url' => 'https://api.deepseek.com',
				'model'    => 'deepseek-chat',
			),
			'custom'     => array(
				'label'    => 'Custom (OpenAI-compatible)',
				'base_url' => '',
				'model'    => '',
			),
			'none'       => array(
				'label'    => 'No AI (export only)',
				'base_url' => '',
				'model'    => '',
			),
		);
	}

	public static function defaults(): array {
		$p = self::presets()['ollama'];
		return array(
			'provider'     => 'ollama',
			'base_url'     => $p['base_url'],
			'api_key'      => '',
			'model'        => $p['model'],
			'ai_layout'    => 1,
			'ai_cleanup'   => 1,
			'github_token' => '',
			'repo_name'    => sanitize_title( get_bloginfo( 'name' ) ?: 'my-site' ) . '-astro',
			'repo_private' => 1,
		);
	}

	public static function get(): array {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public static function ai_enabled(): bool {
		$s = self::get();
		return 'none' !== $s['provider'] && '' !== trim( $s['base_url'] ) && '' !== trim( $s['model'] );
	}

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu(): void {
		add_management_page( 'WP to Astro', 'WP to Astro', 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function register(): void {
		register_setting(
			'wpta',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
		// Secrets live here, so create the option with autoload off.
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		}
	}

	public static function sanitize( $in ): array {
		$old = self::get();
		$in  = is_array( $in ) ? $in : array();
		$out = array();

		$out['provider'] = array_key_exists( $in['provider'] ?? '', self::presets() ) ? $in['provider'] : 'ollama';
		$out['base_url'] = esc_url_raw( trim( $in['base_url'] ?? '' ) );
		$out['model']    = sanitize_text_field( $in['model'] ?? '' );

		// Blank secret field = keep the stored value.
		$key            = trim( (string) ( $in['api_key'] ?? '' ) );
		$out['api_key'] = '' === $key ? $old['api_key'] : sanitize_text_field( $key );
		if ( ! empty( $in['clear_api_key'] ) ) {
			$out['api_key'] = '';
		}
		$tok                 = trim( (string) ( $in['github_token'] ?? '' ) );
		$out['github_token'] = '' === $tok ? $old['github_token'] : sanitize_text_field( $tok );
		if ( ! empty( $in['clear_github_token'] ) ) {
			$out['github_token'] = '';
		}

		$out['ai_layout']    = empty( $in['ai_layout'] ) ? 0 : 1;
		$out['ai_cleanup']   = empty( $in['ai_cleanup'] ) ? 0 : 1;
		$out['repo_name']    = preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) ( $in['repo_name'] ?? '' ) ) ?: self::defaults()['repo_name'];
		$out['repo_private'] = empty( $in['repo_private'] ) ? 0 : 1;
		return $out;
	}

	public static function assets( string $hook ): void {
		if ( 'tools_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wpta-admin', WPTA_URL . 'assets/admin.css', array(), WPTA_VERSION );
		wp_enqueue_script( 'wpta-admin', WPTA_URL . 'assets/admin.js', array(), WPTA_VERSION, true );
		wp_localize_script(
			'wpta-admin',
			'WPTA',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpta' ),
				'presets' => self::presets(),
			)
		);
	}

	private static function mask( string $secret ): string {
		return '' === $secret ? '' : str_repeat( '•', 8 ) . substr( $secret, -4 );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s   = self::get();
		$opt = self::OPTION;
		?>
		<div class="wrap wpta">
			<h1>WP to Astro</h1>
			<p>Convert this WordPress site into an Astro project. Posts and pages keep their URLs; an AI model rebuilds the theme layout and cleans up dead shortcodes.</p>

			<div class="wpta-grid">
				<div class="wpta-card">
					<h2>1. Settings</h2>
					<form method="post" action="options.php">
						<?php settings_fields( 'wpta' ); ?>
						<h3>Model</h3>
						<table class="form-table" role="presentation">
							<tr>
								<th><label for="wpta-provider">Provider</label></th>
								<td>
									<select id="wpta-provider" name="<?php echo esc_attr( $opt ); ?>[provider]">
										<?php foreach ( self::presets() as $id => $p ) : ?>
											<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $s['provider'], $id ); ?>><?php echo esc_html( $p['label'] ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description">Any OpenAI-compatible <code>/chat/completions</code> endpoint works.</p>
								</td>
							</tr>
							<tr class="wpta-ai-row">
								<th><label for="wpta-base">Base URL</label></th>
								<td><input id="wpta-base" class="regular-text" type="url" name="<?php echo esc_attr( $opt ); ?>[base_url]" value="<?php echo esc_attr( $s['base_url'] ); ?>"></td>
							</tr>
							<tr class="wpta-ai-row">
								<th><label for="wpta-model">Model</label></th>
								<td><input id="wpta-model" class="regular-text" type="text" name="<?php echo esc_attr( $opt ); ?>[model]" value="<?php echo esc_attr( $s['model'] ); ?>"></td>
							</tr>
							<tr class="wpta-ai-row">
								<th><label for="wpta-key">API key</label></th>
								<td>
									<input id="wpta-key" class="regular-text" type="password" autocomplete="off" name="<?php echo esc_attr( $opt ); ?>[api_key]" value="" placeholder="<?php echo esc_attr( self::mask( $s['api_key'] ) ?: 'Not needed for local Ollama' ); ?>">
									<?php if ( $s['api_key'] ) : ?>
										<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[clear_api_key]" value="1"> Clear</label>
									<?php endif; ?>
								</td>
							</tr>
							<tr class="wpta-ai-row">
								<th>AI steps</th>
								<td>
									<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[ai_layout]" value="1" <?php checked( $s['ai_layout'] ); ?>> Rebuild the theme layout as an Astro component</label><br>
									<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[ai_cleanup]" value="1" <?php checked( $s['ai_cleanup'] ); ?>> Clean up dead shortcodes in posts</label>
								</td>
							</tr>
						</table>

						<h3>GitHub <span class="wpta-muted">(optional)</span></h3>
						<table class="form-table" role="presentation">
							<tr>
								<th><label for="wpta-token">Access token</label></th>
								<td>
									<input id="wpta-token" class="regular-text" type="password" autocomplete="off" name="<?php echo esc_attr( $opt ); ?>[github_token]" value="" placeholder="<?php echo esc_attr( self::mask( $s['github_token'] ) ?: 'github_pat_…' ); ?>">
									<?php if ( $s['github_token'] ) : ?>
										<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[clear_github_token]" value="1"> Clear</label>
									<?php endif; ?>
									<p class="description">Fine-grained token with <strong>Administration</strong> and <strong>Contents</strong> set to read &amp; write. Leave empty to only download a zip.</p>
								</td>
							</tr>
							<tr>
								<th><label for="wpta-repo">Repository name</label></th>
								<td>
									<input id="wpta-repo" class="regular-text" type="text" name="<?php echo esc_attr( $opt ); ?>[repo_name]" value="<?php echo esc_attr( $s['repo_name'] ); ?>">
									<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[repo_private]" value="1" <?php checked( $s['repo_private'] ); ?>> Private</label>
								</td>
							</tr>
						</table>
						<?php submit_button( 'Save settings' ); ?>
					</form>
				</div>

				<div class="wpta-card">
					<h2>2. Convert</h2>
					<?php
					$counts = wp_count_posts( 'post' );
					$pages  = wp_count_posts( 'page' );
					?>
					<p><?php echo esc_html( sprintf( '%d published posts and %d pages will be converted.', (int) $counts->publish, (int) $pages->publish ) ); ?></p>
					<p>
						Model: <strong><?php echo esc_html( self::ai_enabled() ? $s['model'] : 'none (export only)' ); ?></strong><br>
						GitHub: <strong><?php echo esc_html( $s['github_token'] ? $s['repo_name'] : 'not connected (zip only)' ); ?></strong>
					</p>
					<p><button type="button" class="button button-primary button-hero" id="wpta-start">Convert to Astro</button></p>
					<div id="wpta-result" hidden></div>
					<pre id="wpta-log" hidden></pre>

					<h3>Where to host it</h3>
					<p>Deploy the repo with <a href="https://flywp.com/features/nodejs-deployments/" target="_blank" rel="noopener">FlyWP Node.js deployments</a> on a DigitalOcean droplet. FlyWP's free plan covers one server and one site; the droplet is billed by DigitalOcean. Build command <code>npm run build</code>, start command <code>npm start</code>.</p>
				</div>
			</div>
		</div>
		<?php
	}
}
