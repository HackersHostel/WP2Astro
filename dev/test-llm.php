<?php
// Copy to the test site's wp-content/mu-plugins/: sends every AI provider to the mock in dev/llm.py.
add_filter( 'wpta_provider', function ( $p ) {
	$p['base_url'] = 'http://127.0.0.1:11434/v1' . ( 'chatgpt' === $p['id'] ? '/chatgpt' : '' );
	return $p;
} );
