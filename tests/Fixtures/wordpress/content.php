<?php

/**
 * The sample content of the fixture site, created through the WordPress API.
 * Run by build.sh with "wp eval-file". All texts are written for these tests.
 */

require_once ABSPATH.'wp-admin/includes/image.php';
require_once ABSPATH.'wp-admin/includes/file.php';
require_once ABSPATH.'wp-admin/includes/media.php';

function fixture_post(array $post): int
{
    $date = $post['post_date'] ?? '2026-01-10 10:00:00';

    $id = wp_insert_post(array_merge([
        'post_status' => 'publish',
        'post_author' => 1,
        'post_date' => $date,
        'post_date_gmt' => get_gmt_from_date($date),
    ], $post), true);

    if (is_wp_error($id)) {
        throw new RuntimeException($id->get_error_message());
    }

    return $id;
}

function fixture_image(string $name, int $width, int $height, int $parent, array $rgb): int
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
    $file = sys_get_temp_dir().'/'.$name.'.png';
    imagepng($image, $file);

    $id = media_handle_sideload(['name' => $name.'.png', 'tmp_name' => $file], $parent, ucfirst($name));

    if (is_wp_error($id)) {
        throw new RuntimeException($id->get_error_message());
    }

    return $id;
}

// Start from an empty site.
foreach (get_posts(['post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1]) as $post) {
    wp_delete_post($post->ID, true);
}

update_option('blogdescription', 'We make things');
update_option('timezone_string', 'Europe/Berlin');
update_option('date_format', 'd.m.Y');
// Through the rewrite object, so links created below use pretty permalinks.
global $wp_rewrite;
$wp_rewrite->set_permalink_structure('/%year%/%monthnum%/%postname%/');
$wp_rewrite->set_category_base('thema');
update_option('wpseo_titles', ['separator' => 'sc-pipe']);
update_option('rank-math-options-titles', ['title_separator' => '–']);

register_post_type('event', ['public' => true, 'label' => 'Events', 'has_archive' => true, 'rewrite' => ['slug' => 'veranstaltungen']]);
flush_rewrite_rules();

// One user per hash format. WordPress itself writes the prefixed bcrypt hash.
$editor = wp_insert_user(['user_login' => 'editor', 'user_email' => 'editor@example.com', 'user_pass' => 'bcrypt-wp-password', 'display_name' => 'Eda Editor', 'role' => 'editor', 'user_registered' => '2025-02-01 09:00:00']);
$author = wp_insert_user(['user_login' => 'author', 'user_email' => 'author@example.com', 'user_pass' => 'temporary', 'display_name' => 'Arno Author', 'role' => 'author', 'user_registered' => '2015-06-01 09:00:00']);
$subscriber = wp_insert_user(['user_login' => 'reader', 'user_email' => 'reader@example.com', 'user_pass' => 'temporary', 'display_name' => 'Rhea Reader', 'role' => 'subscriber']);
$member = wp_insert_user(['user_login' => 'member', 'user_email' => 'member@example.com', 'user_pass' => 'temporary', 'display_name' => 'Mo Member', 'role' => 'contributor']);

require_once ABSPATH.WPINC.'/class-phpass.php';
global $wpdb;
$wpdb->update($wpdb->users, ['user_pass' => (new PasswordHash(8, true))->HashPassword('phpass-password')], ['ID' => $author]);
$wpdb->update($wpdb->users, ['user_pass' => md5('md5-password')], ['ID' => $subscriber]);
$wpdb->update($wpdb->users, ['user_pass' => password_hash('plain-bcrypt-password', PASSWORD_BCRYPT)], ['ID' => $member]);

// Taxonomies
$news = wp_insert_term('News', 'category')['term_id'];
$tech = wp_insert_term('Tech', 'category', ['parent' => $news, 'description' => 'Gadgets and code'])['term_id'];
$php = wp_insert_term('PHP', 'post_tag')['term_id'];

// Pages
$about = fixture_post(['post_type' => 'page', 'post_title' => 'About us', 'post_name' => 'about', 'post_content' => "We are a small team.\n\nWe build things that last."]);
$team = fixture_post(['post_type' => 'page', 'post_title' => 'Team', 'post_name' => 'team', 'post_parent' => $about, 'menu_order' => 1, 'post_content' => '<!-- wp:paragraph --><p>Meet the team.</p><!-- /wp:paragraph -->']);
update_post_meta($about, 'rank_math_title', '%title% %sep% %sitename%');
update_post_meta($about, 'rank_math_description', 'Who we are and what we do.');
update_post_meta($about, '_wp_page_template', 'template-wide.php');

// Posts, media and the reusable block
$classic = fixture_post(['post_title' => 'Classic Story', 'post_name' => 'classic-story', 'post_author' => $author, 'post_excerpt' => 'A post from the classic editor.', 'post_date' => '2026-01-10 10:00:00']);
$hero = fixture_image('hero', 1200, 800, $classic, [200, 60, 40]);
$second = fixture_image('second', 800, 600, $classic, [40, 120, 200]);
update_post_meta($hero, '_wp_attachment_image_alt', 'A red rectangle');
set_post_thumbnail($classic, $hero);

$reusable = fixture_post(['post_type' => 'wp_block', 'post_title' => 'Call to action', 'post_content' => '<!-- wp:paragraph {"className":"cta"} --><p class="cta">Get in touch!</p><!-- /wp:paragraph -->']);

$heroUrl = wp_get_attachment_url($hero);
$heroMedium = wp_get_attachment_image_url($hero, 'medium');

$block = fixture_post([
    'post_title' => 'Block Story',
    'post_name' => 'block-story',
    'post_author' => $editor,
    'post_date' => '2026-02-14 09:30:00',
    'post_content' => implode("\n\n", [
        '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">A block post</h2><!-- /wp:heading -->',
        '<!-- wp:paragraph --><p>Read more <a href="'.get_permalink($about).'">about us</a>.</p><!-- /wp:paragraph -->',
        '<!-- wp:image {"id":'.$hero.',"sizeSlug":"medium"} --><figure class="wp-block-image size-medium"><img src="'.$heroMedium.'" alt="" class="wp-image-'.$hero.'"/></figure><!-- /wp:image -->',
        '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --><!-- wp:list-item --><li>Two</li><!-- /wp:list-item --></ul><!-- /wp:list --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>Less is more.</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote --></div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->',
        '<!-- wp:embed {"url":"https://www.youtube.com/watch?v=abc123","type":"video","providerNameSlug":"youtube"} --><figure class="wp-block-embed is-type-video"><div class="wp-block-embed__wrapper">https://www.youtube.com/watch?v=abc123</div></figure><!-- /wp:embed -->',
        '<!-- wp:code --><pre class="wp-block-code"><code>[gallery] stays literal</code></pre><!-- /wp:code -->',
        '<!-- wp:block {"ref":'.$reusable.'} /-->',
        '<!-- wp:acme/hero {"title":"Custom block"} --><div class="acme-hero">Custom block</div><!-- /wp:acme/hero -->',
    ]),
]);
wp_set_post_terms($block, [$tech], 'category');
wp_set_post_terms($block, [$php], 'post_tag');
update_post_meta($block, '_yoast_wpseo_title', '%%title%% %%sep%% %%sitename%%');
update_post_meta($block, '_yoast_wpseo_metadesc', '%%excerpt%%');
update_post_meta($block, '_yoast_wpseo_canonical', get_permalink($block));
update_post_meta($block, '_yoast_wpseo_meta-robots-nofollow', '1');
update_post_meta($block, '_yoast_wpseo_opengraph-image-id', (string) $hero);
update_post_meta($block, '_yoast_wpseo_primary_category', (string) $tech);

wp_update_post([
    'ID' => $classic,
    'post_content' => implode("\n\n", [
        "Welcome to the classic story.\nIt has a line break.",
        'It links to the <a href="'.get_permalink($block).'">block story</a>.',
        '[caption id="attachment_'.$hero.'" align="alignleft" width="300"]<img src="'.$heroMedium.'" alt="A red rectangle" width="300" height="200" class="size-medium wp-image-'.$hero.'" /> A captioned image[/caption]',
        '[gallery ids="'.$hero.','.$second.'" columns="2" size="medium"]',
        '[embed]https://vimeo.com/123456[/embed]',
        '[contact-form-7 id="42" title="Contact"]',
        "<pre>Preformatted\n\n  text</pre>",
    ]),
]);
wp_set_post_terms($classic, [$news], 'category');

// An event with every kind of custom field
acf_import_field_group([
    'key' => 'group_event',
    'title' => 'Event details',
    'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'event']]],
    'fields' => [
        ['key' => 'field_subtitle', 'name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text'],
        ['key' => 'field_sold_out', 'name' => 'sold_out', 'label' => 'Sold out', 'type' => 'true_false'],
        ['key' => 'field_date', 'name' => 'date', 'label' => 'Date', 'type' => 'date_picker'],
        ['key' => 'field_doors', 'name' => 'doors', 'label' => 'Doors', 'type' => 'date_time_picker'],
        ['key' => 'field_price', 'name' => 'price', 'label' => 'Price', 'type' => 'number'],
        ['key' => 'field_poster', 'name' => 'poster', 'label' => 'Poster', 'type' => 'image'],
        ['key' => 'field_photos', 'name' => 'photos', 'label' => 'Photos', 'type' => 'gallery'],
        ['key' => 'field_related', 'name' => 'related', 'label' => 'Related', 'type' => 'relationship'],
        ['key' => 'field_link', 'name' => 'tickets', 'label' => 'Tickets', 'type' => 'link'],
        ['key' => 'field_lineup', 'name' => 'lineup', 'label' => 'Line-up', 'type' => 'repeater', 'sub_fields' => [
            ['key' => 'field_lineup_artist', 'name' => 'artist', 'label' => 'Artist', 'type' => 'text'],
            ['key' => 'field_lineup_headliner', 'name' => 'headliner', 'label' => 'Headliner', 'type' => 'true_false'],
        ]],
        ['key' => 'field_sections', 'name' => 'sections', 'label' => 'Sections', 'type' => 'flexible_content', 'layouts' => [
            'layout_text' => ['key' => 'layout_text', 'name' => 'text', 'label' => 'Text', 'sub_fields' => [
                ['key' => 'field_sections_body', 'name' => 'body', 'label' => 'Body', 'type' => 'wysiwyg'],
            ]],
            'layout_quote' => ['key' => 'layout_quote', 'name' => 'quote', 'label' => 'Quote', 'sub_fields' => [
                ['key' => 'field_sections_quote', 'name' => 'quote', 'label' => 'Quote', 'type' => 'text'],
                ['key' => 'field_sections_by', 'name' => 'by', 'label' => 'By', 'type' => 'text'],
            ]],
        ]],
        ['key' => 'field_venue', 'name' => 'venue', 'label' => 'Venue', 'type' => 'group', 'sub_fields' => [
            ['key' => 'field_venue_name', 'name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['key' => 'field_venue_capacity', 'name' => 'capacity', 'label' => 'Capacity', 'type' => 'number'],
        ]],
    ],
]);

$event = fixture_post(['post_type' => 'event', 'post_title' => 'Summer Concert', 'post_name' => 'summer-concert', 'post_date' => '2026-03-01 18:00:00', 'post_content' => '<!-- wp:paragraph --><p>Live on stage.</p><!-- /wp:paragraph -->']);
update_field('field_subtitle', 'An evening outdoors', $event);
update_field('field_sold_out', 1, $event);
update_field('field_date', '20260715', $event);
update_field('field_doors', '2026-07-15 19:00:00', $event);
update_field('field_price', 25.5, $event);
update_field('field_poster', $hero, $event);
update_field('field_photos', [$second, $hero], $event);
update_field('field_related', [$block], $event);
update_field('field_link', ['title' => 'Buy tickets', 'url' => get_permalink($about), 'target' => '_blank'], $event);
update_field('field_lineup', [
    ['field_lineup_artist' => 'The Openers', 'field_lineup_headliner' => 0],
    ['field_lineup_artist' => 'The Headliners', 'field_lineup_headliner' => 1],
], $event);
update_field('field_sections', [
    ['acf_fc_layout' => 'text', 'field_sections_body' => "First paragraph.\n\nSecond paragraph."],
    ['acf_fc_layout' => 'quote', 'field_sections_quote' => 'Unforgettable', 'field_sections_by' => 'A visitor'],
], $event);
update_field('field_venue', ['field_venue_name' => 'Open Air Stage', 'field_venue_capacity' => 800], $event);

// Content that is not migrated or not imported
$landing = fixture_post(['post_type' => 'page', 'post_title' => 'Landing', 'post_name' => 'landing', 'post_content' => '<div class="elementor">Built with a page builder</div>']);
update_post_meta($landing, '_elementor_data', '[{"id":"a1b2","elType":"section"}]');
fixture_post(['post_title' => 'Unfinished', 'post_status' => 'draft', 'post_content' => 'Not ready yet.']);

// A classic menu in a theme location
$menu = wp_create_nav_menu('Main Menu');
$home = wp_update_nav_menu_item($menu, 0, ['menu-item-title' => 'Home', 'menu-item-url' => home_url('/'), 'menu-item-type' => 'custom', 'menu-item-status' => 'publish', 'menu-item-position' => 1]);
$aboutItem = wp_update_nav_menu_item($menu, 0, ['menu-item-object-id' => $about, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-position' => 2]);
wp_update_nav_menu_item($menu, 0, ['menu-item-object-id' => $team, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-parent-id' => $aboutItem, 'menu-item-status' => 'publish', 'menu-item-position' => 3]);
wp_update_nav_menu_item($menu, 0, ['menu-item-object-id' => $news, 'menu-item-object' => 'category', 'menu-item-type' => 'taxonomy', 'menu-item-status' => 'publish', 'menu-item-position' => 4]);
wp_update_nav_menu_item($menu, 0, ['menu-item-title' => 'Partner', 'menu-item-url' => 'https://example.net/', 'menu-item-type' => 'custom', 'menu-item-target' => '_blank', 'menu-item-classes' => 'external', 'menu-item-status' => 'publish', 'menu-item-position' => 5]);
set_theme_mod('nav_menu_locations', ['primary' => $menu]);

// A navigation menu as block themes store it
fixture_post([
    'post_type' => 'wp_navigation',
    'post_title' => 'Header navigation',
    'post_name' => 'header-navigation',
    'post_content' => '<!-- wp:home-link {"label":"Start"} /-->'
        .'<!-- wp:navigation-link {"label":"About","type":"page","id":'.$about.',"url":"'.get_permalink($about).'","kind":"post-type"} /-->'
        .'<!-- wp:navigation-submenu {"label":"More","url":"#","kind":"custom"} -->'
        .'<!-- wp:navigation-link {"label":"News","type":"category","id":'.$news.',"url":"'.get_category_link($news).'","kind":"taxonomy"} /-->'
        .'<!-- /wp:navigation-submenu -->',
]);

// Comments: approved, a reply by a user, pending and spam
$first = wp_insert_comment(['comment_post_ID' => $classic, 'comment_author' => 'Visitor', 'comment_author_email' => 'visitor@example.com', 'comment_author_url' => 'https://visitor.example', 'comment_content' => 'Great read!', 'comment_approved' => 1, 'comment_date' => '2026-01-11 08:00:00', 'comment_date_gmt' => '2026-01-11 07:00:00']);
wp_insert_comment(['comment_post_ID' => $classic, 'comment_parent' => $first, 'user_id' => $editor, 'comment_author' => 'Eda Editor', 'comment_author_email' => 'editor@example.com', 'comment_content' => 'Thank you!', 'comment_approved' => 1, 'comment_date' => '2026-01-11 09:00:00', 'comment_date_gmt' => '2026-01-11 08:00:00']);
wp_insert_comment(['comment_post_ID' => $block, 'comment_author' => 'Quiet', 'comment_author_email' => 'quiet@example.com', 'comment_content' => 'Waiting for approval', 'comment_approved' => 0, 'comment_date' => '2026-02-15 10:00:00', 'comment_date_gmt' => '2026-02-15 09:00:00']);
wp_insert_comment(['comment_post_ID' => $block, 'comment_author' => 'Spammer', 'comment_author_email' => 'spam@example.com', 'comment_content' => 'Buy now', 'comment_approved' => 'spam', 'comment_date' => '2026-02-16 10:00:00', 'comment_date_gmt' => '2026-02-16 09:00:00']);

WP_CLI::success('Fixture content created.');
