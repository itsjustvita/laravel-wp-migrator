<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
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

it('puts patterns that match whole paths behind the routes they would hide', function () {
    config()->set('wp-migrator.urls.patterns', ['page' => '/{path}', 'post' => '/blog/{path}']);
    config()->set('wp-migrator.urls.term_patterns', ['category' => '/blog/category/{path}', 'post_tag' => '/blog/tag/{slug}']);

    $routes = scaffold([], ['taxonomies' => ['category' => 1, 'post_tag' => 1], 'authors' => ['alice' => 3]])->files()['routes/wp.php'];

    preg_match_all("/Route::get\\('([^']+)'/", $routes, $uris);

    expect($uris[1])->toBe(['/', '/blog', '/blog/tag/{slug}', '/blog/author/{slug}', '/blog/category/{path}', '/blog/{path}', '/{path}']);
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
        ]);
});

it('gives the authors an archive that is found by their slug', function () {
    $scaffold = scaffold(
        ['templates' => [new Template('index.php', 'index'), new Template('author.php', 'author')]],
        ['authors' => ['2024' => 7, 'alice' => 3, 'müller' => 12]],
    );
    $files = $scaffold->files();

    expect($files['routes/wp.php'])->toContain("Route::get('/blog/author/{slug}', [WpController::class, 'author'])->name('wp.author');")
        ->and($files['app/Http/Controllers/WpController.php'])
        ->toContain("private const array AUTHORS = [\n        2024 => 7,\n        'alice' => 3,\n        'müller' => 12,\n    ];")
        ->toContain('public function author(Request $request): View')
        ->and($files['resources/views/authors/show.blade.php'])
        ->toStartWith('{{-- The archive of an author, from author.php of the theme. Generated by php artisan wp:scaffold. --}}')
        ->toContain("@section('title', \$author->getAttribute('name'))")
        ->toContain("<h1>{{ \$author->getAttribute('name') }}</h1>")
        ->toContain("@include('components.wp.post-list', ['posts' => \$posts])")
        ->and($scaffold->notes())->toBe([]);

    lintScaffold($files);
});

it('finds authors by their old ID when the pattern says so, also without the source database', function () {
    config()->set('wp-migrator.urls.author_pattern', '/writers/{id}');

    $scaffold = scaffold([], ['reachable' => false, 'postCounts' => []]);
    $files = $scaffold->files();

    expect($files['routes/wp.php'])->toContain("Route::get('/writers/{id}', [WpController::class, 'author'])->where('id', '[0-9]+')->name('wp.author');")
        ->and($files['app/Http/Controllers/WpController.php'])->toContain('private const array AUTHORS = [];')
        ->and($files)->toHaveKey('resources/views/authors/show.blade.php')
        ->and($scaffold->notes())->toBe([]);
});

it('names the author by the attribute that holds the display name', function (mixed $attributes, string $title, string $heading) {
    config()->set('wp-migrator.users.attributes', $attributes);

    $view = scaffold([], ['authors' => ['alice' => 3]])->files()['resources/views/authors/show.blade.php'];

    expect($view)->toContain($title)->toContain($heading);
})->with([
    'a custom attribute' => [['full_name' => 'display_name', 'email' => 'email'], "@section('title', \$author->getAttribute('full_name'))", "<h1>{{ \$author->getAttribute('full_name') }}</h1>"],
    'none' => [['email' => 'email'], "@section('title', 'Author')", '<h1>Author</h1>'],
    'an old configuration without the setting' => [null, "@section('title', \$author->getAttribute('name'))", "<h1>{{ \$author->getAttribute('name') }}</h1>"],
]);

it('lists up to 500 authors in the controller', function () {
    $authors = array_combine(array_map(fn (int $i): string => 'author-'.$i, range(1, 500)), range(1, 500));

    $scaffold = scaffold([], ['authors' => $authors]);

    expect($scaffold->files()['routes/wp.php'])->toContain('wp.author')
        ->and($scaffold->notes())->toBe([]);
});

it('writes no author archive when it has no route to give', function (?string $pattern, array $site, array $notes) {
    config()->set('wp-migrator.urls.author_pattern', $pattern);

    $scaffold = scaffold(['templates' => [new Template('index.php', 'index'), new Template('author.php', 'author')]], $site);
    $files = $scaffold->files();

    expect($files['routes/wp.php'])->not->toContain('wp.author')
        ->and($files)->not->toHaveKey('resources/views/authors/show.blade.php')
        ->and($files['app/Http/Controllers/WpController.php'])->not->toContain('function author(')->not->toContain('AUTHORS')
        ->and($scaffold->notes())->toBe($notes);

    lintScaffold($files);
})->with([
    'no pattern' => [null, ['authors' => ['alice' => 3]], []],
    'no authors with posts' => ['/blog/author/{slug}', [], []],
    'a pattern without slug or ID' => ['/blog/authors', ['authors' => ['alice' => 3]], ['urls.author_pattern has neither {slug} nor {id}, so the author archives got no route.']],
    'no source database' => ['/blog/author/{slug}', ['reachable' => false, 'postCounts' => []], ['The author archives got no route: their slugs are read from the source database, which could not be opened.']],
    'too many authors' => ['/blog/author/{slug}', ['authors' => array_combine(array_map(fn (int $i): string => 'author-'.$i, range(1, 501)), range(1, 501))], ['The author archives got no route: 501 authors are too many to list in the controller. Use {id} in urls.author_pattern, or find them by a column of your own.']],
]);

