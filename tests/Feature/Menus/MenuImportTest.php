<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Facades\WpMigrator;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\ImportReport;
use ItsJustVita\WpMigrator\Import\ImportRunner;
use ItsJustVita\WpMigrator\Mapping\MenuMapper;
use ItsJustVita\WpMigrator\Models\Menu;
use ItsJustVita\WpMigrator\Models\MenuItem;
use ItsJustVita\WpMigrator\Source\Dto\SourceMenu;

final class NoMenusMapper implements MenuMapper
{
    public function map(SourceMenu $menu, ImportContext $context): ?Model
    {
        return null;
    }
}

function importMenus(): ImportReport
{
    return app(ImportRunner::class)->run(new ImportOptions(only: ['posts', 'pages', 'terms', 'menus']));
}

/**
 * @return list<array<string, mixed>>
 */
function menuItems(string $slug): array
{
    return Menu::query()->where('slug', $slug)->sole()->items()->get()
        ->map(fn (MenuItem $item) => [
            'title' => $item->title,
            'url' => $item->url,
            'parent' => $item->parent?->title,
            'linkable' => $item->linkable === null ? null : class_basename($item->linkable).':'.($item->linkable->getAttribute('title') ?? $item->linkable->getAttribute('name')),
            'target' => $item->target,
            'classes' => $item->classes,
        ])->all();
}

beforeEach(function () {
    $site = $this->site = $this->source();
    $site->option('home', 'https://old.example.com');
    $site->option('permalink_structure', '/%postname%/');

    $this->about = $site->page(['post_title' => 'About us', 'post_name' => 'about', 'post_author' => 0]);
    $this->team = $site->page(['post_title' => 'Team', 'post_name' => 'team', 'post_parent' => $this->about, 'post_author' => 0]);
    $this->hello = $site->post(['post_title' => 'Hello', 'post_name' => 'hello', 'post_author' => 0]);
    $this->news = $site->term('category', 'News');
});

it('imports classic menus with their locations', function () {
    $main = $this->site->menu('Main Menu', 'main');
    $footer = $this->site->menu('Footer');
    $this->site->menu('Unused');
    $this->site->menuLocations(['primary' => $main, 'footer' => $footer, 'social' => 0]);

    importMenus();

    expect(Menu::query()->orderBy('id')->get(['name', 'slug', 'locations'])->toArray())->toBe([
        ['name' => 'Main Menu', 'slug' => 'main', 'locations' => ['primary']],
        ['name' => 'Footer', 'slug' => 'footer', 'locations' => ['footer']],
        ['name' => 'Unused', 'slug' => 'unused', 'locations' => null],
    ]);
});

it('imports menu items in order, nested, with their targets', function () {
    $main = $this->site->menu('Main', 'main');
    $this->site->menuItem($main, ['title' => 'Home', 'order' => 1, 'url' => 'https://old.example.com/']);
    $about = $this->site->menuItem($main, ['order' => 2, 'type' => 'post_type', 'object' => 'page', 'object_id' => $this->about]);
    $this->site->menuItem($main, ['title' => 'Our team', 'order' => 3, 'parent' => $about, 'type' => 'post_type', 'object' => 'page', 'object_id' => $this->team]);
    $this->site->menuItem($main, ['order' => 4, 'type' => 'taxonomy', 'object' => 'category', 'object_id' => $this->news]);
    $this->site->menuItem($main, ['title' => 'Blog post', 'order' => 5, 'type' => 'post_type', 'object' => 'post', 'object_id' => $this->hello]);
    $this->site->menuItem($main, ['title' => 'Partner', 'order' => 6, 'url' => 'https://example.net/', 'target' => '_blank', 'classes' => ['external', '', 'highlight']]);
    $this->site->menuItem($main, ['title' => 'Old link', 'order' => 7, 'url' => 'https://old.example.com/hello/']);

    $report = importMenus();

    expect(menuItems('main'))->toBe([
        ['title' => 'Home', 'url' => '/', 'parent' => null, 'linkable' => null, 'target' => null, 'classes' => null],
        ['title' => 'About us', 'url' => '/about', 'parent' => null, 'linkable' => 'Post:About us', 'target' => null, 'classes' => null],
        ['title' => 'Our team', 'url' => '/about/team', 'parent' => 'About us', 'linkable' => 'Post:Team', 'target' => null, 'classes' => null],
        ['title' => 'News', 'url' => '/blog/category/news', 'parent' => null, 'linkable' => 'Term:News', 'target' => null, 'classes' => null],
        ['title' => 'Blog post', 'url' => '/blog/hello', 'parent' => null, 'linkable' => 'Post:Hello', 'target' => null, 'classes' => null],
        ['title' => 'Partner', 'url' => 'https://example.net/', 'parent' => null, 'linkable' => null, 'target' => '_blank', 'classes' => ['external', 'highlight']],
        ['title' => 'Old link', 'url' => '/blog/hello', 'parent' => null, 'linkable' => null, 'target' => null, 'classes' => null],
    ])->and($report->unresolvedReferences())->toBe([])
        ->and($report->counts()['menus']['created'])->toBe(1)
        ->and($report->counts()['menu items']['created'])->toBe(7);
});

