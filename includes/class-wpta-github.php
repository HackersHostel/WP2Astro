<?php
defined( 'ABSPATH' ) || exit;

/**
 * GitHub: create the repo and push all files as one commit through the Git Data API.
 * Plain HTTP via wp_remote_request — no git binary needed on the server.
 */
class WPTA_GitHub {

	private string $token;
	private string $api;

	public function __construct( string $token ) {
		$this->token = $token;
		$this->api   = rtrim( (string) apply_filters( 'wpta_github_api', 'https://api.github.com' ), '/' );
	}

	/** @return array|WP_Error Decoded JSON. */
	public function request( string $method, string $path, ?array $body = null, array $ok = array( 200, 201 ) ) {
		$args = array(
			'method'  => $method,
			'timeout' => 60,
			'headers' => array(
				'Authorization'        => 'Bearer ' . $this->token,
				'Accept'               => 'application/vnd.github+json',
				'X-GitHub-Api-Version' => '2022-11-28',
				'User-Agent'           => 'wp-to-astro',
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}
		$res = wp_remote_request( $this->api . $path, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! in_array( $code, $ok, true ) ) {
			$msg = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : 'HTTP ' . $code;
			return new WP_Error( 'wpta_github_' . $code, 'GitHub: ' . $msg, array( 'status' => $code ) );
		}
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Find or create the repo.
	 *
	 * @return array{owner:string, repo:string, branch:string, url:string}|WP_Error
	 */
	public function ensure_repo( string $name, bool $private, string $description ) {
		$user = $this->request( 'GET', '/user' );
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		$owner = $user['login'];
		$repo  = $this->request( 'GET', "/repos/{$owner}/{$name}", null, array( 200, 404 ) );
		if ( is_wp_error( $repo ) ) {
			return $repo;
		}
		if ( empty( $repo['full_name'] ) ) {
			$repo = $this->request(
				'POST',
				'/user/repos',
				array(
					'name'        => $name,
					'private'     => $private,
					'description' => $description,
					'auto_init'   => true, // The Git Data API needs at least one commit.
				)
			);
			if ( is_wp_error( $repo ) ) {
				return $repo;
			}
		}
		return array(
			'owner'  => $owner,
			'repo'   => $repo['name'],
			'branch' => $repo['default_branch'] ?? 'main',
			'url'    => $repo['html_url'],
		);
	}

	/** @return string|WP_Error Blob SHA. */
	public function blob( array $r, string $contents ) {
		$res = $this->request( 'POST', "/repos/{$r['owner']}/{$r['repo']}/git/blobs", array( 'content' => base64_encode( $contents ), 'encoding' => 'base64' ) );
		return is_wp_error( $res ) ? $res : $res['sha'];
	}

	/**
	 * Commit a full tree (path => blob sha) on top of the branch head.
	 *
	 * @return string|WP_Error Commit URL.
	 */
	public function commit( array $r, array $blobs, string $message ) {
		$base = "/repos/{$r['owner']}/{$r['repo']}";
		$ref  = $this->request( 'GET', "{$base}/git/ref/heads/{$r['branch']}", null, array( 200, 404, 409 ) );
		if ( is_wp_error( $ref ) ) {
			return $ref;
		}
		$parent = $ref['object']['sha'] ?? null;

		$tree = array();
		foreach ( $blobs as $path => $sha ) {
			$tree[] = array( 'path' => $path, 'mode' => '100644', 'type' => 'blob', 'sha' => $sha );
		}
		$t = $this->request( 'POST', "{$base}/git/trees", array( 'tree' => $tree ) );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$c = $this->request(
			'POST',
			"{$base}/git/commits",
			array_filter(
				array(
					'message' => $message,
					'tree'    => $t['sha'],
					'parents' => $parent ? array( $parent ) : null,
				)
			)
		);
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		$u = $parent
			? $this->request( 'PATCH', "{$base}/git/refs/heads/{$r['branch']}", array( 'sha' => $c['sha'], 'force' => true ) )
			: $this->request( 'POST', "{$base}/git/refs", array( 'ref' => 'refs/heads/' . $r['branch'], 'sha' => $c['sha'] ) );
		if ( is_wp_error( $u ) ) {
			return $u;
		}
		return $r['url'] . '/commit/' . $c['sha'];
	}
}
