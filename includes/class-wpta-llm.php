<?php
defined( 'ABSPATH' ) || exit;

/**
 * One chat() call, two wire formats:
 * - openai:  POST {base}/chat/completions (OpenRouter, or any OpenAI-compatible gateway via the wpta_provider filter)
 * - chatgpt: POST {base}/responses with a Sign in with ChatGPT token (the user's ChatGPT plan)
 */
class WPTA_LLM {

	private string $kind;
	private string $base_url;
	private string $api_key;
	private string $model;
	private string $label;

	public function __construct( string $kind, string $base_url, string $api_key, string $model, string $label = '' ) {
		$this->kind     = $kind;
		$this->base_url = rtrim( $base_url, '/' );
		$this->api_key  = $api_key;
		$this->model    = $model;
		$this->label    = $label;
	}

	public static function from_settings(): ?self {
		return WPTA_Settings::ai_enabled() ? self::for_provider( WPTA_Settings::provider() ) : null;
	}

	public static function for_provider( array $p ): self {
		return new self( $p['kind'], $p['base_url'], $p['api_key'], $p['model'], $p['label'] );
	}

	public function model(): string {
		return $this->model;
	}

	/** "anthropic/claude-haiku-5.5 (OpenRouter)" */
	public function label(): string {
		return $this->label ? $this->model . ' (' . $this->label . ')' : $this->model;
	}

	private static function timeout(): int {
		return (int) apply_filters( 'wpta_llm_timeout', 300 );
	}

	/**
	 * @param string $system System prompt.
	 * @param string $user   User prompt.
	 * @param bool   $loose_fence Also extract a fenced block surrounded by chatter (off for Markdown documents).
	 * @return string|WP_Error Model text with code fences / think blocks removed.
	 */
	public function chat( string $system, string $user, bool $loose_fence = true ) {
		$text = 'chatgpt' === $this->kind ? $this->responses( $system, $user ) : $this->completions( $system, $user );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		if ( '' === trim( $text ) ) {
			return new WP_Error( 'wpta_llm_empty', 'Model returned an empty response.' );
		}
		return self::clean( $text, $loose_fence );
	}

	private function headers(): array {
		$headers = array( 'Content-Type' => 'application/json' );
		if ( '' !== $this->api_key ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}
		if ( false !== strpos( $this->base_url, 'openrouter.ai' ) ) {
			$headers['HTTP-Referer'] = home_url( '/' );
			$headers['X-Title']      = WPTA_Settings::brand()['name'];
		}
		return $headers;
	}

