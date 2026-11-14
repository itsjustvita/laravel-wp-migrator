<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use ItsJustVita\WpMigrator\Inventory\Theme\InventoryException;
use ItsJustVita\WpMigrator\Inventory\Theme\ThemeInventory;
use ItsJustVita\WpMigrator\Inventory\Theme\ThemeScanner;

function themePath(string $name): string
{
    return realpath(__DIR__.'/../../Fixtures/themes/'.$name) ?: '';
}

function scanTheme(string $name, ?string $child = null): ThemeInventory
{
    return app(ThemeScanner::class)->scan(themePath($name), $child === null ? null : themePath($child));
}

/**
 * A throwaway theme with the given files.
 *
 * @param  array<string, string>  $files
 */
function tempTheme(array $files): string
{
    $dir = sys_get_temp_dir().'/wp-migrator-theme-'.uniqid();

    foreach (['style.css' => "/*\nTheme Name: Temp\n*/\n", ...$files] as $path => $content) {
        File::ensureDirectoryExists(dirname($dir.'/'.$path));
        file_put_contents($dir.'/'.$path, $content);
    }

    return $dir;
}

beforeEach(function () {
    @unlink(sys_get_temp_dir().'/wp-migrator-theme-was-executed');
});

it('reads the theme header', function () {
    $theme = scanTheme('acme-classic')->theme;

    expect($theme->name)->toBe('Acme Classic')
        ->and($theme->version)->toBe('1.2.0')
        ->and($theme->textDomain)->toBe('acme')
        ->and($theme->parent)->toBeNull()
        ->and($theme->slug)->toBe('acme-classic');
});

it('finds the templates', function () {
    $templates = collect(scanTheme('acme-classic')->templates)->mapWithKeys(fn ($t) => [$t->path => $t->kind]);

    expect($templates->all())->toMatchArray([
        'front-page.php' => 'front-page',
        'single-event.php' => 'single',
        'archive-event.php' => 'archive',
        'page-contact.php' => 'page',
        'taxonomy-genre.php' => 'term',
        'template-wide.php' => 'custom',
        'header.php' => 'partial',
        'template-parts/content-event.php' => 'part',
        '404.php' => 'not-found',
    ])->and($templates->keys())->not->toContain('functions.php', 'inc/setup.php', 'vendor/acme/autoload.php');
});

it('finds post types with their settings', function () {
    $types = scanTheme('acme-classic')->postTypes;

    expect($types)->toHaveCount(1)
        ->and($types[0]->name)->toBe('event')
        ->and($types[0]->label)->toBe('Events')
        ->and($types[0]->public)->toBeTrue()
        ->and($types[0]->hasArchive)->toBeTrue()
        ->and($types[0]->hierarchical)->toBeFalse()
        ->and($types[0]->rewriteSlug)->toBe('veranstaltungen')
        ->and($types[0]->supports)->toBe(['title', 'editor', 'thumbnail'])
        ->and($types[0]->file)->toBe('functions.php');
});

it('finds taxonomies', function () {
    $taxonomies = scanTheme('acme-classic')->taxonomies;

    expect($taxonomies)->toHaveCount(1)
        ->and($taxonomies[0]->name)->toBe('genre')
        ->and($taxonomies[0]->label)->toBe('Genres')
        ->and($taxonomies[0]->postTypes)->toBe(['event'])
        ->and($taxonomies[0]->hierarchical)->toBeTrue()
        ->and($taxonomies[0]->rewriteSlug)->toBe('genres');
});

it('finds shortcodes, menu locations, sidebars, blocks and features', function () {
    $inventory = scanTheme('acme-classic');

    expect(collect($inventory->shortcodes)->mapWithKeys(fn ($s) => [$s->tag => $s->callback])->all())->toBe([
        'button' => 'acme_button_shortcode',
        'price' => 'a closure',
    ])
        ->and($inventory->menuLocations)->toBe([
            'primary' => 'Primary Menu',
            'footer' => 'Footer Menu',
            'social' => 'Social Links',
        ])
        ->and(collect($inventory->sidebars)->map(fn ($s) => [$s->id, $s->name])->all())->toBe([['sidebar-1', 'Blog Sidebar']])
        ->and(collect($inventory->blockTypes)->map(fn ($b) => [$b->name, $b->file])->all())->toBe([['acme/hero', 'blocks/hero/block.json']])
        ->and($inventory->features)->toBe(['post-thumbnails', 'custom-logo', 'woocommerce']);
});

it('finds scripts and styles', function () {
    $inventory = scanTheme('acme-classic');

    expect(collect($inventory->scripts)->map(fn ($a) => [$a->handle, $a->source, $a->dependencies])->all())->toBe([
        ['acme-app', '{theme}/assets/app.js', ['jquery']],
    ])->and(collect($inventory->styles)->map(fn ($a) => [$a->handle, $a->source])->all())->toBe([
        ['acme-style', '{stylesheet}/style.css'],
        ['acme-fonts', 'https://fonts.example.net/css?family=Inter'],
    ]);
});

