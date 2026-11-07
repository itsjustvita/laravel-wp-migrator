<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use ItsJustVita\WpMigrator\Models\ImportMapEntry;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Models\Menu;
use ItsJustVita\WpMigrator\Models\MenuItem;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Models\Redirect;
use ItsJustVita\WpMigrator\Models\SeoMeta;
use ItsJustVita\WpMigrator\Models\Term;

it('prefixes model tables with the configured prefix', function () {
    config()->set('wp-migrator.target.table_prefix', 'cms_');

    expect((new Post)->getTable())->toBe('cms_posts')
        ->and((new ImportMapEntry)->getTable())->toBe('cms_wp_import_map')
        ->and((new SeoMeta)->getTable())->toBe('cms_seo_meta')
        ->and((new MenuItem)->getTable())->toBe('cms_menu_items');
});

it('stores a post with its parent, media, terms, seo meta and comments', function () {
    $media = Media::create(['disk' => 'public', 'path' => 'uploads/2026/01/hero.jpg', 'mime_type' => 'image/jpeg']);
    $parent = Post::create(['type' => 'page', 'status' => 'publish', 'title' => 'Parent', 'slug' => 'parent']);
    $post = Post::create([
        'type' => 'page',
        'status' => 'publish',
        'title' => 'Child',
        'slug' => 'child',
        'parent_id' => $parent->id,
        'featured_media_id' => $media->id,
        'blocks' => [['name' => 'core/paragraph', 'attrs' => []]],
        'fields' => ['subtitle' => 'Hello'],
        'published_at' => '2026-01-15 10:00:00',
    ]);
    $term = Term::create(['taxonomy' => 'category', 'name' => 'News', 'slug' => 'news']);
    $post->terms()->attach($term);
    $post->seo()->create(['title' => 'SEO title', 'robots' => 'index,follow']);
    $post->comments()->create(['author_name' => 'Visitor', 'content' => 'Hi', 'status' => 'approved']);

    $post->refresh();

    expect($post->parent?->is($parent))->toBeTrue()
        ->and($parent->children)->toHaveCount(1)
        ->and($post->featuredMedia?->is($media))->toBeTrue()
        ->and($post->terms->first()?->is($term))->toBeTrue()
        ->and($post->seo?->title)->toBe('SEO title')
        ->and($post->comments)->toHaveCount(1)
        ->and($post->blocks)->toBe([['name' => 'core/paragraph', 'attrs' => []]])
        ->and($post->fields)->toBe(['subtitle' => 'Hello'])
        ->and($post->published_at)->toBeInstanceOf(Carbon::class);
});

it('nests terms and lists the posts attached to them', function () {
    $parent = Term::create(['taxonomy' => 'category', 'name' => 'Parent', 'slug' => 'parent']);
    $child = Term::create(['taxonomy' => 'category', 'name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id]);
    $post = Post::create(['type' => 'post', 'status' => 'publish', 'title' => 'A', 'slug' => 'a']);
    $post->terms()->attach($child);

    expect($parent->children->first()?->is($child))->toBeTrue()
        ->and($child->parent?->is($parent))->toBeTrue()
        ->and($child->posts->first()?->is($post))->toBeTrue();
});

it('stores menus with nested items that can link to models', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'locations' => ['primary']]);
    $page = Post::create(['type' => 'page', 'status' => 'publish', 'title' => 'About', 'slug' => 'about']);

    $top = $menu->items()->create(['title' => 'Home', 'url' => '/', 'order' => 0, 'classes' => ['home']]);
    $child = $menu->items()->make(['title' => 'About', 'order' => 1, 'target' => '_blank']);
    $child->parent()->associate($top);
    $child->linkable()->associate($page);
    $child->save();

    $menu->refresh();

    expect($menu->items)->toHaveCount(2)
        ->and($menu->locations)->toBe(['primary'])
        ->and($top->children->first()?->linkable?->is($page))->toBeTrue()
        ->and($top->classes)->toBe(['home']);
});

it('stores redirects with sensible defaults', function () {
    $redirect = Redirect::create(['from_path' => '/2024/01/old-post', 'to_path' => '/blog/old-post']);

    $redirect->refresh();

    expect($redirect->status_code)->toBe(301)
        ->and($redirect->hits)->toBe(0)
        ->and($redirect->last_hit_at)->toBeNull();
});

it('registers short morph aliases so apps that enforce a morph map keep working', function () {
    expect((new Post)->getMorphClass())->toBe('wp_migrator_post')
        ->and((new Media)->getMorphClass())->toBe('wp_migrator_media')
        ->and((new Term)->getMorphClass())->toBe('wp_migrator_term');
});

it('gives media the url of its file and sizes', function () {
    Storage::fake('public');
    $media = Media::create(['disk' => 'public', 'path' => 'a/b.jpg', 'variants' => [
        'medium' => ['path' => 'a/b-300x200.jpg', 'width' => 300, 'height' => 200, 'mime_type' => 'image/jpeg'],
        'broken' => 'nope',
    ]]);

    expect($media->url())->toEndWith('/a/b.jpg')
        ->and($media->url('medium'))->toEndWith('/a/b-300x200.jpg')
        ->and($media->url('missing'))->toEndWith('/a/b.jpg')
        ->and($media->url('broken'))->toEndWith('/a/b.jpg')
        ->and($media->variantPath('medium'))->toBe('a/b-300x200.jpg')
        ->and($media->variantPath('missing'))->toBeNull();
});
