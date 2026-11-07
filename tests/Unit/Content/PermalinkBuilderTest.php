<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Content\Urls\PermalinkBuilder;
use ItsJustVita\WpMigrator\Content\Urls\PostReference;

function postRef(array $overrides = []): PostReference
{
    return new PostReference(...array_merge([
        'id' => 42,
        'type' => 'post',
        'status' => 'publish',
        'slug' => 'hello',
        'path' => 'hello',
        'date' => '2026-01-05 09:08:07',
        'author' => 'alice',
        'category' => 'news/tech',
    ], $overrides));
}

it('builds post paths from the permalink structure', function (string $structure, string $expected) {
    expect((new PermalinkBuilder($structure))->path(postRef()))->toBe($expected);
})->with([
    'name' => ['/%postname%/', '/hello'],
    'no trailing slash' => ['/%postname%', '/hello'],
    'date and name' => ['/%year%/%monthnum%/%day%/%postname%/', '/2026/01/05/hello'],
    'month and name' => ['/%year%/%monthnum%/%postname%/', '/2026/01/hello'],
    'time' => ['/%hour%/%minute%/%second%/%postname%/', '/09/08/07/hello'],
    'numeric' => ['/archives/%post_id%', '/archives/42'],
    'category' => ['/%category%/%postname%/', '/news/tech/hello'],
    'author' => ['/%author%/%postname%/', '/alice/hello'],
    'static prefix' => ['/blog/%postname%/', '/blog/hello'],
    'path info' => ['/index.php/%postname%/', '/index.php/hello'],
]);

it('keeps non-ascii slugs readable', function () {
    expect((new PermalinkBuilder('/%postname%/'))->path(postRef(['slug' => 'grüße', 'path' => 'grüße'])))->toBe('/grüße');
});

it('builds page paths from the hierarchy, whatever the structure says', function (string $structure) {
    $page = postRef(['type' => 'page', 'slug' => 'team', 'path' => 'company/team']);

    expect((new PermalinkBuilder($structure))->path($page))->toBe('/company/team');
})->with(['/%postname%/', '/blog/%year%/%postname%/']);

it('builds custom post type paths', function () {
    $event = postRef(['type' => 'event', 'slug' => 'concert', 'path' => 'concert']);
    $nested = postRef(['type' => 'event', 'slug' => 'child', 'path' => 'parent/child']);

    expect((new PermalinkBuilder('/%postname%/'))->path($event))->toBe('/event/concert')
        ->and((new PermalinkBuilder('/%postname%/', ['event' => 'veranstaltungen']))->path($event))->toBe('/veranstaltungen/concert')
        ->and((new PermalinkBuilder('/%postname%/', ['event' => '/a/b/']))->path($event))->toBe('/a/b/concert')
        ->and((new PermalinkBuilder('/%postname%/'))->path($nested))->toBe('/event/parent/child');
});

it('puts the static front of the structure before a guessed post type slug', function () {
    $event = postRef(['type' => 'event', 'slug' => 'concert', 'path' => 'concert']);

    expect((new PermalinkBuilder('/blog/%postname%/'))->path($event))->toBe('/blog/event/concert')
        ->and((new PermalinkBuilder('/blog/%year%/%postname%/'))->path($event))->toBe('/blog/event/concert')
        ->and((new PermalinkBuilder('/blog/%postname%/', ['event' => 'events']))->path($event))->toBe('/events/concert');
});

it('has no path when the structure cannot be filled', function (string $structure, array $overrides) {
    expect((new PermalinkBuilder($structure))->path(postRef($overrides)))->toBeNull();
})->with([
    'plain permalinks' => ['', []],
    'plain permalinks for a page' => ['', ['type' => 'page']],
    'unknown tag' => ['/%foo%/%postname%/', []],
    'no date' => ['/%year%/%postname%/', ['date' => null]],
    'zero date' => ['/%year%/%postname%/', ['date' => '0000-00-00 00:00:00']],
    'no category' => ['/%category%/%postname%/', ['category' => null]],
    'no author' => ['/%author%/%postname%/', ['author' => null]],
    'no slug' => ['/%postname%/', ['slug' => '', 'path' => '']],
]);

it('allows a numeric structure for a post without a slug', function () {
    expect((new PermalinkBuilder('/%post_id%/'))->path(postRef(['slug' => '', 'path' => ''])))->toBe('/42');
});
