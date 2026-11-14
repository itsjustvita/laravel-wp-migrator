<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use ItsJustVita\WpMigrator\Tests\Support\SourceSite;

function inventoryTheme(string $name): string
{
    return (string) realpath(__DIR__.'/../../Fixtures/themes/'.$name);
}

beforeEach(function () {
    $this->plan = sys_get_temp_dir().'/wp-migrator-plan-'.uniqid().'/wp-migration-plan.md';
});

afterEach(function () {
    File::deleteDirectory(dirname($this->plan));
});

function seedInventorySite(SourceSite $site): void
{
    $site->option('home', 'https://old.example.com');
    $site->option('blogname', 'Acme');
    $site->option('permalink_structure', '/%postname%/');
    $site->option('stylesheet', 'acme-child');
    $site->option('template', 'acme-classic');
    $site->option('active_plugins', serialize([
        'wordpress-seo/wp-seo.php',
        'contact-form-7/wp-contact-form-7.php',
        'elementor/elementor.php',
        'acme-custom-stuff/acme.php',
    ]));
    $site->option('sidebars_widgets', serialize(['sidebar-1' => ['search-2', 'recent-posts-2', 'text-3'], 'wp_inactive_widgets' => ['text-9']]));

    $site->post(['post_title' => 'Hello', 'post_content' => 'Click [button url="/x"]here[/button] or [button]', 'post_author' => 0]);
    $site->post(['post_title' => 'Gallery', 'post_content' => '[gallery ids="1"] [contact-form-7 id="4"]', 'post_author' => 0]);
    $about = $site->page(['post_title' => 'About', 'post_author' => 0, 'post_content' => '<!-- wp:acme/hero {"title":"Hi"} /--><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->']);
    $site->postMeta($about, '_wp_page_template', 'template-wide.php');
    $team = $site->page(['post_title' => 'Team', 'post_author' => 0]);
    $site->postMeta($team, '_wp_page_template', 'template-wide.php');
    $site->post(['post_type' => 'event', 'post_title' => 'Gig', 'post_author' => 0]);
    $landing = $site->page(['post_title' => 'Landing', 'post_author' => 0]);
    $site->postMeta($landing, '_elementor_data', '[{"id":"x"}]');

    $menu = $site->menu('Main Menu', 'main');
    $site->menuLocations(['primary' => $menu], 'acme-child');
    $site->menuItem($menu, ['title' => 'Home', 'url' => '/']);
}

it('writes the migration plan', function () {
    seedInventorySite($this->source());

    $this->artisan('wp:inventory', ['--theme' => inventoryTheme('acme-classic'), '--child' => inventoryTheme('acme-child'), '--output' => $this->plan])
        ->expectsOutputToContain('Migration plan written to')
        ->assertSuccessful();

    $plan = file_get_contents($this->plan);

    expect($plan)
        ->toContain('# WP migration plan')
        ->toContain('"Acme Classic" 1.2.0 with the child theme "Acme Child" 0.3.0')
        ->toContain('## Routes and views')
        ->toContain('- [ ] **Front page**')
        ->toContain('`resources/views/events/show.blade.php`')
        ->toContain('`/event/{slug}`')
        ->toContain('from `single-event.php` (child theme)')
        ->toContain('**Page template "Wide Layout"**')
        ->toContain('used by 2 pages: About, Team')
        ->toContain('## Components')
        ->toContain('`<x-header>`')
        ->toContain('Menu location `primary` (Primary Menu)')
        ->toContain('"Main Menu"')
        ->toContain('"Blog Sidebar" (`sidebar-1`) held 3 widgets')
        ->toContain('## Shortcodes and blocks')
        ->toContain('- [ ] Shortcode `[button]`')
        ->toContain('used 2 times')
        ->toContain('Shortcode `[contact-form-7]`')
        ->toContain('Block `acme/hero`')
        ->toContain('resources/views/vendor/wp-migrator/blocks/acme/hero.blade.php')
        ->toContain('## Plugins')
        ->toContain('Contact Form 7')
        ->toContain('acme-custom-stuff')
        ->toContain('## Assets')
        ->toContain('`acme-app`')
        ->toContain('Vite')
        ->toContain('## Unsupported content')
        ->toContain('**Elementor**')
        ->not->toContain('[gallery]');
});

