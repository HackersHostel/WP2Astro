<?php
defined( 'ABSPATH' ) || exit;

/**
 * The WP2Astro dashboard: connect a model and GitHub, convert, sync, and see past runs.
 */
class WPTA_Admin {

	const NOTICES = array(
		'saved'        => array( 'success', 'Settings saved.' ),
		'or_connected' => array( 'success', 'OpenRouter is connected. Pick a model and save.' ),
		'or_failed'    => array( 'error', 'OpenRouter didn’t finish connecting. Try again, or paste an API key instead.' ),
		'gh_bad'       => array( 'error', 'GitHub didn’t accept that token, so it wasn’t saved. Check its permissions (Administration and Contents: read & write) and expiry.' ),
	);

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_wpta_save', array( __CLASS__, 'save' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WPTA_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function url( string $hash = '' ): string {
		return admin_url( 'admin.php?page=' . WPTA_Settings::SLUG ) . ( $hash ? '#' . $hash : '' );
	}

	public static function menu(): void {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M10 1.5 2.5 18.5h3.2l1.6-3.8h5.4l1.6 3.8h3.2L10 1.5Zm0 6.2 1.8 4.4H8.2L10 7.7Z"/></svg>' );
		$name = WPTA_Settings::brand()['name'];
		add_menu_page( $name, $name, 'manage_options', WPTA_Settings::SLUG, array( __CLASS__, 'render' ), $icon, 81 );
	}

	public static function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">Open</a>' );
		return $links;
	}

	public static function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'wpta_save' );
		$changes = WPTA_Settings::from_form( (array) ( $_POST['wpta'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized field by field in from_form().
		$notice  = 'saved';
		if ( isset( $changes['github_token'] ) ) {
			// New token: check it now and remember whose it is.
			$user = ( new WPTA_GitHub( $changes['github_token'] ) )->request( 'GET', '/user' );
			if ( is_wp_error( $user ) ) {
				unset( $changes['github_token'], $changes['github_auth'], $changes['github_login'] );
				$notice = 'gh_bad';
			} else {
				$changes['github_login'] = (string) $user['login'];
			}
		}
		WPTA_Settings::update( $changes );
		$hash = sanitize_key( wp_unslash( $_POST['wpta_section'] ?? '' ) );
		wp_safe_redirect( add_query_arg( 'wpta_notice', $notice, admin_url( 'admin.php?page=' . WPTA_Settings::SLUG ) ) . ( $hash ? '#' . $hash : '' ) );
		exit;
	}

	public static function assets( string $hook ): void {
		if ( 'toplevel_page_' . WPTA_Settings::SLUG !== $hook ) {
			return;
		}
		$s = WPTA_Settings::get();
		wp_enqueue_style( 'wpta-admin', WPTA_URL . 'assets/admin.css', array(), WPTA_VERSION );
		wp_enqueue_script( 'wpta-admin', WPTA_URL . 'assets/admin.js', array(), WPTA_VERSION, true );
		wp_localize_script(
			'wpta-admin',
			'WPTA',
			array(
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'wpta' ),
				'providers' => WPTA_Settings::providers(),
				'provider'  => $s['provider'],
				'running'   => WPTA_Sync::running(),
			)
		);
	}

	/* ------------------------------------------------------------- Render */

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = WPTA_Settings::get();
		$brand = WPTA_Settings::brand();
		$ai    = WPTA_LLM::from_settings();
		$gh    = '' !== $s['github_token'];
		$cg    = WPTA_ChatGPT::status();
		$synced = $gh && $s['github_login'] && WPTA_Sync::manifest( $s['github_login'] . '/' . $s['repo_name'] );
		$notice = self::NOTICES[ sanitize_key( wp_unslash( $_GET['wpta_notice'] ?? '' ) ) ] ?? null; // phpcs:ignore WordPress.Security.NonceVerification -- display only.
		?>
		<div class="wrap wpta">
			<header class="wpta-head">
				<img src="<?php echo esc_url( $brand['logo'] ); ?>" alt="" width="40" height="40">
				<div>
					<h1><?php echo esc_html( $brand['name'] ); ?></h1>
					<p>WordPress in, fast Astro site out — same URLs, same look, synced to GitHub.</p>
				</div>
				<ul class="wpta-pills">
					<li class="<?php echo $ai ? 'ok' : 'todo'; ?>"><a href="#ai">AI: <?php echo esc_html( $ai ? $ai->label() : ( 'none' === $s['provider'] ? 'off' : 'not set up' ) ); ?></a></li>
					<li class="<?php echo $gh ? 'ok' : 'todo'; ?>"><a href="#github">GitHub: <?php echo esc_html( $gh ? ( $s['github_login'] ?: 'connected' ) : 'not connected' ); ?></a></li>
					<li class="<?php echo $s['auto_sync'] && $gh ? 'ok' : 'off'; ?>"><a href="#output">Auto-sync: <?php echo $s['auto_sync'] && $gh ? 'on' : 'off'; ?></a></li>
				</ul>
			</header>
			<hr class="wp-header-end">

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice[0] ); ?> is-dismissible"><p><?php echo esc_html( $notice[1] ); ?></p></div>
			<?php endif; ?>

			<div class="wpta-grid">
				<form class="wpta-col" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wpta-settings">
					<input type="hidden" name="action" value="wpta_save">
					<input type="hidden" name="wpta_section" value="" id="wpta-section">
					<?php wp_nonce_field( 'wpta_save' ); ?>
					<?php self::card_ai( $s, $cg ); ?>
					<?php self::card_github( $s ); ?>
					<?php self::card_output( $s, $gh ); ?>
				</form>
				<div class="wpta-col">
					<?php self::card_convert( $s, $ai, $gh, (bool) $synced ); ?>
					<?php self::card_history(); ?>
					<?php self::card_next( $s ); ?>
				</div>
			</div>

			<footer class="wpta-foot">
				<?php echo esc_html( $brand['name'] . ' ' . WPTA_VERSION ); ?>
				<?php if ( $brand['by'] ) : ?>· by <?php echo esc_html( $brand['by'] ); ?><?php endif; ?>
				· <a href="<?php echo esc_url( $brand['docs'] ); ?>" target="_blank" rel="noopener">Docs</a>
				· <a href="<?php echo esc_url( $brand['support'] ); ?>" target="_blank" rel="noopener">Support</a>
			</footer>
		</div>
		<?php
	}

	private static function save_button( string $section ): void {
		echo '<p class="wpta-save"><button type="submit" class="button button-primary" data-section="' . esc_attr( $section ) . '">Save settings</button></p>';
	}

	private static function card_ai( array $s, array $cg ): void {
		$groups = array(
			'plan'    => 'Your subscription',
			'credits' => 'Pay as you go',
			'off'     => 'Off',
		);
		$all = WPTA_Settings::providers();
		?>
		<section class="wpta-card" id="ai">
			<h2><span class="wpta-step">1</span> AI model <span class="wpta-muted">optional</span></h2>
			<p class="wpta-lead">AI rebuilds your theme as an Astro layout and cleans up dead shortcodes. Without it you still get a working site.</p>

			<div class="wpta-providers" role="radiogroup" aria-label="AI provider">
				<?php foreach ( $groups as $g => $glabel ) : ?>
					<div class="wpta-group">
						<h3><?php echo esc_html( $glabel ); ?></h3>
						<?php foreach ( $all as $id => $p ) : ?>
							<?php
							if ( $p['group'] !== $g ) {
								continue;
							}
							?>
							<label class="wpta-provider">
								<input type="radio" name="wpta[provider]" value="<?php echo esc_attr( $id ); ?>" <?php checked( $s['provider'], $id ); ?>>
								<span><strong><?php echo esc_html( $p['label'] ); ?></strong><?php echo self::provider_badge( $id, $s, $cg ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?></span>
							</label>
						<?php endforeach; ?>
						<?php if ( 'plan' === $g ) : ?>
							<a class="wpta-provider wpta-provider-info" href="#next" title="Claude subscriptions only work in Claude's own apps. The repo's agent kit lets Claude Code finish the site on your plan.">
								<span><strong>Claude plan</strong><em>via Claude Code ↓</em></span>
							</a>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<?php foreach ( $all as $id => $p ) : ?>
				<?php $r = WPTA_Settings::provider( $id ); ?>
				<div class="wpta-panel" data-provider="<?php echo esc_attr( $id ); ?>" <?php echo $s['provider'] === $id ? '' : 'hidden'; ?>>
					<p class="description"><?php echo esc_html( $p['note'] ); ?>
						<?php if ( $p['help'] ) : ?><a href="<?php echo esc_url( $p['help'] ); ?>" target="_blank" rel="noopener">Learn more ↗</a><?php endif; ?>
					</p>
					<?php
					if ( 'none' === $id ) {
						echo '</div>';
						continue;
					}
					if ( 'openrouter' === $id ) {
						self::openrouter_box( $r );
					} elseif ( 'chatgpt' === $id ) {
						self::chatgpt_box( $cg );
					}
					?>
					<table class="form-table" role="presentation">
						<?php if ( $p['key'] ) : ?>
							<tr>
								<th><label for="wpta-key-<?php echo esc_attr( $id ); ?>">API key</label></th>
								<td>
									<input id="wpta-key-<?php echo esc_attr( $id ); ?>" class="regular-text wpta-key" type="password" autocomplete="off" name="wpta[keys][<?php echo esc_attr( $id ); ?>]" value="" placeholder="<?php echo esc_attr( WPTA_Secrets::mask( $r['api_key'] ) ?: 'Set by Connect, or paste a key' ); ?>">
									<?php if ( $r['api_key'] ) : ?>
										<label class="wpta-inline"><input type="checkbox" name="wpta[clear_keys][<?php echo esc_attr( $id ); ?>]" value="1"> Remove</label>
									<?php endif; ?>
									<p class="description">Stored encrypted. Only sent to <?php echo esc_html( (string) wp_parse_url( $p['base_url'], PHP_URL_HOST ) ); ?>.</p>
								</td>
							</tr>
						<?php endif; ?>
						<tr>
							<th><label for="wpta-model-<?php echo esc_attr( $id ); ?>">Model</label></th>
							<td>
								<input id="wpta-model-<?php echo esc_attr( $id ); ?>" class="regular-text wpta-model" type="text" list="wpta-models-<?php echo esc_attr( $id ); ?>" name="wpta[models][<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $r['model'] ); ?>" placeholder="<?php echo esc_attr( $p['model'] ?: 'Load the list or type a model ID' ); ?>">
								<datalist id="wpta-models-<?php echo esc_attr( $id ); ?>"></datalist>
								<button type="button" class="button wpta-load-models">Load models</button>
								<button type="button" class="button wpta-test">Test</button>
								<p class="wpta-msg" aria-live="polite"></p>
							</td>
						</tr>
					</table>
				</div>
			<?php endforeach; ?>

			<fieldset class="wpta-checks">
				<legend>AI steps</legend>
				<label><input type="checkbox" name="wpta[ai_layout]" value="1" <?php checked( $s['ai_layout'] ); ?>> Rebuild the theme’s header, menu and footer as an Astro layout</label>
				<label><input type="checkbox" name="wpta[ai_cleanup]" value="1" <?php checked( $s['ai_cleanup'] ); ?>> Clean up dead shortcodes in posts</label>
				<p class="description">Results are cached: re-running a sync doesn’t spend credits again unless your theme or those posts change.</p>
			</fieldset>
			<?php self::save_button( 'ai' ); ?>
		</section>
		<?php
	}

	private static function provider_badge( string $id, array $s, array $cg ): string {
		$on = '';
		if ( 'chatgpt' === $id ) {
			$on = $cg['connected'] ? 'signed in' : 'Plus or Pro';
		} elseif ( 'openrouter' === $id ) {
			$on = '' !== ( $s['keys']['openrouter'] ?? '' ) ? 'connected' : 'Claude, GPT, free models';
		} elseif ( 'none' === $id ) {
			$on = 'default layout';
		}
		return $on ? '<em>' . esc_html( $on ) . '</em>' : '';
	}

	private static function openrouter_box( array $r ): void {
		?>
		<div class="wpta-connect" data-connect="openrouter">
			<?php if ( '' !== $r['api_key'] ) : ?>
				<p class="wpta-connected">✓ OpenRouter connected · <span class="wpta-balance">checking credits…</span></p>
				<p>
					<a class="button button-primary wpta-buy" href="<?php echo esc_url( WPTA_Connect::OPENROUTER_CREDITS ); ?>" target="_blank" rel="noopener">Buy credits ↗</a>
					<button type="button" class="button wpta-balance-refresh">Refresh balance</button>
					<button type="button" class="button-link wpta-or-connect">Reconnect</button>
				</p>
				<p class="description">Credits are bought on OpenRouter (card or crypto) and show up here. Models marked “free” cost nothing.</p>
			<?php else : ?>
				<p><button type="button" class="button button-primary wpta-or-connect">Connect OpenRouter</button> <span class="wpta-muted">opens openrouter.ai, then comes back here</span></p>
				<p class="description">No account yet? Create one during connect. Start with <code>openrouter/free</code>, or add a few dollars of credit for Claude — converting a small blog with Claude Haiku costs around a cent.</p>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function chatgpt_box( array $cg ): void {
		?>
		<div class="wpta-connect" data-connect="chatgpt">
			<div class="wpta-cg-on" <?php echo $cg['connected'] ? '' : 'hidden'; ?>>
				<p class="wpta-connected">✓ Signed in<span class="wpta-cg-email"><?php echo $cg['email'] ? ' as ' . esc_html( $cg['email'] ) : ''; ?></span> · uses your ChatGPT plan</p>
				<p>
					<a class="button" href="<?php echo esc_url( $cg['usage_url'] ); ?>" target="_blank" rel="noopener">Usage &amp; limits ↗</a>
					<button type="button" class="button-link wpta-cg-signout">Sign out</button>
				</p>
			</div>
			<div class="wpta-cg-off" <?php echo $cg['connected'] ? 'hidden' : ''; ?>>
				<p><button type="button" class="button button-primary wpta-cg-start">Sign in with ChatGPT</button></p>
				<div class="wpta-cg-paste" hidden>
					<ol>
						<li>Approve <?php echo esc_html( WPTA_Settings::brand()['name'] ); ?> in the ChatGPT tab that just opened.</li>
						<li>That tab then shows a page that can’t load (an address starting with <code>http://127.0.0.1:1455</code>). That’s expected.</li>
						<li>Copy the full address from that tab and paste it here:</li>
					</ol>
					<p>
						<input type="url" class="large-text wpta-cg-callback" placeholder="http://127.0.0.1:1455/auth/callback?code=…">
						<button type="button" class="button button-primary wpta-cg-finish">Finish sign-in</button>
					</p>
				</div>
				<p class="description">Requires ChatGPT Plus or Pro. Usage counts toward your plan’s limits; you can cap it per app in ChatGPT settings.</p>
			</div>
		</div>
		<?php
	}

	private static function card_github( array $s ): void {
		$connected = '' !== $s['github_token'];
		$oauth     = '' !== WPTA_Connect::github_client_id();
		?>
		<section class="wpta-card" id="github">
			<h2><span class="wpta-step">2</span> GitHub <span class="wpta-muted">optional</span></h2>
			<p class="wpta-lead">Push the Astro site to a repo, then deploy it anywhere. Without GitHub you get a zip.</p>

			<div class="wpta-connect" data-connect="github">
				<?php if ( $connected ) : ?>
					<p class="wpta-connected">✓ Connected<?php echo $s['github_login'] ? ' as <strong>@' . esc_html( $s['github_login'] ) . '</strong>' : ''; ?> <span class="wpta-muted">(<?php echo 'oauth' === $s['github_auth'] ? 'GitHub app' : 'access token'; ?>)</span></p>
					<p>
						<button type="button" class="button wpta-gh-check">Check repo</button>
						<button type="button" class="button-link wpta-gh-disconnect">Disconnect</button>
					</p>
					<p class="wpta-msg wpta-gh-status" aria-live="polite"></p>
				<?php else : ?>
					<?php if ( $oauth ) : ?>
						<p><button type="button" class="button button-primary wpta-gh-start">Connect GitHub</button></p>
						<div class="wpta-gh-device" hidden>
							<p>Enter this code on GitHub:</p>
							<p class="wpta-code"><code class="wpta-gh-code"></code> <button type="button" class="button wpta-copy">Copy</button></p>
							<p><a class="button button-primary wpta-gh-open" href="https://github.com/login/device" target="_blank" rel="noopener">Open github.com/login/device ↗</a> <span class="wpta-muted wpta-gh-wait">Waiting for approval…</span></p>
						</div>
						<p class="wpta-msg" aria-live="polite"></p>
					<?php endif; ?>
					<details <?php echo $oauth ? '' : 'open'; ?>>
						<summary><?php echo $oauth ? 'Use a personal access token instead' : 'Connect with a personal access token'; ?></summary>
						<p>
							<input type="password" class="regular-text" autocomplete="off" name="wpta[github_token]" value="" placeholder="github_pat_…">
						</p>
						<p class="description"><a href="https://github.com/settings/personal-access-tokens/new" target="_blank" rel="noopener">Create a fine-grained token ↗</a> with <strong>Administration</strong> and <strong>Contents</strong> set to read &amp; write. Stored encrypted.</p>
					</details>
				<?php endif; ?>
			</div>

			<table class="form-table" role="presentation">
				<tr>
					<th><label for="wpta-repo">Repository</label></th>
					<td>
						<input id="wpta-repo" class="regular-text" type="text" name="wpta[repo_name]" value="<?php echo esc_attr( $s['repo_name'] ); ?>">
						<label class="wpta-inline"><input type="checkbox" name="wpta[repo_private]" value="1" <?php checked( $s['repo_private'] ); ?>> Private</label>
						<p class="description">Created on the first run if it doesn’t exist.</p>
					</td>
				</tr>
			</table>
			<?php self::save_button( 'github' ); ?>
		</section>
		<?php
	}

	private static function card_output( array $s, bool $gh ): void {
		$checks = array(
			'seo'       => array( 'SEO titles and descriptions', 'From Yoast, Rank Math or All in One SEO, plus canonical and Open Graph tags.' ),
			'feeds'     => array( 'RSS feed, sitemap and robots.txt', 'Old /feed/ and /wp-sitemap.xml links redirect to the new ones.' ),
			'search'    => array( 'Site search', 'Static full-text search with Pagefind; old ?s= links land on it.' ),
			'cpts'      => array( 'Custom post types', 'Public custom post types get their own folders and archives.' ),
			'agent_kit' => array( 'Agent kit', 'AGENTS.md, CLAUDE.md and Claude Code skills so an AI agent can finish the site.' ),
		);
		?>
		<section class="wpta-card" id="output">
			<h2><span class="wpta-step">3</span> What to build</h2>
			<fieldset class="wpta-checks">
				<?php foreach ( $checks as $k => $c ) : ?>
					<label><input type="checkbox" name="wpta[<?php echo esc_attr( $k ); ?>]" value="1" <?php checked( $s[ $k ] ); ?>> <strong><?php echo esc_html( $c[0] ); ?></strong> <span class="wpta-muted"><?php echo esc_html( $c[1] ); ?></span></label>
				<?php endforeach; ?>
			</fieldset>
			<fieldset class="wpta-checks">
				<legend>Keep in sync</legend>
				<label><input type="checkbox" name="wpta[auto_sync]" value="1" <?php checked( $s['auto_sync'] ); ?> <?php disabled( ! $gh && ! $s['auto_sync'] ); ?>> <strong>Sync to GitHub automatically</strong> <span class="wpta-muted">a couple of minutes after you publish, update or delete a post. Only changed files are pushed; files edited in the repo are kept.</span></label>
				<?php if ( ! $gh ) : ?><p class="description">Connect GitHub to turn this on.</p><?php endif; ?>
			</fieldset>
			<?php self::save_button( 'output' ); ?>
		</section>
		<?php
	}

	private static function card_convert( array $s, ?WPTA_LLM $ai, bool $gh, bool $synced ): void {
		$counts = array();
		foreach ( WPTA_Exporter::post_types() as $type ) {
			$n   = (int) ( wp_count_posts( $type )->publish ?? 0 );
			$obj = get_post_type_object( $type );
			if ( $n && $obj ) {
				$counts[] = $n . ' ' . strtolower( 1 === $n ? $obj->labels->singular_name : $obj->labels->name );
			}
		}
		?>
		<section class="wpta-card wpta-run" id="convert">
			<h2><?php echo $synced ? 'Sync' : 'Convert'; ?></h2>
			<ul class="wpta-summary">
				<li><span>Content</span><strong><?php echo esc_html( $counts ? implode( ' · ', $counts ) : 'nothing published yet' ); ?></strong></li>
				<li><span>AI</span><strong><?php echo esc_html( $ai ? $ai->label() : 'none — default layout' ); ?></strong></li>
				<li><span>Output</span><strong><?php echo esc_html( $gh ? ( $s['github_login'] ? $s['github_login'] . '/' : '' ) . $s['repo_name'] . ( $synced ? ' (changed files only)' : '' ) : 'zip download' ); ?></strong></li>
			</ul>
			<p><button type="button" class="button button-primary button-hero" id="wpta-start"><?php echo $synced ? 'Sync now' : 'Convert to Astro'; ?></button></p>
			<div class="wpta-progress" hidden><div class="wpta-bar"><span></span></div><p class="wpta-phase"></p></div>
			<div id="wpta-result" hidden></div>
			<details class="wpta-logbox" hidden><summary>Log</summary><pre id="wpta-log"></pre></details>
		</section>
		<?php
	}

	private static function card_history(): void {
		?>
		<section class="wpta-card" id="history">
			<h2>Recent runs</h2>
			<table class="widefat striped wpta-runs">
				<thead><tr><th>When</th><th>How</th><th>Result</th><th></th></tr></thead>
				<tbody id="wpta-runs">
					<?php self::history_rows(); ?>
				</tbody>
			</table>
		</section>
		<?php
	}

	public static function history_rows(): void {
		$runs = WPTA_Sync::runs();
		if ( ! $runs ) {
			echo '<tr><td colspan="4" class="wpta-muted">No runs yet.</td></tr>';
			return;
		}
		$how = array( 'manual' => 'Button', 'auto' => 'Auto-sync', 'cli' => 'WP-CLI' );
		foreach ( $runs as $r ) {
			$when = isset( $r['started'] ) ? human_time_diff( (int) $r['started'] ) . ' ago' : '';
			if ( 'error' === ( $r['status'] ?? '' ) ) {
				$res = '<span class="wpta-bad">Failed</span> ' . esc_html( mb_substr( (string) ( $r['error'] ?? '' ), 0, 120 ) );
			} elseif ( 'running' === ( $r['status'] ?? '' ) ) {
				$res = '<span class="wpta-muted">Running…</span>';
			} else {
				$parts = array( (int) ( $r['entries'] ?? 0 ) . ' entries' );
				if ( null !== ( $r['changed'] ?? null ) ) {
					$parts[] = (int) $r['changed'] . ' changed';
					if ( ! empty( $r['deleted'] ) ) {
						$parts[] = (int) $r['deleted'] . ' removed';
					}
					if ( ! empty( $r['kept'] ) ) {
						$parts[] = count( (array) $r['kept'] ) . ' kept';
					}
				}
				$res = '<span class="wpta-good">Done</span> ' . esc_html( implode( ', ', $parts ) );
			}
			$links = '';
			if ( ! empty( $r['commit'] ) ) {
				$links .= '<a href="' . esc_url( $r['commit'] ) . '" target="_blank" rel="noopener">Commit ↗</a> ';
			}
			if ( ! empty( $r['zip'] ) && is_readable( WPTA_Job::root() . '/' . $r['id'] . '/site.zip' ) ) {
				$links .= '<a href="' . esc_url( $r['zip'] ) . '">Zip</a>';
			}
			printf(
				'<tr><td title="%s">%s</td><td>%s</td><td>%s</td><td class="wpta-links">%s</td></tr>',
				esc_attr( wp_date( 'Y-m-d H:i', (int) ( $r['started'] ?? 0 ) ) ),
				esc_html( $when ),
				esc_html( $how[ $r['trigger'] ?? '' ] ?? '' ),
				$res, // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
				$links // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
			);
		}
	}

	private static function card_next( array $s ): void {
		?>
		<section class="wpta-card" id="next">
			<h2>Finish and deploy</h2>
			<h3>Finish it with your Claude or ChatGPT subscription</h3>
			<p>The repo ships an agent kit. Open it in <strong>Claude Code</strong> and run <code>/wp2astro-polish-layout</code> or <code>/wp2astro-fix-shortcodes</code> — it runs on your Claude plan. Codex and other agents read <code>AGENTS.md</code>.</p>
			<p>Agents can also drive this plugin from the command line:</p>
			<pre class="wpta-cmd">wp wp2astro convert
wp wp2astro status</pre>
			<h3>Deploy</h3>
			<ol>
				<li><strong>FlyWP + DigitalOcean:</strong> add a Node.js site from the repo. Build <code>npm install &amp;&amp; npm run build</code>, start <code>npm start</code> (serves the WordPress redirects too). FlyWP’s free plan covers one server and one site; the droplet is billed by DigitalOcean.</li>
				<li><strong>Any static host:</strong> build <code>npm run build</code>, publish <code>dist/</code>. Netlify and Cloudflare Pages read <code>public/_redirects</code>.</li>
			</ol>
		</section>
		<?php
	}
}
