<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Mapping\DefaultPostMapper;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Models\Term;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;
use ItsJustVita\WpMigrator\Source\Dto\SourceTerm;
use ItsJustVita\WpMigrator\Tests\Support\User;

function sourcePost(array $overrides = [], array $meta = [], array $terms = []): SourcePost
{
    $values = array_merge([
        'id' => 42,
        'type' => 'page',
        'status' => 'publish',
        'title' => 'About us',
        'slug' => 'about-us',
        'content' => '<p>Hello</p>',
        'excerpt' => 'Short',
        'authorId' => null,
        'parentId' => null,
        'menuOrder' => 2,
        'mimeType' => null,
        'guid' => null,
        'publishedAt' => CarbonImmutable::parse('2026-01-15 10:00:00'),
        'modifiedAt' => CarbonImmutable::parse('2026-01-16 10:00:00'),
        'meta' => new MetaBag($meta),
        'terms' => $terms,
    ], $overrides);

    return new SourcePost(...$values);
}

it('maps a post with its attributes and resolves references through the import map', function () {
    $context = importContext('posts');
    $parent = Post::create(['type' => 'page', 'status' => 'publish', 'title' => 'Parent', 'slug' => 'parent']);
    $media = Media::create(['disk' => 'public', 'path' => 'x.jpg']);
    $author = User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);
    $term = Term::create(['taxonomy' => 'category', 'name' => 'News', 'slug' => 'news']);
    $context->map->record(SourceType::Post, 1, $parent, 'x');
    $context->map->record(SourceType::Post, 9, $media, 'x');
    $context->map->record(SourceType::User, 3, $author, 'x');
    $context->map->record(SourceType::Term, 5, $term, 'x');

    $post = app(DefaultPostMapper::class)->map(sourcePost(
        ['parentId' => 1, 'authorId' => 3],
        ['_thumbnail_id' => ['9'], '_wp_page_template' => ['templates/wide.php'], 'subtitle' => ['Sub'], 'colors' => ['a:2:{i:0;s:3:"red";i:1;s:4:"blue";}'], 'multi' => ['one', 'two'], '_edit_lock' => ['123']],
        [new SourceTerm(5, 5, 'category', 'News', 'news', '', null, MetaBag::empty())],
    ), $context);

    expect($post)->toBeInstanceOf(Post::class)
        ->and($post->type)->toBe('page')
        ->and($post->status)->toBe('publish')
        ->and($post->title)->toBe('About us')
        ->and($post->slug)->toBe('about-us')
        ->and($post->excerpt)->toBe('Short')
        ->and($post->content_raw)->toBe('<p>Hello</p>')
        ->and($post->template)->toBe('templates/wide.php')
        ->and($post->parent_id)->toBe($parent->id)
        ->and($post->menu_order)->toBe(2)
        ->and($post->author_id)->toBe($author->id)
        ->and($post->featured_media_id)->toBe($media->id)
        ->and($post->published_at?->toIso8601String())->toBe('2026-01-15T10:00:00+00:00')
        ->and($post->created_at?->toIso8601String())->toBe('2026-01-15T10:00:00+00:00')
        ->and($post->updated_at?->toIso8601String())->toBe('2026-01-16T10:00:00+00:00')
        ->and($post->fields)->toBe(['subtitle' => 'Sub', 'colors' => ['red', 'blue'], 'multi' => ['one', 'two']])
        ->and($post->terms->pluck('id')->all())->toBe([$term->id])
        ->and($context->report->unresolvedReferences())->toBe([]);
});

it('reports references it cannot resolve and leaves them empty', function () {
    $context = importContext('posts');

    $post = app(DefaultPostMapper::class)->map(sourcePost(
        ['parentId' => 1, 'authorId' => 3],
        ['_thumbnail_id' => ['9']],
        [new SourceTerm(5, 5, 'category', 'News', 'news', '', null, MetaBag::empty())],
    ), $context);

    expect($post->parent_id)->toBeNull()
        ->and($post->author_id)->toBeNull()
        ->and($post->featured_media_id)->toBeNull()
        ->and($post->terms)->toHaveCount(0)
        ->and(array_map(fn (array $r) => $r['kind'].' '.$r['reference'], $context->report->unresolvedReferences()))
        ->toBe(['parent post 1', 'author 3', 'featured media 9', 'term 5']);
});

