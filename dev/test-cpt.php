<?php
// Dev only (copy into the test site's mu-plugins/): the public 'project' post type dev/seed.php and the CI job expect.
add_action('init', function () {
    register_post_type('project', [
        'label' => 'Projects',
        'public' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'projects'],
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'thumbnail'],
    ]);
});