it('imports a child before it would need its parent', function () {
    $main = $this->site->menu('Main', 'main');
    $child = $this->site->menuItem($main, ['title' => 'Child', 'order' => 2, 'url' => '/child/']);
    $parent = $this->site->menuItem($main, ['title' => 'Parent', 'order' => 1, 'url' => '/parent/']);
    $this->site->table('postmeta')->where('post_id', $child)->where('meta_key', '_menu_item_menu_item_parent')->update(['meta_value' => (string) $parent]);

    importMenus();

    expect(MenuItem::query()->where('title', 'Child')->sole()->parent?->title)->toBe('Parent');
});

it('reports links to content that was not imported and keeps the item', function () {
    $draft = $this->site->page(['post_title' => 'Secret', 'post_status' => 'trash', 'post_author' => 0]);
    $main = $this->site->menu('Main', 'main');
    $this->site->menuItem($main, ['title' => 'Gone', 'type' => 'post_type', 'object' => 'page', 'object_id' => $draft]);

    $report = importMenus();

    expect(menuItems('main'))->toBe([['title' => 'Gone', 'url' => null, 'parent' => null, 'linkable' => null, 'target' => null, 'classes' => null]])
        ->and(array_column($report->unresolvedReferences(), 'kind'))->toBe(['menu link']);
});

it('warns about links to post type archives', function () {
    $main = $this->site->menu('Main', 'main');
    $this->site->menuItem($main, ['title' => 'Events', 'type' => 'post_type_archive', 'object' => 'event']);

    $report = importMenus();

    expect(MenuItem::query()->sole()->url)->toBeNull()
        ->and($report->warnings()[0]['message'])->toContain('archive');
});

it('leaves out drafts saved by the customizer', function () {
    $main = $this->site->menu('Main', 'main');
    $this->site->menuItem($main, ['title' => 'Draft', 'status' => 'draft', 'url' => '/x/']);

    importMenus();

    expect(MenuItem::count())->toBe(0);
});

it('is idempotent', function () {
    $main = $this->site->menu('Main', 'main');
    $this->site->menuLocations(['primary' => $main]);
    $this->site->menuItem($main, ['title' => 'Home', 'url' => '/']);

    importMenus();
    $second = importMenus();

    expect($second->counts()['menus'])->toMatchArray(['created' => 0, 'updated' => 0, 'unchanged' => 1])
        ->and($second->counts()['menu items'])->toMatchArray(['created' => 0, 'updated' => 0, 'unchanged' => 1])
        ->and(Menu::count())->toBe(1)
        ->and(MenuItem::count())->toBe(1);
});

