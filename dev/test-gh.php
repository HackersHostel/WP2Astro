<?php
// Dev only (copy into the test site's mu-plugins/): point WP2Astro's GitHub and Git LFS calls at dev/gh.py.
add_filter( 'wpta_github_api', fn() => 'http://127.0.0.1:9999' );
add_filter( 'wpta_github_lfs', fn() => 'http://127.0.0.1:9999/__lfs' );
