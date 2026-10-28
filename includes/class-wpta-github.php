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

		/**
		 * Filters the base URL of the GitHub REST API.
		 *
		 * Point it at GitHub Enterprise Server (https://github.example.com/api/v3) or a test server.
		 *
		 * @since 0.2.0
		 *
		 * @param string $api API base URL. Default 'https://api.github.com'.
		 */
		$this->api = rtrim( (string) apply_filters( 'wpta_github_api', 'https://api.github.com' ), '/' );
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
				'User-Agent'           => 'wp2astro',
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
		$repo  = $this->request( 'GET', '/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $name ), null, array( 200, 404 ) );
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

	/** Git's blob id for these bytes, so unchanged files can be skipped without asking GitHub. */
	public static function blob_sha( string $contents ): string {
		return sha1( 'blob ' . strlen( $contents ) . "\0" . $contents );
	}

	/** @return string|WP_Error Blob SHA. */
	public function blob( array $r, string $contents ) {
		$res = $this->request( 'POST', "/repos/{$r['owner']}/{$r['repo']}/git/blobs", array( 'content' => base64_encode( $contents ), 'encoding' => 'base64' ) );
		return is_wp_error( $res ) ? $res : $res['sha'];
	}

	/**
	 * Files on the branch head.
	 *
	 * @return array{head:?string, files:array<string,string>, complete:bool}|WP_Error path => blob sha
	 */
	public function remote_files( array $r ) {
		$base = "/repos/{$r['owner']}/{$r['repo']}";
		$ref  = $this->request( 'GET', "{$base}/git/ref/heads/{$r['branch']}", null, array( 200, 404, 409 ) );
		if ( is_wp_error( $ref ) ) {
			return $ref;
		}
		$head = $ref['object']['sha'] ?? null;
		if ( ! $head ) {
			return array( 'head' => null, 'files' => array(), 'complete' => true );
		}
		$commit = $this->request( 'GET', "{$base}/git/commits/{$head}" );
		if ( is_wp_error( $commit ) ) {
			return $commit;
		}
		$tree = $this->request( 'GET', "{$base}/git/trees/{$commit['tree']['sha']}?recursive=1" );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		$files = array();
		foreach ( (array) ( $tree['tree'] ?? array() ) as $e ) {
			if ( 'blob' === ( $e['type'] ?? '' ) ) {
				$files[ $e['path'] ] = $e['sha'];
			}
		}
		return array( 'head' => $head, 'files' => $files, 'complete' => empty( $tree['truncated'] ) );
	}

	/**
	 * Commit a full tree (path => blob sha) on top of $parent. Paths left out are deleted.
	 *
	 * @return array{sha:string, url:string}|WP_Error
	 */
	public function commit( array $r, array $blobs, string $message, ?string $parent ) {
		$base = "/repos/{$r['owner']}/{$r['repo']}";

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
		// Not forced: if someone pushed since we read the branch, GitHub refuses and the next run picks their commit up.
		$u = $parent
			? $this->request( 'PATCH', "{$base}/git/refs/heads/{$r['branch']}", array( 'sha' => $c['sha'], 'force' => false ) )
			: $this->request( 'POST', "{$base}/git/refs", array( 'ref' => 'refs/heads/' . $r['branch'], 'sha' => $c['sha'] ) );
		if ( is_wp_error( $u ) ) {
			return 422 === ( $u->get_error_data()['status'] ?? 0 )
				? new WP_Error( 'wpta_github_moved', 'The repo changed while syncing (someone pushed). Run the sync again.' )
				: $u;
		}
		return array( 'sha' => $c['sha'], 'url' => $r['url'] . '/commit/' . $c['sha'] );
	}
}
