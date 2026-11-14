<?php

add_shortcode('child-only', 'acme_child_shortcode');

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('acme-child', get_stylesheet_uri(), ['acme-style']);
});
