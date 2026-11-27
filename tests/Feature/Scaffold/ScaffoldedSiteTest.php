<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use ItsJustVita\WpMigrator\Import\ImportMap;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Inventory\Frontend;
use ItsJustVita\WpMigrator\Inventory\SiteFacts;
use ItsJustVita\WpMigrator\Inventory\Theme\PostTypeDefinition;
use ItsJustVita\WpMigrator\Inventory\Theme\Template;
use ItsJustVita\WpMigrator\Inventory\Theme\ThemeInfo;
use ItsJustVita\WpMigrator\Inventory\Theme\ThemeInventory;
use ItsJustVita\WpMigrator\Models\Menu;
use ItsJustVita\WpMigrator\Models\MenuItem;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Models\Term;
use ItsJustVita\WpMigrator\Scaffold\Scaffold;
use ItsJustVita\WpMigrator\Tests\Support\User;

/*
 * Generates a frontend, loads the generated controller, URL helper, routes
 * and views into the test application and requests its pages. The classes
 * can be declared once per process, so everything runs in one test.
 */

function scaffoldedPost(string $type, string $title, string $slug, array $attributes = []): Post
{
    return Post::create([
        'type' => $type, 'status' => 'publish', 'title' => $title, 'slug' => $slug,
        'blocks' => [['name' => 'core/paragraph', 'attrs' => [], 'innerHTML' => '<p>Body of '.$title.'</p>', 'innerBlocks' => []]],
        'published_at' => CarbonImmutable::parse('2026-01-15 10:00'),
        ...$attributes,
    ]);
}

