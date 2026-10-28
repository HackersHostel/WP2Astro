<?php
defined( 'ABSPATH' ) || exit;

/**
 * Minimal OpenAI-compatible chat client.
 * Works with Ollama (/v1), OpenRouter, DeepSeek and any other /chat/completions endpoint.
 */
class WPTA_LLM {

	private string $base_url;
	private string $api_key;
	private string $model;

	public function __construct( string $base_url, string $api_key, string $model ) {
		$this->base_url = rtrim( $base_url, '/' );
		$this->api_key  = $api_key;
		$this->model    = $model;
	}

	public static function from_settings(): ?self {
		if ( ! WPTA_Settings::ai_enabled() ) {
			return null;
		}
		$s = WPTA_Settings::get();
		return new self( $s['base_url'], $s['api_key'], $s['model'] );
	}

	public function model(): string {
		return $this->model;
	}

	/**
	 * @param string $system System prompt.
	 * @param string $user   User prompt.
	 * @param bool   $loose_fence Also extract a fenced block surrounded by chatter (off for Markdown documents).
	 * @return string|WP_Error Model text with code fences / think blocks removed.
	 */
	public function chat( string $system, string $user, bool $loose_fence = true ) {
		$headers = array( 'Content-Type' => 'application/json' );
		if ( '' !== $this->api_key ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}
		if ( false !== strpos( $this->base_url, 'openrouter.ai' ) ) {
			$headers['HTTP-Referer'] = home_url( '/' );
			$headers['X-Title']      = 'WP to Astro';
		}

		$res = wp_remote_post(
			$this->base_url . '/chat/completions',
			array(
				'timeout' => (int) apply_filters( 'wpta_llm_timeout', 300 ),
				'headers' => $headers,
				'body'    => wp_json_encode(
					array(
						'model'       => $this->model,
						'temperature' => 0.2,
						'stream'      => false,
						'messages'    => array(
							array( 'role' => 'system', 'content' => $system ),
							array( 'role' => 'user', 'content' => $user ),
						),
					)
				),
			)
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = $body['error']['message'] ?? ( is_string( $body['error'] ?? null ) ? $body['error'] : 'HTTP ' . $code );
			return new WP_Error( 'wpta_llm_http', 'Model API error: ' . $msg );
		}
		$text = $body['choices'][0]['message']['content'] ?? null;
		if ( ! is_string( $text ) || '' === trim( $text ) ) {
			return new WP_Error( 'wpta_llm_empty', 'Model returned an empty response.' );
		}
		return self::clean( $text, $loose_fence );
	}

	/** Remove reasoning blocks and a wrapping code fence. */
	public static function clean( string $text, bool $loose_fence = true ): string {
		$text = preg_replace( '#<think>.*?</think>#s', '', $text );
		$text = trim( $text );
		if ( preg_match( '/^```[a-zA-Z0-9_-]*\s*\n(.*?)\n```\s*$/s', $text, $m ) ) {
			$text = $m[1];
		} elseif ( $loose_fence && preg_match( '/```[a-zA-Z0-9_-]*\s*\n(.*?)\n```/s', $text, $m ) ) {
			// Model added chatter around a fenced block: keep the block.
			$text = $m[1];
		}
		return trim( $text ) . "\n";
	}
}
