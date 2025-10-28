<?php
add_action('wp_enqueue_scripts', function () {
    // parent styl (Twenty Twenty-Four)
    wp_enqueue_style(
        'twentytwentyfour-style',
        get_template_directory_uri() . '/style.css',
        [],
        wp_get_theme('twentytwentyfour')->get('Version')
    );

    // child styl (PF Child)
    wp_enqueue_style(
        'pf-child',
        get_stylesheet_uri(),
        ['twentytwentyfour-style'],
        wp_get_theme()->get('Version')
    );
});
