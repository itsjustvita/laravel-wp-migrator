<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Content\Shortcodes\Handlers\CaptionHandler;
use ItsJustVita\WpMigrator\Inventory\SiteFacts;
use ItsJustVita\WpMigrator\Inventory\SiteInventory;
use ItsJustVita\WpMigrator\WpMigrator;

function readSite(): SiteFacts
{
    return app(SiteInventory::class)->read();
}

it('reads the site settings, content counts and taxonomies', function () {
    $site = $this->source();
    $site->option('blogname', 'Acme')->option('home', 'https://old.example.com')->option('template', 'acme-classic');
    $site->post(['post_title' => 'One']);
    $site->post(['post_title' => 'Two', 'post_status' => 'draft']);
    $site->page(['post_title' => 'About']);
    $site->term('category', 'News');
    $site->term('genre', 'Jazz');
    $site->term('genre', 'Rock');
    $site->term('post_translations', 'pll_1');
    $menu = $site->menu('Main');
    $site->menuLocations(['primary' => $menu], 'acme-child');

    $facts = readSite();

    expect($facts->reachable)->toBeTrue()
        ->and($facts->error)->toBeNull()
        ->and($facts->siteName)->toBe('Acme')
        ->and($facts->homeUrl)->toBe('https://old.example.com')
        ->and($facts->stylesheet)->toBe('acme-child')
        ->and($facts->template)->toBe('acme-classic')
        ->and($facts->postCounts['post'])->toEqual(['publish' => 1, 'draft' => 1])
        ->and($facts->postCounts['page'])->toBe(['publish' => 1])
        ->and($facts->taxonomies)->toBe(['genre' => 2, 'category' => 1]);
});

it('counts the shortcodes and blocks the package cannot render yet', function () {
    $site = $this->source();
    $first = $site->post(['post_content' => '[button]a[/button] [button] [gallery ids="1"] [caption]x[/caption] [[escaped]]']);
    $second = $site->post(['post_content' => '<!-- wp:acme/hero /--><!-- wp:group --><div><!-- wp:acme/card /--></div><!-- /wp:group --><!-- wp:paragraph --><p>[button]</p><!-- /wp:paragraph -->']);
    $site->post(['post_content' => '[button]', 'post_status' => 'trash']);
    $site->post(['post_content' => '[button]', 'post_type' => 'wp_block']);
    $site->post(['post_content' => '<!-- wp:block {"ref":1} /--><!-- wp:acme/hero /-->']);

    $facts = readSite();

    expect($facts->shortcodes)->toBe([
        'button' => ['count' => 3, 'post_ids' => [$first, $second]],
    ])
        ->and($facts->blocks)->toBe([
            'acme/hero' => ['count' => 2, 'post_ids' => [$second, $second + 3]],
            'acme/card' => ['count' => 1, 'post_ids' => [$second]],
        ]);
});

it('leaves out shortcodes that have a handler', function () {
    app(WpMigrator::class)->mapShortcode('button', CaptionHandler::class);
    $this->source()->post(['post_content' => '[button] [price]']);

    expect(array_keys(readSite()->shortcodes))->toBe(['price']);
});

it('keeps at most five example posts per shortcode', function () {
    $site = $this->source();
    $ids = [];

    foreach (range(1, 7) as $i) {
        $ids[] = $site->post(['post_content' => '[button]']);
    }

    expect(readSite()->shortcodes['button'])->toBe(['count' => 7, 'post_ids' => array_slice($ids, 0, 5)]);
});

it('lists the posts that use a page template', function () {
    $site = $this->source();
    $about = $site->page(['post_title' => 'About']);
    $site->postMeta($about, '_wp_page_template', 'template-wide.php');
    $story = $site->post(['post_title' => 'Story']);
    $site->postMeta($story, '_wp_page_template', 'template-wide.php');
    $default = $site->page(['post_title' => 'Default']);
    $site->postMeta($default, '_wp_page_template', 'default');
    $draft = $site->page(['post_title' => 'Trashed', 'post_status' => 'trash']);
    $site->postMeta($draft, '_wp_page_template', 'template-wide.php');

    expect(readSite()->pageTemplates)->toBe([
        'template-wide.php' => [['title' => 'About', 'type' => 'page'], ['title' => 'Story', 'type' => 'post']],
    ]);
});

it('reads the active plugins, menus and widgets', function () {
    $site = $this->source();
    $site->option('active_plugins', serialize(['contact-form-7/wp-contact-form-7.php', 7, 'hello.php']));
    $site->option('sidebars_widgets', serialize([
        'sidebar-1' => ['search-2', 'text-3'],
        'footer' => [],
        'wp_inactive_widgets' => ['text-9'],
        'array_version' => 3,
    ]));
    $main = $site->menu('Main Menu');
    $site->menuItem($main, ['title' => 'Home']);
    $site->menuItem($main, ['title' => 'About']);
    $site->menuItem($main, ['title' => 'Draft', 'status' => 'draft']);
    $footer = $site->menu('Footer');
    $site->menuLocations(['primary' => $main, 'footer' => $footer]);

    $facts = readSite();

    expect($facts->plugins)->toBe(['contact-form-7/wp-contact-form-7.php', 'hello.php'])
        ->and($facts->widgets)->toBe(['sidebar-1' => 2, 'footer' => 0])
        ->and($facts->menus)->toBe([
            'primary' => ['menu' => 'Main Menu', 'items' => 2],
            'footer' => ['menu' => 'Footer', 'items' => 0],
        ]);
});

it('treats unreadable options as empty', function () {
    $site = $this->source();
    $site->option('active_plugins', 'a:1:{broken');
    $site->option('sidebars_widgets', 's:3:"abc";');

    $facts = readSite();

    expect($facts->reachable)->toBeTrue()
        ->and($facts->plugins)->toBe([])
        ->and($facts->widgets)->toBe([]);
});

it('reports content that is not migrated', function () {
    $site = $this->source();
    $page = $site->page(['post_title' => 'Landing']);
    $site->postMeta($page, '_elementor_data', '[{"id":"x"}]');

    expect(array_map(fn ($finding) => $finding->key, readSite()->findings))->toBe(['elementor']);
});

it('reports a source database it cannot read', function () {
    config()->set('database.connections.wordpress', ['driver' => 'sqlite', 'database' => '/nonexistent/wordpress.sqlite']);
    app('db')->purge('wordpress');

    $facts = readSite();

    expect($facts->reachable)->toBeFalse()
        ->and($facts->error)->toContain('wordpress.sqlite')
        ->and($facts->postCounts)->toBe([]);
});