it('leaves out the route of the author archives when it clashes', function () {
    config()->set('wp-migrator.urls.author_pattern', '/blog/{slug}');

    $scaffold = scaffold([], ['authors' => ['alice' => 3]]);

    expect($scaffold->files()['routes/wp.php'])->not->toContain('wp.author')
        ->and($scaffold->notes())->toBe(['The route for wp.author is the same as the one for wp.post.show, so it was left out; give it a URL pattern of its own.']);
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
        ->toContain("<div class=\"hero_title\">{{ \$attrs['hero_title'] ?? '' }}</div>")
        ->not->toContain('@foreach')
        ->and($files['resources/views/vendor/wp-migrator/blocks/elementor/acme-card.blade.php'])
        ->toContain('used once')
        ->toContain('$attrs holds its settings.')
        ->toContain('@foreach ($attrs as $key => $value)')
        ->and($files['resources/views/vendor/wp-migrator/blocks/acme/teaser.blade.php'])->toContain('{!! $content !!}')
        ->and($files['resources/views/vendor/wp-migrator/blocks/latest-posts.blade.php'])->toContain('The block "core/latest-posts", used 2 times.');
});

it('keeps names from the old site from turning into code', function () {
    $evil = "x'); system('id'); //";
    config()->set('wp-migrator.urls.patterns', ['page' => '/{path}', 'post' => "/blog's/{slug}", '*' => '/{type}/{slug}']);
    config()->set('wp-migrator.urls.term_patterns', ['genre' => '/genre/{slug}', "genre'); system('id" => '/g/{slug}']);

    $scaffold = scaffold(
        [
            'templates' => [
                new Template('index.php', 'index'),
                new Template("archive.php --}} @php system('id') @endphp", 'archive'),
                new Template('template-wide.php', 'custom', "Wide --}} @php system('id') @endphp", null, ['page']),
                new Template('template-blank.php', 'custom', '!!!', null, ['page']),
                new Template("tpl --}} @php system('id') @endphp.php", 'custom', 'Other', null, ['page']),
                new Template('page-contact.php', 'page', null, '../../evil'),
            ],
            'postTypes' => [
                new PostTypeDefinition('book', 'Books {{ system("id") }} @php echo 1; @endphp', true, true, false, null, [], 'functions.php'),
                new PostTypeDefinition($evil, 'Films', true, true, false, null, [], 'functions.php'),
            ],
            'taxonomies' => [new TaxonomyDefinition("genre'); system('id", 'Genres', ['book'], true, null, 'functions.php')],
            'menuLocations' => ['primary" :x="system(\'id\')' => 'Main', 'footer --}}{{ system("id") }}' => 'Footer'],
        ],
        ['authors' => ["x' => system('id'), 'y" => 1, 'alice' => 2], 'taxonomies' => ["genre'); system('id" => 1], 'blocks' => [
            'elementor/acme-hero' => ['count' => 1, 'post_ids' => [1]],
            "acme/teaser'); system('id') ?>" => ['count' => 1, 'post_ids' => [2]],
        ]],
        ['elementor/acme-hero' => ['hero_title' => 'text', "a'] ?? ''; system('id'); //" => 'text']],
    );
    $files = $scaffold->files();

    lintScaffold($files);

    // Compiling resolves the generated components, so they have to be views.
    $root = sys_get_temp_dir().'/wp-migrator-hostile-'.uniqid();

    foreach ($files as $path => $content) {
        File::ensureDirectoryExists(dirname($root.'/'.$path));
        File::put($root.'/'.$path, $content);
    }

    View::addLocation($root.'/resources/views');

    foreach ($files as $path => $content) {
        if (str_ends_with($path, '.blade.php')) {
            preg_match_all('/<\?php.*?\?>/s', Blade::compileString($content), $blocks);
            $code = implode("\n", $blocks[0]);

            expect(str_contains($code, 'system('))->toBeFalse($path)
                ->and(str_contains($code, 'echo 1'))->toBeFalse($path);
        } else {
            $calls = array_filter(token_get_all($content), fn (mixed $token): bool => is_array($token) && $token[0] === T_STRING && $token[1] === 'system');

            expect($calls)->toBe([], $path);
        }
    }

    File::deleteDirectory($root);

    expect($scaffold->notes())
        ->toContain('The post type "x\'); system(\'id\'); //" was left out: its name cannot be used in code safely.')
        ->toContain('The taxonomy "genre\'); system(\'id" was left out: its name cannot be used in code safely.')
        ->toContain('The block "acme/teaser\'); system(\'id\') ?>" was left out: its name cannot be used in code safely.')
        ->toContain('The menu location "primary" :x="system(\'id\')" was left out: its name cannot be used in code safely.')
        ->toContain('The author "x\' => system(\'id\'), \'y" was left out: its name cannot be used in code safely.')
        ->and($files['app/Http/Controllers/WpController.php'])->toContain("private const array AUTHORS = [\n        'alice' => 2,\n    ];")
        ->and($files['routes/wp.php'])->not->toContain('system')->toContain("Route::get('/blog\\'s/{slug}'")
        ->and($files['resources/views/books/index.blade.php'])->toContain("@section('title')\n    Books &#123;&#123; system(&quot;id&quot;) &#125;&#125; &#64;php echo 1; &#64;endphp\n@endsection")
        ->and($files['resources/views/components/wp/header.blade.php'])->toContain('<x-wp.menu location="primary" />')
        ->and($files['resources/views/components/wp/footer.blade.php'])->not->toContain('x-wp.menu')
        ->and($files['resources/views/vendor/wp-migrator/blocks/elementor/acme-hero.blade.php'])->not->toContain('system')
        ->and(array_filter(array_keys($files), fn (string $path): bool => str_contains($path, 'evil') || str_ends_with($path, '/.blade.php')))->toBe([]);
});
