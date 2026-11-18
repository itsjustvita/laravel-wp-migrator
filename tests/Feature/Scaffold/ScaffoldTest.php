<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Inventory\Frontend;
use ItsJustVita\WpMigrator\Inventory\SiteFacts;
use ItsJustVita\WpMigrator\Inventory\Theme\PostTypeDefinition;
use ItsJustVita\WpMigrator\Inventory\Theme\TaxonomyDefinition;
use ItsJustVita\WpMigrator\Inventory\Theme\Template;
use ItsJustVita\WpMigrator\Inventory\Theme\ThemeInfo;
use ItsJustVita\WpMigrator\Inventory\Theme\ThemeInventory;
use ItsJustVita\WpMigrator\Scaffold\Scaffold;

/**
 * @param  array<string, mixed>  $theme
 * @param  array<string, mixed>  $site
 * @param  array<string, array<string, string>>  $attributes
 */
function scaffold(array $theme = [], array $site = [], array $attributes = []): Scaffold
{
    $inventory = new ThemeInventory(...array_merge([
        'theme' => new ThemeInfo('shop', '/themes/shop', 'Shop'),
        'child' => null,
        'isBlockTheme' => false,
        'templates' => [new Template('index.php', 'index')],
        'templateParts' => [],
        'postTypes' => [],
        'taxonomies' => [],
        'shortcodes' => [],
        'menuLocations' => [],
        'sidebars' => [],
        'blockTypes' => [],
        'scripts' => [],
        'styles' => [],
        'features' => [],
        'warnings' => [],
    ], $theme));

    $facts = new SiteFacts(...array_merge(['reachable' => true, 'postCounts' => ['page' => ['publish' => 1], 'post' => ['publish' => 1]]], $site));

    return new Scaffold(new Frontend($inventory, $facts, config()), $attributes);
}

/**
 * @param  array<string, string>  $files
 */
function lintScaffold(array $files): void
{
    foreach ($files as $path => $content) {
        if (! str_ends_with($path, '.php') || str_ends_with($path, '.blade.php')) {
            continue;
        }

        $file = tempnam(sys_get_temp_dir(), 'scaffold');
        file_put_contents((string) $file, $content);
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg((string) $file).' 2>&1', $output, $status);
        unlink((string) $file);

        expect($status)->toBe(0, $path.': '.implode("\n", $output));
    }
}

it('writes the routes in an order where catch-all patterns come last', function () {
    config()->set('wp-migrator.urls.patterns', ['page' => '/{path}', 'post' => '/news/{year}/{month}/{slug}', '*' => '/{type}/{id}']);
    config()->set('wp-migrator.urls.term_patterns', ['category' => '/topics/{path}']);

    $files = scaffold(
        ['templates' => [new Template('index.php', 'index'), new Template('search.php', 'search')], 'postTypes' => [new PostTypeDefinition('book', 'Books', true, true, false, null, [], 'functions.php')]],
        ['postCounts' => ['page' => ['publish' => 1], 'post' => ['publish' => 1], 'book' => ['publish' => 1]], 'taxonomies' => ['category' => 2]],
    )->files();

    expect($files['routes/wp.php'])->toContain(<<<'PHP'
        Route::get('/', [WpController::class, 'home'])->name('wp.home');
        Route::get('/search', [WpController::class, 'search'])->name('wp.search');
        Route::get('/news', [WpController::class, 'archive'])->defaults('type', 'post')->name('wp.post.index');
        Route::get('/book', [WpController::class, 'archive'])->defaults('type', 'book')->name('wp.book.index');
        Route::get('/news/{year}/{month}/{slug}', [WpController::class, 'show'])->defaults('type', 'post')->where('year', '[0-9]{4}')->where('month', '[0-9]{2}')->name('wp.post.show');
        Route::get('/book/{id}', [WpController::class, 'show'])->defaults('type', 'book')->where('id', '[0-9]+')->name('wp.book.show');
        Route::get('/topics/{path}', [WpController::class, 'term'])->defaults('taxonomy', 'category')->where('path', '.+')->name('wp.term.category');
        Route::get('/{path}', [WpController::class, 'show'])->defaults('type', 'page')->where('path', '.+')->name('wp.page.show');
        PHP);

    lintScaffold($files);
});

it('leaves out routes that would clash and tells why', function () {
    config()->set('wp-migrator.urls.patterns', ['page' => '/{path}', 'post' => '/{slug}', 'event' => '/events/{slug}']);
    config()->set('wp-migrator.urls.term_patterns', ['category' => null]);
    config()->set('wp-migrator.urls.author_pattern', '/author/{slug}');

    $scaffold = scaffold(
        ['taxonomies' => [new TaxonomyDefinition('genre', 'Genres', ['event'], true, null, 'functions.php')], 'postTypes' => [new PostTypeDefinition('book', 'Books', true, false, false, null, [], 'functions.php')]],
        ['postCounts' => ['page' => ['publish' => 1], 'post' => ['publish' => 1], 'book' => ['publish' => 1]], 'taxonomies' => ['genre' => 1]],
    );
    config()->set('wp-migrator.urls.patterns', ['page' => '/{path}', 'post' => '/{slug}']);
    $files = $scaffold->files();

    expect($files['routes/wp.php'])
        ->toContain("Route::get('/{path}', [WpController::class, 'show'])->defaults('type', 'page')")
        ->not->toContain("Route::get('/{slug}'")
        ->not->toContain('book')
        ->and($scaffold->notes())->toBe([
            'The post type "book" has no URL pattern, so it got no route; add one to urls.patterns and scaffold again.',
            'The taxonomy "genre" has no URL pattern, so its archive got no route; add one to urls.term_patterns and scaffold again.',
            'The route for wp.post.show is the same as the one for wp.page.show, so it was left out; give it a URL pattern of its own.',
            'Author archives are not scaffolded: the imported users keep no slug to find them by.',
        ]);
});