it('refuses to overwrite a plan unless forced', function () {
    seedInventorySite($this->source());
    File::ensureDirectoryExists(dirname($this->plan));
    file_put_contents($this->plan, 'my ticked boxes');

    $this->artisan('wp:inventory', ['--theme' => inventoryTheme('acme-classic'), '--output' => $this->plan])
        ->expectsOutputToContain('--force')
        ->assertFailed();

    expect(file_get_contents($this->plan))->toBe('my ticked boxes');

    $this->artisan('wp:inventory', ['--theme' => inventoryTheme('acme-classic'), '--output' => $this->plan, '--force' => true])
        ->assertSuccessful();

    expect(file_get_contents($this->plan))->toContain('# WP migration plan');
});

it('finds the active theme next to the uploads directory', function () {
    seedInventorySite($this->source());
    $root = sys_get_temp_dir().'/wp-migrator-site-'.uniqid();
    File::copyDirectory(inventoryTheme('acme-classic'), $root.'/wp-content/themes/acme-classic');
    File::copyDirectory(inventoryTheme('acme-child'), $root.'/wp-content/themes/acme-child');
    File::ensureDirectoryExists($root.'/wp-content/uploads');
    config()->set('wp-migrator.source.uploads_path', $root.'/wp-content/uploads/');

    $this->artisan('wp:inventory', ['--output' => $this->plan])
        ->expectsOutputToContain('acme-child')
        ->assertSuccessful();

    expect(file_get_contents($this->plan))->toContain('with the child theme "Acme Child"');

    File::deleteDirectory($root);
});

it('finds the active child theme next to the given theme', function () {
    seedInventorySite($this->source());

    $this->artisan('wp:inventory', ['--theme' => inventoryTheme('acme-classic'), '--output' => $this->plan])
        ->expectsOutputToContain('acme-child')
        ->assertSuccessful();

    expect(file_get_contents($this->plan))->toContain('with the child theme "Acme Child"');
});

it('shows what the scan could not read', function () {
    seedInventorySite($this->source());

    $this->artisan('wp:inventory', ['--theme' => inventoryTheme('acme-classic'), '--output' => $this->plan])
        ->expectsOutputToContain('Could not read inc/broken.php')
        ->assertSuccessful();
});

it('fails when the theme directory does not exist', function () {
    seedInventorySite($this->source());

    $this->artisan('wp:inventory', ['--theme' => '/nonexistent/theme', '--output' => $this->plan])
        ->expectsOutputToContain('does not exist')
        ->assertFailed();

    expect(file_exists($this->plan))->toBeFalse();
});

it('never takes a path from the theme options', function () {
    seedInventorySite($this->source());
    $root = sys_get_temp_dir().'/wp-migrator-site-'.uniqid();
    File::copyDirectory(inventoryTheme('acme-classic'), $root.'/elsewhere');
    File::ensureDirectoryExists($root.'/wp-content/themes');
    File::ensureDirectoryExists($root.'/wp-content/uploads');
    config()->set('wp-migrator.source.uploads_path', $root.'/wp-content/uploads');
    $this->source()->option('template', '../../elsewhere');

    $this->artisan('wp:inventory', ['--output' => $this->plan])
        ->expectsOutputToContain('--theme')
        ->assertFailed();

    File::deleteDirectory($root);
});

it('asks for the theme when it cannot find it', function () {
    seedInventorySite($this->source());

    $this->artisan('wp:inventory', ['--output' => $this->plan])
        ->expectsOutputToContain('--theme')
        ->assertFailed();
});

it('writes a plan from the theme alone when the source database is not reachable', function () {
    config()->set('database.connections.wordpress', ['driver' => 'sqlite', 'database' => '/nonexistent/wordpress.sqlite']);
    app('db')->purge('wordpress');

    $this->artisan('wp:inventory', ['--theme' => inventoryTheme('acme-classic'), '--output' => $this->plan])
        ->expectsOutputToContain('source database')
        ->assertSuccessful();

    expect(file_get_contents($this->plan))
        ->toContain('- [ ] Shortcode `[button]`')
        ->toContain('The source database could not be read');
});

it('writes to docs/wp-migration-plan.md by default', function () {
    seedInventorySite($this->source());
    $default = base_path('docs/wp-migration-plan.md');
    File::delete($default);

    $this->artisan('wp:inventory', ['--theme' => inventoryTheme('acme-classic')])->assertSuccessful();

    expect(file_exists($default))->toBeTrue();

    File::deleteDirectory(base_path('docs'));
});