	/** @return string|WP_Error */
	private function completions( string $system, string $user ) {
		$res = wp_remote_post(
			$this->base_url . '/chat/completions',
			array(
				'timeout' => self::timeout(),
				'headers' => $this->headers(),
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
		$body = self::decode( $res );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$text = $body['choices'][0]['message']['content'] ?? null;
		return is_string( $text ) ? $text : '';
	}

	/**
	 * ChatGPT plan usage: Responses API, store=false, stream=true (required in this flow).
	 * The body is read whole and parsed as server-sent events; only response.completed counts as success.
	 *
	 * @return string|WP_Error
	 */
	private function responses( string $system, string $user ) {
		$token = WPTA_ChatGPT::access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$res = wp_remote_post(
			$this->base_url . '/responses',
			array(
				'timeout' => self::timeout(),
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Accept'        => 'text/event-stream',
					'Authorization' => 'Bearer ' . $token,
				),
				'body'    => wp_json_encode(
					array(
						'model'        => $this->model,
						'instructions' => $system,
						'input'        => array( array( 'role' => 'user', 'content' => $user ) ),
						'store'        => false,
						'stream'       => true,
					)
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = (string) wp_remote_retrieve_body( $res );
		if ( $code < 200 || $code >= 300 ) {
			$body = json_decode( $raw, true );
			return WPTA_ChatGPT::error( (string) ( $body['error']['code'] ?? '' ), (string) ( $body['error']['message'] ?? $body['detail'] ?? 'HTTP ' . $code ), $code );
		}

		$text      = '';
		$completed = false;
		foreach ( preg_split( '/\r?\n/', $raw ) as $line ) {
			if ( 0 !== strpos( $line, 'data:' ) ) {
				continue;
			}
			$event = json_decode( trim( substr( $line, 5 ) ), true );
			$type  = is_array( $event ) ? (string) ( $event['type'] ?? '' ) : '';
			if ( 'response.output_text.delta' === $type ) {
				$text .= (string) ( $event['delta'] ?? '' );
			} elseif ( 'response.completed' === $type ) {
				$completed = true;
				if ( '' === $text ) {
					$text = self::response_text( (array) ( $event['response'] ?? array() ) );
				}
			} elseif ( in_array( $type, array( 'response.failed', 'error' ), true ) ) {
				$err = $event['response']['error'] ?? $event['error'] ?? $event;
				return WPTA_ChatGPT::error( (string) ( $err['code'] ?? '' ), (string) ( $err['message'] ?? 'Request failed.' ), 0 );
			} elseif ( 'response.incomplete' === $type ) {
				$why = $event['response']['incomplete_details']['reason'] ?? 'unknown';
				return new WP_Error( 'wpta_chatgpt_incomplete', 'ChatGPT stopped before finishing (' . $why . ').' );
			}
		}
		if ( ! $completed ) {
			return new WP_Error( 'wpta_chatgpt_stream', 'ChatGPT stream ended without a completed response.' );
		}
		return $text;
	}

	private static function response_text( array $response ): string {
		$text = '';
		foreach ( (array) ( $response['output'] ?? array() ) as $item ) {
			foreach ( (array) ( $item['content'] ?? array() ) as $part ) {
				if ( 'output_text' === ( $part['type'] ?? '' ) ) {
					$text .= $part['text'];
				}
			}
		}
		return $text;
	}

	/** @return array|WP_Error Decoded JSON body of a 2xx response. */
	private static function decode( $res ) {
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = $body['error']['message'] ?? ( is_string( $body['error'] ?? null ) ? $body['error'] : ( $body['detail'] ?? 'HTTP ' . $code ) );
			return new WP_Error( 'wpta_llm_http', 'Model API error: ' . $msg, array( 'status' => $code ) );
		}
		return is_array( $body ) ? $body : array();
	}

	/**
	 * Models the user can pick for a provider.
	 *
	 * @return array<int,array{id:string,label:string,free?:bool}>|WP_Error
	 */
	public static function list_models( array $p ) {
		$headers = array();
		$url     = $p['base_url'] . '/models';
		if ( 'chatgpt' === $p['kind'] ) {
			$token = WPTA_ChatGPT::access_token();
			if ( is_wp_error( $token ) ) {
				return $token;
			}
			$headers = array( 'Authorization' => 'Bearer ' . $token );
		} elseif ( '' !== $p['api_key'] ) {
			$headers = array( 'Authorization' => 'Bearer ' . $p['api_key'] );
		}
		$body = self::decode( wp_remote_get( $url, array( 'timeout' => 20, 'headers' => $headers ) ) );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$out = array();
		if ( 'chatgpt' === $p['kind'] ) {
			foreach ( (array) ( $body['models'] ?? array() ) as $m ) {
				if ( 'list' === ( $m['visibility'] ?? 'list' ) && ! empty( $m['slug'] ) ) {
					$out[] = array( 'id' => $m['slug'], 'label' => $m['display_name'] ?? $m['slug'] );
				}
			}
			return $out;
		}
		foreach ( (array) ( $body['data'] ?? array() ) as $m ) {
			if ( empty( $m['id'] ) ) {
				continue;
			}
			$row = array( 'id' => $m['id'], 'label' => $m['display_name'] ?? $m['name'] ?? $m['id'] );
			if ( isset( $m['pricing']['prompt'] ) ) {
				$row['free'] = 0.0 === (float) $m['pricing']['prompt'] && 0.0 === (float) ( $m['pricing']['completion'] ?? 0 );
			}
			$out[] = $row;
		}
		if ( 'openrouter' === $p['id'] ) {
			// Free models first, then alphabetical — the list is long.
			usort( $out, fn( $a, $b ) => array( empty( $a['free'] ), $a['id'] ) <=> array( empty( $b['free'] ), $b['id'] ) );
		}
		return $out;
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