it('finds template parts loaded by the templates', function () {
    expect(scanTheme('acme-classic')->templateParts)->toBe([
        'template-parts/content' => ['archive-event.php', 'index.php'],
        'template-parts/content-event' => ['single-event.php'],
    ]);
});

it('reports what it could not read', function () {
    $warnings = implode("\n", scanTheme('acme-classic')->warnings);

    expect($warnings)->toContain('inc/broken.php')
        ->toContain('register_post_type in functions.php')
        ->toContain('only known at runtime');
});

it('never runs theme code', function () {
    scanTheme('acme-classic');

    expect(file_exists(sys_get_temp_dir().'/wp-migrator-theme-was-executed'))->toBeFalse();
});

it('skips bundled dependencies', function () {
    $inventory = scanTheme('acme-classic');

    expect(collect($inventory->postTypes)->pluck('name')->all())->not->toContain('from-vendor')
        ->and(collect($inventory->shortcodes)->pluck('tag')->all())->not->toContain('from-node-modules');
});

it('merges a child theme into its parent', function () {
    $inventory = scanTheme('acme-classic', 'acme-child');
    $templates = collect($inventory->templates)->mapWithKeys(fn ($t) => [$t->path => $t->source]);

    expect($inventory->child?->name)->toBe('Acme Child')
        ->and($inventory->child?->parent)->toBe('acme-classic')
        ->and($templates['single-event.php'])->toBe('child')
        ->and($templates['page-team.php'])->toBe('child')
        ->and($templates['front-page.php'])->toBe('theme')
        ->and(collect($inventory->templates)->where('path', 'single-event.php'))->toHaveCount(1)
        ->and(collect($inventory->shortcodes)->pluck('tag')->all())->toBe(['button', 'price', 'child-only'])
        ->and(collect($inventory->styles)->pluck('handle')->all())->toContain('acme-child');
});

it('reads block themes', function () {
    $inventory = scanTheme('acme-block');
    $templates = collect($inventory->templates)->mapWithKeys(fn ($t) => [$t->path => [$t->kind, $t->subject]]);

    expect($inventory->isBlockTheme)->toBeTrue()
        ->and($templates->all())->toMatchArray([
            'templates/index.html' => ['index', null],
            'templates/single-event.html' => ['single', 'event'],
            'templates/page-wide.html' => ['custom', 'Wide page'],
            'parts/header.html' => ['part', 'header'],
            'parts/footer.html' => ['part', 'footer'],
        ]);
});

it('ignores files that link out of the theme', function () {
    $dir = sys_get_temp_dir().'/wp-migrator-theme-'.uniqid();
    $outside = sys_get_temp_dir().'/wp-migrator-outside-'.uniqid();
    File::copyDirectory(themePath('acme-block'), $dir);
    File::ensureDirectoryExists($outside);
    file_put_contents($outside.'/secret.php', "<?php\nregister_post_type('outside', []);\n");
    symlink($outside.'/secret.php', $dir.'/linked.php');
    symlink($outside, $dir.'/linked-dir');

    $inventory = app(ThemeScanner::class)->scan($dir);

    expect(collect($inventory->postTypes)->pluck('name')->all())->not->toContain('outside');

    File::deleteDirectory($dir);
    File::deleteDirectory($outside);
});

it('reads no block.json outside the theme', function () {
    $root = sys_get_temp_dir().'/wp-migrator-site-'.uniqid();
    File::copyDirectory(themePath('acme-block'), $root.'/theme');
    File::ensureDirectoryExists($root.'/outside');
    file_put_contents($root.'/outside/block.json', '{"name":"outside/secret"}');
    file_put_contents($root.'/theme/functions.php', "<?php\nregister_block_type(__DIR__.'/../outside');\n");

    $inventory = app(ThemeScanner::class)->scan($root.'/theme');

    expect($inventory->blockTypes)->toBe([])
        ->and($inventory->warnings)->toContain('register_block_type in functions.php points to {theme-dir}/../outside, which has no block.json in the theme.');

    File::deleteDirectory($root);
});

it('skips files that are too large to be templates', function () {
    $dir = sys_get_temp_dir().'/wp-migrator-theme-'.uniqid();
    File::copyDirectory(themePath('acme-block'), $dir);
    file_put_contents($dir.'/huge.php', "<?php\nregister_post_type('huge', []);\n".str_repeat('// padding'.PHP_EOL, 200000));

    $inventory = app(ThemeScanner::class)->scan($dir);

    expect(collect($inventory->postTypes)->pluck('name')->all())->not->toContain('huge')
        ->and(implode("\n", $inventory->warnings))->toContain('huge.php');

    File::deleteDirectory($dir);
});

it('refuses a path that is not a theme directory', function (string $path) {
    app(ThemeScanner::class)->scan($path);
})->with([
    'missing' => ['/nonexistent/theme'],
    'a file' => [__FILE__],
])->throws(InventoryException::class);

