<?php

require get_template_directory().'/inc/setup.php';

add_action('init', function () {
    register_post_type('event', [
        'labels' => ['name' => __('Events', 'acme'), 'singular_name' => __('Event', 'acme')],
        'public' => true,
        'has_archive' => true,
        'hierarchical' => false,
        'rewrite' => ['slug' => 'veranstaltungen'],
        'supports' => ['title', 'editor', 'thumbnail'],
    ]);

    register_taxonomy('genre', ['event'], [
        'label' => __('Genres', 'acme'),
        'hierarchical' => true,
        'rewrite' => ['slug' => 'genres'],
    ]);

    $type = get_option('acme_extra_type');
    register_post_type($type, ['public' => true]);
});

add_shortcode('button', 'acme_button_shortcode');
add_shortcode('price', function ($atts) {
    return '<span class="price">'.esc_html($atts['amount'] ?? '').'</span>';
});

register_nav_menus([
    'primary' => __('Primary Menu', 'acme'),
    'footer' => esc_html__('Footer Menu', 'acme'),
]);

register_sidebar([
    'name' => __('Blog Sidebar', 'acme'),
    'id' => 'sidebar-1',
]);

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('acme-style', get_stylesheet_uri(), [], '1.2.0');
    wp_enqueue_style('acme-fonts', 'https://fonts.example.net/css?family=Inter');
    wp_enqueue_script('acme-app', get_template_directory_uri().'/assets/app.js', ['jquery'], '1.2.0', true);
});

register_block_type(__DIR__.'/blocks/hero');

function acme_button_shortcode($atts, $content = '')
{
    return '<a class="button" href="'.esc_url($atts['url'] ?? '#').'">'.$content.'</a>';
}