it('keeps drafts unpublished, derives missing slugs and ignores the default template', function () {
    $post = app(DefaultPostMapper::class)->map(sourcePost(
        ['status' => 'draft', 'slug' => '', 'title' => 'Mein Entwurf'],
        ['_wp_page_template' => ['default']],
    ), importContext('posts'));

    expect($post->published_at)->toBeNull()
        ->and($post->slug)->toBe('mein-entwurf')
        ->and($post->template)->toBeNull();
});

it('keeps malformed serialized meta as a string and warns', function () {
    $context = importContext('posts');

    $post = app(DefaultPostMapper::class)->map(sourcePost([], ['broken' => ['a:1:{s:1:"a";']]), $context);

    expect($post->fields)->toBe(['broken' => 'a:1:{s:1:"a";'])
        ->and($context->report->warnings()[0]['message'])->toContain('broken');
});

it('copies allow-listed internal keys and skips excluded ones', function () {
    config()->set('wp-migrator.fields.allowed_internal_keys', ['_price']);
    config()->set('wp-migrator.fields.excluded_keys', ['rank_math_title']);

    $post = app(DefaultPostMapper::class)->map(sourcePost([], [
        '_price' => ['9.90'],
        '_hidden' => ['x'],
        'rank_math_title' => ['SEO'],
        'visible' => ['yes'],
    ]), importContext('posts'));

    expect($post->fields)->toBe(['_price' => '9.90', 'visible' => 'yes']);
});

it('updates the post it created before and syncs its terms', function () {
    $context = importContext('posts');
    $mapper = app(DefaultPostMapper::class);
    $news = Term::create(['taxonomy' => 'category', 'name' => 'News', 'slug' => 'news']);
    $tech = Term::create(['taxonomy' => 'category', 'name' => 'Tech', 'slug' => 'tech']);
    $context->map->record(SourceType::Term, 5, $news, 'x');
    $context->map->record(SourceType::Term, 6, $tech, 'x');
    $termRef = fn (int $id) => new SourceTerm($id, $id, 'category', 'T', 't', '', null, MetaBag::empty());

    $first = $mapper->map(sourcePost(['title' => 'v1'], [], [$termRef(5)]), $context);
    $context->map->record(SourceType::Post, 42, $first, 'x');
    $second = $mapper->map(sourcePost(['title' => 'v2'], [], [$termRef(6)]), $context);

    expect($second->is($first))->toBeTrue()
        ->and(Post::count())->toBe(1)
        ->and($second->title)->toBe('v2')
        ->and($second->terms()->pluck('slug')->all())->toBe(['tech']);
});

it('does not accept a thumbnail id that points at something other than media', function () {
    $context = importContext('posts');
    $other = Post::create(['type' => 'post', 'status' => 'publish', 'title' => 'Not an image', 'slug' => 'x']);
    Media::create(['id' => $other->id, 'disk' => 'public', 'path' => 'unrelated.jpg']);
    $context->map->record(SourceType::Post, 9, $other, 'x');

    $post = app(DefaultPostMapper::class)->map(sourcePost([], ['_thumbnail_id' => ['9']]), $context);

    expect($post->featured_media_id)->toBeNull()
        ->and($context->report->unresolvedReferences()[0]['kind'])->toBe('featured media');
});

it('does not accept a parent that was mapped onto another model', function () {
    $context = importContext('posts');
    $media = Media::create(['disk' => 'public', 'path' => 'x.jpg']);
    Post::create(['id' => $media->id, 'type' => 'post', 'status' => 'publish', 'title' => 'Unrelated', 'slug' => 'y']);
    $context->map->record(SourceType::Post, 1, $media, 'x');

    $post = app(DefaultPostMapper::class)->map(sourcePost(['parentId' => 1]), $context);

    expect($post->parent_id)->toBeNull()
        ->and($context->report->unresolvedReferences()[0]['kind'])->toBe('parent post');
});