it('warns about a directory without a stylesheet header', function () {
    $dir = sys_get_temp_dir().'/wp-migrator-theme-'.uniqid();
    File::ensureDirectoryExists($dir);
    file_put_contents($dir.'/index.php', "<?php\n");

    $inventory = app(ThemeScanner::class)->scan($dir);

    expect($inventory->theme->name)->toBe(basename($dir))
        ->and(implode("\n", $inventory->warnings))->toContain('style.css');

    File::deleteDirectory($dir);
});

it('finds blocks by their name and by a block.json the code does not name', function () {
    $dir = tempTheme([
        'functions.php' => "<?php\nregister_block_type('temp/plain', ['render_callback' => 'temp_plain']);\nforeach (glob(__DIR__.'/blocks/*') as \$block) { register_block_type(\$block); }\n",
        'blocks/card/block.json' => '{"name":"temp/card"}',
    ]);

    expect(collect(app(ThemeScanner::class)->scan($dir)->blockTypes)->map(fn ($block) => [$block->name, $block->file])->all())
        ->toBe([['temp/card', 'blocks/card/block.json'], ['temp/plain', 'functions.php']]);

    File::deleteDirectory($dir);
});

it('follows block paths relative to included files, inside the theme', function () {
    $dir = tempTheme([
        'inc/blocks.php' => "<?php\nregister_block_type(__DIR__.'/../blocks/card');\n",
        'blocks/card/block.json' => '{"name":"temp/card"}',
    ]);

    $inventory = app(ThemeScanner::class)->scan($dir);

    expect(collect($inventory->blockTypes)->pluck('name')->all())->toBe(['temp/card'])
        ->and($inventory->warnings)->toBe([]);

    File::deleteDirectory($dir);
});

it('keeps the file of a script that is registered first and enqueued later', function () {
    $dir = tempTheme(['functions.php' => "<?php\nwp_register_script('temp-app', get_template_directory_uri().'/app.js', ['jquery']);\nwp_enqueue_script('temp-app');\n"]);

    expect(collect(app(ThemeScanner::class)->scan($dir)->scripts)->map(fn ($script) => [$script->handle, $script->source, $script->dependencies])->all())
        ->toBe([['temp-app', '{theme}/app.js', ['jquery']]]);

    File::deleteDirectory($dir);
});

it('reads taxonomies registered for a single post type', function () {
    $dir = tempTheme(['functions.php' => "<?php\nregister_taxonomy('topic', 'post', ['label' => 'Topics']);\n"]);

    expect(app(ThemeScanner::class)->scan($dir)->taxonomies[0]->postTypes)->toBe(['post']);

    File::deleteDirectory($dir);
});

it('numbers sidebars without an ID the way WP does', function () {
    $dir = tempTheme(['functions.php' => "<?php\nregister_sidebar(['name' => 'Main', 'id' => 'main']);\nregister_sidebar(['name' => 'Footer']);\n"]);

    $inventory = app(ThemeScanner::class)->scan($dir);

    expect(collect($inventory->sidebars)->map(fn ($sidebar) => [$sidebar->id, $sidebar->name])->all())->toBe([['main', 'Main'], ['sidebar-2', 'Footer']])
        ->and($inventory->warnings)->toBe(['The sidebar "Footer" in functions.php has no ID; WP numbers it by registration order, so "sidebar-2" is a guess.']);

    File::deleteDirectory($dir);
});

it('marks the warnings of the child theme', function () {
    $child = tempTheme(['broken.php' => "<?php\nfunction (\n"]);

    expect(app(ThemeScanner::class)->scan(themePath('acme-block'), $child)->warnings)
        ->toHaveCount(1)
        ->and(app(ThemeScanner::class)->scan(themePath('acme-block'), $child)->warnings[0])->toStartWith('Child theme: Could not read broken.php');

    File::deleteDirectory($child);
});

it('treats a block child theme of a classic theme as a block theme', function () {
    $child = tempTheme(['templates/index.html' => '<!-- wp:post-content /-->']);

    expect(app(ThemeScanner::class)->scan(themePath('acme-classic'), $child)->isBlockTheme)->toBeTrue()
        ->and(scanTheme('acme-classic')->isBlockTheme)->toBeFalse();

    File::deleteDirectory($child);
});

it('places templates of post types it only knows from the site', function () {
    $dir = tempTheme(['single-product-shirt.php' => '<?php']);

    $known = app(ThemeScanner::class)->scan($dir, null, ['product'])->template('single-product-shirt.php');
    $unknown = app(ThemeScanner::class)->scan($dir)->template('single-product-shirt.php');

    expect([$known?->subject, $known?->detail])->toBe(['product', 'shirt'])
        ->and([$unknown?->subject, $unknown?->detail])->toBe(['product-shirt', null]);

    File::deleteDirectory($dir);
});