it('imports navigation menus of block themes', function () {
    $this->site->post([
        'post_type' => 'wp_navigation',
        'post_title' => 'Header navigation',
        'post_name' => 'header-navigation',
        'post_author' => 0,
        'post_content' => '<!-- wp:home-link {"label":"Start"} /-->'
            .'<!-- wp:navigation-link {"label":"About","type":"page","id":'.$this->about.',"url":"https://old.example.com/about/","kind":"post-type"} /-->'
            .'<!-- wp:navigation-submenu {"label":"More","url":"#","kind":"custom"} -->'
            .'<!-- wp:navigation-link {"label":"News","type":"category","id":'.$this->news.',"url":"https://old.example.com/category/news/","kind":"taxonomy"} /-->'
            .'<!-- wp:navigation-link {"label":"Partner","url":"https://example.net/","kind":"custom","opensInNewTab":true,"className":"ext"} /-->'
            .'<!-- /wp:navigation-submenu -->'
            .'<!-- wp:page-list /-->',
    ]);

    $report = importMenus();

    expect(menuItems('header-navigation'))->toBe([
        ['title' => 'Start', 'url' => '/', 'parent' => null, 'linkable' => null, 'target' => null, 'classes' => null],
        ['title' => 'About', 'url' => '/about', 'parent' => null, 'linkable' => 'Post:About us', 'target' => null, 'classes' => null],
        ['title' => 'More', 'url' => '#', 'parent' => null, 'linkable' => null, 'target' => null, 'classes' => null],
        ['title' => 'News', 'url' => '/blog/category/news', 'parent' => 'More', 'linkable' => 'Term:News', 'target' => null, 'classes' => null],
        ['title' => 'Partner', 'url' => 'https://example.net/', 'parent' => 'More', 'linkable' => null, 'target' => '_blank', 'classes' => ['ext']],
    ])->and(Menu::query()->sole()->name)->toBe('Header navigation')
        ->and($report->warnings()[0]['message'])->toContain('core/page-list');
});

it('replaces the items of a navigation menu when it changes', function () {
    $nav = $this->site->post(['post_type' => 'wp_navigation', 'post_title' => 'Nav', 'post_name' => 'nav', 'post_author' => 0,
        'post_content' => '<!-- wp:navigation-link {"label":"One","url":"/one/","kind":"custom"} /-->']);
    importMenus();
    $this->site->table('posts')->where('ID', $nav)->update([
        'post_content' => '<!-- wp:navigation-link {"label":"Two","url":"/two/","kind":"custom"} /-->',
        'post_modified_gmt' => '2026-02-01 00:00:00',
    ]);

    $report = importMenus();

    expect(MenuItem::query()->pluck('title')->all())->toBe(['Two'])
        ->and($report->counts()['navigation menus']['updated'])->toBe(1);
});

it('gives a navigation menu its own slug when a classic menu has it', function () {
    $this->site->menu('Main', 'main');
    $nav = $this->site->post(['post_type' => 'wp_navigation', 'post_title' => 'Main', 'post_name' => 'main', 'post_author' => 0, 'post_content' => '']);

    importMenus();

    expect(Menu::query()->orderBy('id')->pluck('slug')->all())->toBe(['main', 'main-'.$nav]);
});

it('skips the items of a menu that was not imported', function () {
    WpMigrator::mapMenus(NoMenusMapper::class);
    $main = $this->site->menu('Main', 'main');
    $this->site->menuItem($main, ['title' => 'Home', 'url' => '/']);

    $report = importMenus();

    expect(MenuItem::count())->toBe(0)
        ->and($report->counts()['menu items'])->toMatchArray(['skipped' => 1, 'failed' => 0])
        ->and($report->skippedItems()[1]['reason'])->toBe('its menu was not imported');
});

it('does not accept a parent that is not a menu item', function () {
    $main = $this->site->menu('Main', 'main');
    $this->site->menuItem($main, ['title' => 'Broken', 'url' => '/x/', 'parent' => $this->hello]);

    $report = importMenus();

    expect(MenuItem::query()->sole()->parent_id)->toBeNull()
        ->and(array_column($report->unresolvedReferences(), 'kind'))->toBe(['parent menu item']);
});