it('bakes the front page, post types, taxonomies and layouts into the controller', function () {
    $files = scaffold(
        ['templates' => [new Template('index.php', 'index'), new Template('templates/full.html', 'custom', 'Full width', null, ['page'])]],
        ['frontPage' => "it's-home", 'taxonomies' => ['category' => 1]],
    )->files();
    $controller = $files['app/Http/Controllers/WpController.php'];

    expect($controller)
        ->toContain("private const ?string FRONT_PAGE = 'it\\'s-home';")
        ->toContain("private const array TYPES = [\n        'page' => 'pages',\n        'post' => 'posts',\n    ];")
        ->toContain("private const array TAXONOMIES = [\n        'category' => 'categories',\n    ];")
        ->toContain("private const array LAYOUTS = [\n        'full' => 'layouts.full-width',\n    ];")
        ->and($files['resources/views/layouts/full-width.blade.php'])->toContain("@section('main-class', 'template-full-width')")
        ->and(scaffold()->files()['app/Http/Controllers/WpController.php'])
        ->toContain('private const ?string FRONT_PAGE = NULL;')
        ->toContain('private const array TAXONOMIES = [];');

    lintScaffold($files);
});

it('writes the views the migration plan names, and only those', function () {
    $files = scaffold([
        'templates' => [
            new Template('index.php', 'index'),
            new Template('single.php', 'single', 'post'),
            new Template('page-contact.php', 'page', null, 'contact', [], 'child'),
            new Template('single-book-launch.php', 'single', 'book', 'launch'),
        ],
    ])->files();

    expect(array_keys($files))->toContain('resources/views/home.blade.php', 'resources/views/pages/show.blade.php', 'resources/views/posts/show.blade.php', 'resources/views/posts/index.blade.php', 'resources/views/pages/contact.blade.php')
        ->not->toContain('resources/views/search.blade.php')
        ->not->toContain('resources/views/errors/404.blade.php')
        ->not->toContain('resources/views/books/launch.blade.php')
        ->and($files['resources/views/pages/contact.blade.php'])->toStartWith('{{-- The page "contact", from page-contact.php of the child theme.')
        ->and($files['resources/views/posts/show.blade.php'])->toStartWith('{{-- A single post of the type "post", from single.php of the theme.')
        ->toContain('<time datetime=')
        ->and($files['resources/views/pages/show.blade.php'])->not->toContain('<time')
        ->and($files['routes/wp.php'])->not->toContain('wp.search');
});

it('puts the menus of the theme into the header and footer', function () {
    $files = scaffold(['menuLocations' => ['main-nav' => 'Main', 'footer-legal' => 'Legal', 'footer-links' => 'Links', 'social' => 'Social']])->files();

    expect($files['resources/views/components/wp/header.blade.php'])->toContain('<x-wp.menu location="main-nav" />')
        ->and($files['resources/views/components/wp/footer.blade.php'])
        ->toContain('<x-wp.menu location="footer-legal" />')
        ->toContain('<x-wp.menu location="footer-links" />')
        ->not->toContain('social')
        ->and(scaffold()->files()['resources/views/components/wp/header.blade.php'])->toContain('<x-wp.menu location="primary" />')
        ->and(scaffold()->files()['resources/views/components/wp/footer.blade.php'])->not->toContain('x-wp.menu');
});

it('writes views for blocks without one', function () {
    $files = scaffold(site: ['blocks' => [
        'elementor/acme-hero' => ['count' => 3, 'post_ids' => [1]],
        'elementor/acme-card' => ['count' => 1, 'post_ids' => [2]],
        'acme/teaser' => ['count' => 1, 'post_ids' => [3]],
        'core/latest-posts' => ['count' => 2, 'post_ids' => [4]],
    ]], attributes: ['elementor/acme-hero' => ['hero_title' => 'text', 'cards' => 'list', 'link' => 'object', 'color' => 'value']])->files();

    expect($files['resources/views/vendor/wp-migrator/blocks/elementor/acme-hero.blade.php'])
        ->toContain('The Elementor widget "acme-hero", used 3 times.')
        ->toContain('$attrs holds its settings: hero_title, cards (list), link (object), color.')
        ->toContain("<div class=\"hero_title\">{!! \$attrs['hero_title'] ?? '' !!}</div>")
        ->not->toContain('@foreach')
        ->and($files['resources/views/vendor/wp-migrator/blocks/elementor/acme-card.blade.php'])
        ->toContain('used once')
        ->toContain('$attrs holds its settings.')
        ->toContain('@foreach ($attrs as $key => $value)')
        ->and($files['resources/views/vendor/wp-migrator/blocks/acme/teaser.blade.php'])->toContain('{!! $content !!}')
        ->and($files['resources/views/vendor/wp-migrator/blocks/latest-posts.blade.php'])->toContain('The block "core/latest-posts", used 2 times.');
});
