<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Import\ImportMap;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Models\ImportMapEntry;
use ItsJustVita\WpMigrator\Models\Post;

function somePost(): Post
{
    return Post::create(['type' => 'post', 'status' => 'publish', 'title' => 'A', 'slug' => 'a']);
}

it('records where a source row went and finds it again', function () {
    $post = somePost();
    $map = app(ImportMap::class);

    $entry = $map->record(SourceType::Post, 42, $post, 'abc');

    expect($entry->target?->is($post))->toBeTrue()
        ->and($entry->imported_at)->not->toBeNull()
        ->and($map->find(SourceType::Post, 42)?->target_id)->toBe($post->id)
        ->and($map->find(SourceType::Post, 43))->toBeNull()
        ->and($map->targetId(SourceType::Post, 42))->toBe($post->id)
        ->and($map->targetId(SourceType::Term, 42))->toBeNull();
});

it('tells whether a source row is unchanged since the last import', function () {
    $map = app(ImportMap::class);
    $map->record(SourceType::Post, 1, somePost(), ImportMap::checksum(['title' => 'a']));

    expect($map->isUnchanged(SourceType::Post, 1, ImportMap::checksum(['title' => 'a'])))->toBeTrue()
        ->and($map->isUnchanged(SourceType::Post, 1, ImportMap::checksum(['title' => 'b'])))->toBeFalse()
        ->and($map->isUnchanged(SourceType::Post, 2, ImportMap::checksum(['title' => 'a'])))->toBeFalse();
});

it('updates the existing entry when a row is imported again', function () {
    $map = app(ImportMap::class);
    $post = somePost();

    $map->record(SourceType::Post, 1, $post, 'v1');
    $map->record(SourceType::Post, 1, $post, 'v2');

    expect(ImportMapEntry::count())->toBe(1)
        ->and($map->find(SourceType::Post, 1)?->checksum)->toBe('v2');
});

it('builds checksums that ignore key order but notice value changes', function () {
    expect(ImportMap::checksum(['a' => 1, 'b' => ['c' => 2, 'd' => 3]]))
        ->toBe(ImportMap::checksum(['b' => ['d' => 3, 'c' => 2], 'a' => 1]))
        ->and(ImportMap::checksum(['a' => 1]))->not->toBe(ImportMap::checksum(['a' => 2]))
        ->and(ImportMap::checksum(['a' => 1]))->not->toBe(ImportMap::checksum(['a' => '1']));
});
