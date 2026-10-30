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

	/**
	 * @param string|null $raw JSON body already encoded (large blobs), instead of $body.
	 * @return array|WP_Error Decoded JSON.
	 */
	public function request( string $method, string $path, ?array $body = null, array $ok = array( 200, 201 ), ?string $raw = null ) {
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
		if ( null !== $body || null !== $raw ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = $raw ?? wp_json_encode( $body );
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

	/** Git's blob id of a file, read in chunks so large media doesn't have to fit in memory. */
	public static function blob_sha_file( string $file ): string {
		$ctx = hash_init( 'sha1' );
		hash_update( $ctx, 'blob ' . filesize( $file ) . "\0" );
		hash_update_file( $ctx, $file );
		return hash_final( $ctx );
	}

	/**
	 * Upload a file as a blob. The JSON body is built by hand: base64 needs no escaping, and this keeps one encoded
	 * copy in memory instead of several.
	 *
	 * @return string|WP_Error Blob SHA.
	 */
	public function blob_file( array $r, string $file ) {
		$res = $this->request( 'POST', "/repos/{$r['owner']}/{$r['repo']}/git/blobs", null, array( 200, 201 ), '{"encoding":"base64","content":"' . base64_encode( (string) file_get_contents( $file ) ) . '"}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return is_wp_error( $res ) ? $res : $res['sha'];
	}

	/**
	 * Store files in the repo's Git LFS storage (batch API, basic transfer). Objects GitHub already has are skipped.
	 *
	 * @param array<int, array{oid:string, size:int, file:string}> $objects
	 * @return true|WP_Error
	 */
	public function lfs_upload( array $r, array $objects ) {
		if ( ! $objects ) {
			return true;
		}

		/**
		 * Filters the Git LFS endpoint of the repository WP2Astro pushes to.
		 *
		 * Point it at GitHub Enterprise Server or a test server. Used when media is stored with Git LFS.
		 *
		 * @since 0.3.0
		 *
		 * @param string $url  LFS endpoint. Default the repository's web address followed by '.git/info/lfs'.
		 * @param array  $repo Repository: owner, repo, branch, url.
		 */
		$base    = rtrim( (string) apply_filters( 'wpta_github_lfs', $r['url'] . '.git/info/lfs', $r ), '/' );
		$headers = array(
			'Accept'        => 'application/vnd.git-lfs+json',
			'Content-Type'  => 'application/vnd.git-lfs+json',
			'Authorization' => 'Basic ' . base64_encode( $r['owner'] . ':' . $this->token ),
			'User-Agent'    => 'wp2astro',
		);
		$res = wp_remote_post(
			$base . '/objects/batch',
			array(
				'timeout' => 60,
				'headers' => $headers,
				'body'    => wp_json_encode(
					array(
						'operation' => 'upload',
						'transfers' => array( 'basic' ),
						'ref'       => array( 'name' => 'refs/heads/' . $r['branch'] ),
						'objects'   => array_map( fn( $o ) => array( 'oid' => $o['oid'], 'size' => $o['size'] ), $objects ),
					)
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 200 !== $code ) {
			return new WP_Error( 'wpta_lfs_' . $code, 'Git LFS: ' . ( is_array( $data ) && isset( $data['message'] ) ? $data['message'] : 'HTTP ' . $code ) );
		}
		$files = array_column( $objects, 'file', 'oid' );
		foreach ( (array) ( $data['objects'] ?? array() ) as $o ) {
			if ( ! empty( $o['error'] ) ) {
				return new WP_Error( 'wpta_lfs', 'Git LFS: ' . ( $o['error']['message'] ?? 'object refused' ) );
			}
			$up = $o['actions']['upload'] ?? null;
			if ( ! $up || ! isset( $files[ $o['oid'] ] ) ) {
				continue; // Already stored.
			}
			$put = wp_remote_request(
				$up['href'],
				array(
					'method'  => 'PUT',
					'timeout' => 300,
					'headers' => array_merge( array( 'Content-Type' => 'application/octet-stream' ), (array) ( $up['header'] ?? array() ) ),
					'body'    => (string) file_get_contents( $files[ $o['oid'] ] ), // phpcs:ignore WordPress.WP.AlternativeFunctions
				)
			);
			$put_code = is_wp_error( $put ) ? 0 : (int) wp_remote_retrieve_response_code( $put );
			if ( $put_code < 200 || $put_code > 299 ) {
				return is_wp_error( $put ) ? $put : new WP_Error( 'wpta_lfs_upload', 'Git LFS upload of ' . basename( $files[ $o['oid'] ] ) . ' failed: HTTP ' . $put_code );
			}
			$verify = $o['actions']['verify'] ?? null;
			if ( $verify ) {
				$v      = wp_remote_post( $verify['href'], array( 'timeout' => 60, 'headers' => array_merge( $headers, (array) ( $verify['header'] ?? array() ) ), 'body' => wp_json_encode( array( 'oid' => $o['oid'], 'size' => $o['size'] ) ) ) );
				$v_code = is_wp_error( $v ) ? 0 : (int) wp_remote_retrieve_response_code( $v );
				if ( $v_code < 200 || $v_code > 299 ) {
					return new WP_Error( 'wpta_lfs_verify', 'Git LFS did not confirm ' . basename( $files[ $o['oid'] ] ) . '.' );
				}
			}
		}
		return true;
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
			return array( 'head' => null, 'tree' => null, 'files' => array(), 'complete' => true );
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
		return array( 'head' => $head, 'tree' => $commit['tree']['sha'], 'files' => $files, 'complete' => empty( $tree['truncated'] ) );
	}

	/**
	 * Commit tree changes (WPTA_Sync::tree_changes()) on top of $parent. With $base_tree, only the listed paths change
	 * (a null sha deletes one); without it (an empty branch), the entries are the whole tree.
	 *
	 * @return array{sha:string, url:string}|WP_Error
	 */
	public function commit( array $r, array $changes, string $message, ?string $parent, ?string $base_tree = null ) {
		$base = "/repos/{$r['owner']}/{$r['repo']}";
		$body = array( 'tree' => $changes );
		if ( $base_tree ) {
			$body['base_tree'] = $base_tree;
		} else {
			$body['tree'] = array_values( array_filter( $changes, fn( $e ) => null !== $e['sha'] ) );
		}
		$t = $this->request( 'POST', "{$base}/git/trees", $body );
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
