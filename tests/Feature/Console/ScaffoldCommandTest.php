<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use ItsJustVita\WpMigrator\Models\Post;

beforeEach(function () {
    $this->originalBase = base_path();
    $this->root = sys_get_temp_dir().'/wp-migrator-app-'.uniqid();
    File::ensureDirectoryExists($this->root.'/routes');
    file_put_contents($this->root.'/routes/web.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/', function () {\n    return view('welcome');\n});\n");
    $this->app->setBasePath($this->root);

    $site = $this->source();
    $site->option('home', 'https://old.example.com');
    $site->post(['post_title' => 'Hello']);
    $page = $site->page(['post_title' => 'Landing']);
    $site->postMeta($page, '_elementor_edit_mode', 'builder');
    $site->postMeta($page, '_elementor_data', json_encode([['elType' => 'widget', 'widgetType' => 'acme-hero', 'settings' => []]]));
});

afterEach(function () {
    $this->app->setBasePath($this->originalBase);
    File::deleteDirectory($this->root);
});

function scaffoldTheme(): string
{
    return (string) realpath(__DIR__.'/../../Fixtures/themes/acme-classic');
}

it('writes the frontend and loads its routes', function () {
    expect(Artisan::call('wp:scaffold', ['--theme' => scaffoldTheme()]))->toBe(0);
    $output = Artisan::output();

    expect($output)
        ->toMatch('/routes\/wp\.php \.+ created/')
        ->toMatch('/app\/Http\/Controllers\/WpController\.php \.+ created/')
        ->toMatch('/resources\/views\/vendor\/wp-migrator\/blocks\/elementor\/acme-hero\.blade\.php[ .]*created/')
        ->toMatch('/routes\/web\.php \.+ loads routes\/wp\.php/')
        ->toContain('routes/web.php has its own route for "/", which comes first; remove it to show the front page.')
        ->toContain('The frontend is scaffolded.')
        ->and(file_get_contents($this->root.'/routes/web.php'))->toEndWith("\nrequire __DIR__.'/wp.php';\n")
        ->and(file_exists($this->root.'/resources/views/layouts/wp.blade.php'))->toBeTrue()
        ->and(file_exists($this->root.'/resources/views/pages/contact.blade.php'))->toBeTrue();
});

it('keeps existing files unless forced', function () {
    Artisan::call('wp:scaffold', ['--theme' => scaffoldTheme()]);
    file_put_contents($this->root.'/resources/views/pages/show.blade.php', 'mine');
    $web = file_get_contents($this->root.'/routes/web.php');

    Artisan::call('wp:scaffold', ['--theme' => scaffoldTheme()]);

    expect(Artisan::output())->toMatch('/resources\/views\/pages\/show\.blade\.php \.+ exists, kept/')
        ->not->toContain('loads routes/wp.php')
        ->and(file_get_contents($this->root.'/resources/views/pages/show.blade.php'))->toBe('mine')
        ->and(file_get_contents($this->root.'/routes/web.php'))->toBe($web);

    Artisan::call('wp:scaffold', ['--theme' => scaffoldTheme(), '--force' => true]);

    expect(Artisan::output())->toMatch('/resources\/views\/pages\/show\.blade\.php \.+ replaced/')
        ->and(file_get_contents($this->root.'/resources/views/pages/show.blade.php'))->not->toBe('mine');
});

it('says when there is no routes/web.php', function () {
    unlink($this->root.'/routes/web.php');

    Artisan::call('wp:scaffold', ['--theme' => scaffoldTheme()]);

    expect(Artisan::output())->toContain('There is no routes/web.php; load routes/wp.php yourself.')
        ->and(file_exists($this->root.'/routes/web.php'))->toBeFalse();
});

it('lists the settings the imported widgets hold', function () {
    Post::create(['type' => 'page', 'status' => 'publish', 'title' => 'Landing', 'slug' => 'landing', 'blocks' => [
        ['name' => 'core/group', 'attrs' => [], 'innerHTML' => '', 'innerBlocks' => [
            ['name' => 'elementor/acme-hero', 'attrs' => ['hero_title' => 'Big news', 'color' => 'red', '_margin' => ['top' => 1]], 'innerHTML' => '', 'innerBlocks' => []],
        ]],
        ['name' => 'elementor/acme-hero', 'attrs' => ['hero_title' => 'x', 'cards' => [['a' => 1]], 'link' => ['url' => '/']], 'innerHTML' => '', 'innerBlocks' => []],
        ['name' => 'elementor/other', 'attrs' => ['ignored' => 'yes'], 'innerHTML' => '', 'innerBlocks' => []],
    ]]);

    Artisan::call('wp:scaffold', ['--theme' => scaffoldTheme()]);

    expect(file_get_contents($this->root.'/resources/views/vendor/wp-migrator/blocks/elementor/acme-hero.blade.php'))
        ->toContain('$attrs holds its settings: cards (list), color, hero_title, link (object).')
        ->toContain("<div class=\"hero_title\">{!! \$attrs['hero_title'] ?? '' !!}</div>")
        ->not->toContain('_margin');
});

it('fails without a theme to scan', function () {
    expect(Artisan::call('wp:scaffold'))->toBe(1)
        ->and(Artisan::output())->toContain('--theme')
        ->and(file_exists($this->root.'/routes/wp.php'))->toBeFalse();
});
