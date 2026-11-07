<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;
use ItsJustVita\WpMigrator\Source\Readers\PostFilter;
use ItsJustVita\WpMigrator\Source\Readers\PostReader;

it('reads posts with their meta and terms', function () {
    $site = $this->source();
    $category = $site->term('category', 'News');
    $tag = $site->term('post_tag', 'php');
    $author = $site->user(['user_login' => 'alice']);
    $id = $site->post([
        'post_title' => 'Hello',
        'post_name' => 'hello',
        'post_content' => '<p>Hi</p>',
        'post_excerpt' => 'Short',
        'post_author' => $author,
        'post_date' => '2026-01-15 11:00:00',
        'post_date_gmt' => '2026-01-15 10:00:00',
        'post_modified' => '2026-01-16 11:00:00',
        'post_modified_gmt' => '2026-01-16 10:00:00',
        'menu_order' => 3,
        'guid' => 'https://old.example.com/?p=1',
    ]);
    $site->postMeta($id, '_thumbnail_id', '99');
    $site->postMeta($id, 'multi', 'one');
    $site->postMeta($id, 'multi', 'two');
    $site->attachTerm($id, $category);
    $site->attachTerm($id, $tag);

    $posts = app(PostReader::class)->byIds([$id]);

    expect($posts)->toHaveCount(1);

    $post = $posts[0];

    expect($post)->toBeInstanceOf(SourcePost::class)
        ->and($post->id)->toBe($id)
        ->and($post->type)->toBe('post')
        ->and($post->status)->toBe('publish')
        ->and($post->title)->toBe('Hello')
        ->and($post->slug)->toBe('hello')
        ->and($post->content)->toBe('<p>Hi</p>')
        ->and($post->excerpt)->toBe('Short')
        ->and($post->authorId)->toBe($author)
        ->and($post->parentId)->toBeNull()
        ->and($post->menuOrder)->toBe(3)
        ->and($post->guid)->toBe('https://old.example.com/?p=1')
        ->and($post->publishedAt?->toIso8601String())->toBe('2026-01-15T10:00:00+00:00')
        ->and($post->modifiedAt?->toIso8601String())->toBe('2026-01-16T10:00:00+00:00')
        ->and($post->meta->get('_thumbnail_id'))->toBe('99')
        ->and($post->meta->all('multi'))->toBe(['one', 'two'])
        ->and($post->meta->get('missing'))->toBeNull()
        ->and($post->terms)->toHaveCount(2)
        ->and($post->terms[0]->taxonomy)->toBe('category')
        ->and($post->terms[0]->name)->toBe('News')
        ->and($post->terms[0]->id)->toBe($category)
        ->and($post->terms[1]->slug)->toBe('php');
});

it('decodes url-encoded slugs and reads draft dates in the site timezone', function () {
    $site = $this->source();
    $site->option('timezone_string', 'Europe/Berlin');
    $id = $site->post([
        'post_title' => 'Entwurf',
        'post_name' => 'entw%c3%bcrfe',
        'post_status' => 'draft',
        'post_date' => '2026-01-15 11:00:00',
        'post_date_gmt' => '0000-00-00 00:00:00',
    ]);

    $post = app(PostReader::class)->byIds([$id])[0];

    expect($post->slug)->toBe('entwürfe')
        ->and($post->publishedAt?->toIso8601String())->toBe('2026-01-15T11:00:00+01:00');
});

it('groups post ids by hierarchy depth so parents come first', function () {
    $site = $this->source();
    $parent = $site->page(['post_title' => 'Parent']);
    $orphan = $site->page(['post_title' => 'Orphan', 'post_parent' => 9999]);
    $child = $site->page(['post_title' => 'Child', 'post_parent' => $parent]);
    $grandchild = $site->page(['post_title' => 'Grandchild', 'post_parent' => $child]);
    $site->post(['post_title' => 'Not a page']);

    $levels = app(PostReader::class)->idsByDepth(new PostFilter(types: ['page']));

    expect($levels)->toBe([[$parent, $orphan], [$child], [$grandchild]]);
});

it('filters by type, status and modification date', function () {
    $site = $this->source();
    $a = $site->post(['post_title' => 'A']);
    $b = $site->post(['post_title' => 'B', 'post_status' => 'draft']);
    $c = $site->page(['post_title' => 'C']);
    $site->post(['post_title' => 'Trashed', 'post_status' => 'trash']);
    $site->post(['post_title' => 'Auto', 'post_status' => 'auto-draft']);
    $old = $site->post(['post_title' => 'Old', 'post_modified_gmt' => '2025-12-01 00:00:00']);
    $site->attachment();
    $site->post(['post_type' => 'revision']);
    $site->post(['post_type' => 'nav_menu_item']);
    $site->post(['post_type' => 'acf-field-group']);

    $reader = app(PostReader::class);
    $flat = fn (PostFilter $filter): array => array_merge(...$reader->idsByDepth($filter) ?: [[]]);

    expect($flat(new PostFilter))->toBe([$a, $b, $c, $old])
        ->and($flat(new PostFilter(types: ['page'])))->toBe([$c])
        ->and($flat(new PostFilter(since: CarbonImmutable::parse('2026-01-01'))))->toBe([$a, $b, $c])
        ->and($flat(new PostFilter(statuses: ['draft'])))->toBe([$b])
        ->and($reader->count(new PostFilter))->toBe(4);
});