it('serves the imported content through the generated code', function () {
    $root = sys_get_temp_dir().'/wp-migrator-scaffold-'.uniqid();
    config()->set('wp-migrator.urls.patterns', ['page' => '/{path}', 'post' => '/blog/{slug}', 'event' => '/events/{id}']);

    $theme = new ThemeInventory(
        theme: new ThemeInfo('acme', '/themes/acme', 'Acme'),
        child: null,
        isBlockTheme: false,
        templates: [
            new Template('index.php', 'index'),
            new Template('page.php', 'page'),
            new Template('single.php', 'single', 'post'),
            new Template('archive-event.php', 'archive', 'event'),
            new Template('page-contact.php', 'page', null, 'contact'),
            new Template('template-wide.php', 'custom', 'Wide', null, ['page']),
            new Template('search.php', 'search'),
            new Template('404.php', 'not-found'),
            new Template('author.php', 'author'),
        ],
        templateParts: [],
        postTypes: [new PostTypeDefinition('event', 'Events', true, true, false, null, [], 'functions.php')],
        taxonomies: [],
        shortcodes: [],
        menuLocations: ['primary' => 'Main', 'footer' => 'Footer'],
        sidebars: [],
        blockTypes: [],
        scripts: [],
        styles: [],
        features: [],
        warnings: [],
    );
    $site = new SiteFacts(
        reachable: true,
        postCounts: ['page' => ['publish' => 5], 'post' => ['publish' => 2], 'event' => ['publish' => 1]],
        taxonomies: ['category' => 1],
        blocks: ['elementor/acme-hero' => ['count' => 1, 'post_ids' => [9]]],
        frontPage: 'welcome',
        authors: ['alice' => 5, 'ghost' => 99],
    );

    $scaffold = new Scaffold(new Frontend($theme, $site, config()), ['elementor/acme-hero' => ['hero_title' => 'text', 'color' => 'value']]);

    foreach ($scaffold->files() as $path => $content) {
        File::ensureDirectoryExists(dirname($root.'/'.$path));
        File::put($root.'/'.$path, $content);
    }

    file_put_contents($root.'/resources/views/pages/contact.blade.php', 'CONTACT VIEW {{ $post->title }}');

    // The web middleware encrypts the session cookie.
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    require_once $root.'/app/Http/Controllers/WpController.php';
    require_once $root.'/app/Support/WpUrl.php';
    // The route a new application has for "/"; the routes loaded after it replace it.
    Route::middleware('web')->get('/', fn (): string => 'LARAVEL WELCOME');
    Route::middleware('web')->group($root.'/routes/wp.php');
    app('router')->getRoutes()->refreshNameLookups();
    View::addLocation($root.'/resources/views');
    // Laravel looks for error views in the configured view paths, as resources/views is in an application.
    config()->set('view.paths', [$root.'/resources/views', ...config('view.paths')]);
    View::prependNamespace('wp-migrator', $root.'/resources/views/vendor/wp-migrator');

    scaffoldedPost('page', 'Welcome', 'welcome');
    $about = scaffoldedPost('page', 'About us', 'about');
    scaffoldedPost('page', 'Team', 'team', ['parent_id' => $about->id, 'template' => 'template-wide.php']);
    scaffoldedPost('page', 'Contact', 'contact');
    scaffoldedPost('page', 'Büro', 'b%c3%bcro');
    $alice = User::create(['name' => 'Alice <A>', 'email' => 'alice@example.com', 'password' => 'secret']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret']);
    app(ImportMap::class)->record(SourceType::User, 5, $alice, 'x');
    $hello = scaffoldedPost('post', 'Hello world', 'hello', ['author_id' => $alice->id, 'blocks' => [
        ['name' => 'elementor/acme-hero', 'attrs' => ['hero_title' => 'Big <em>news</em>', 'color' => 'red'], 'innerHTML' => '', 'innerBlocks' => []],
    ]]);
    scaffoldedPost('post', 'By someone else', 'by-bob', ['author_id' => $bob->id]);
    scaffoldedPost('post', 'Not ready', 'not-ready', ['status' => 'draft', 'author_id' => $alice->id]);
    $gig = scaffoldedPost('event', 'Summer gig', 'summer-gig');
    scaffoldedPost('lead', 'Hello from a form entry', 'lead-1');
    app(ImportMap::class)->record(SourceType::Post, 77, $gig, 'x');
    $news = Term::create(['taxonomy' => 'category', 'name' => 'News', 'slug' => 'news']);
    $hello->terms()->attach($news);
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'locations' => ['primary']]);
    $parent = MenuItem::create(['menu_id' => $menu->id, 'title' => 'About us', 'url' => '/about', 'order' => 1]);
    MenuItem::create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'title' => 'Team', 'url' => '/about/team', 'order' => 1]);

    $this->get('/')->assertOk()->assertSee('<h1>Welcome</h1>', false)->assertSee('<title>Welcome</title>', false)->assertDontSee('LARAVEL WELCOME')
        ->assertSee('<a href="/about" >About us</a>', false)->assertSee('<a href="/about/team" >Team</a>', false);
    $this->get('/about/team')->assertOk()->assertSee('<main class="template-wide">', false)->assertSee('Body of Team');
    $this->get('/about')->assertOk()->assertSee('<main class="">', false);
    $this->get('/team')->assertNotFound();
    $this->get('/contact')->assertOk()->assertSee('CONTACT VIEW Contact');
    $this->get('/b%C3%BCro')->assertOk()->assertSee('Büro');
    $this->get('/blog')->assertOk()->assertSee('href="'.url('/blog/hello').'"', false);
    $this->get('/blog/hello')->assertOk()->assertSee('<div class="hero_title">Big &lt;em&gt;news&lt;/em&gt;</div>', false)->assertDontSee('red');
    $this->get('/blog/not-ready')->assertNotFound();
    $this->get('/events')->assertOk()->assertSee('href="'.url('/events/77').'"', false);
    expect($this->get('/events')->getContent())->toMatch('#<title>\s*Events\s*</title>#');
    $this->get('/events/77')->assertOk()->assertSee('Summer gig')->assertSee('<time datetime=', false);
    $this->get('/events/78')->assertNotFound();
    $this->get('/blog/category/news')->assertOk()->assertSee('<h1>News</h1>', false)->assertSee('Hello world');
    $this->get('/search?q=hello')->assertOk()->assertSee('Hello world')->assertDontSee('Summer gig')->assertDontSee('form entry');
    $this->get('/search')->assertOk()->assertSee('Nothing found.');
    $this->get('/blog/author/alice')->assertOk()->assertSee('<h1>Alice &lt;A&gt;</h1>', false)->assertSee('<title>Alice &lt;A&gt;</title>', false)
        ->assertSee('Hello world')->assertDontSee('By someone else')->assertDontSee('Not ready')->assertDontSee('Summer gig');
    $this->get('/blog/author/ghost')->assertNotFound();
    $this->get('/blog/author/nobody')->assertNotFound();
    $this->get('/nowhere/at-all')->assertNotFound()->assertSee('Page not found');

    File::deleteDirectory($root);
});
