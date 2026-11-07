<?php

declare(strict_types=1);

use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use ItsJustVita\WpMigrator\Source\Like;
use ItsJustVita\WpMigrator\Source\WpDatabase;

it('resolves the configured connection and table prefix', function () {
    $db = app(WpDatabase::class);

    expect($db->connectionName())->toBe('wordpress')
        ->and($db->prefix())->toBe('wp_')
        ->and($db->tableName('posts'))->toBe('wp_posts');
});

it('queries prefixed tables on the source connection', function () {
    $this->source()->post(['post_title' => 'Hello']);

    $db = app(WpDatabase::class);

    expect($db->table('posts')->count())->toBe(1)
        ->and($db->hasTable('posts'))->toBeTrue()
        ->and($db->hasTable('nope'))->toBeFalse();
});

it('reads options and returns null for missing ones', function () {
    $this->source()->option('home', 'https://old.example.com');

    $db = app(WpDatabase::class);

    expect($db->option('home'))->toBe('https://old.example.com')
        ->and($db->option('missing'))->toBeNull();
});

it('excludes revisions and auto-drafts from content posts', function () {
    $site = $this->source();
    $site->post(['post_title' => 'Keep']);
    $site->post(['post_title' => 'Revision', 'post_type' => 'revision']);
    $site->post(['post_title' => 'Draft', 'post_status' => 'auto-draft']);

    expect(app(WpDatabase::class)->posts()->count())->toBe(1);
});

it('pings the connection and reports the server version', function () {
    $this->source();

    $db = app(WpDatabase::class);
    $db->ping();

    expect($db->serverVersion())->not->toBeEmpty();
});

it('fails to ping an unreachable connection', function () {
    config()->set('database.connections.wordpress', ['driver' => 'sqlite', 'database' => '/nonexistent/wordpress.sqlite']);
    app('db')->purge('wordpress');

    expect(fn () => app(WpDatabase::class)->ping())->toThrow(SQLiteDatabaseDoesNotExistException::class);
});

it('matches substrings literally so like wildcards in the needle are plain characters', function () {
    $site = $this->source();
    $site->post(['post_content' => 'has [et_pb_section] inside']);
    $site->post(['post_content' => 'has [etXpbX] inside']);
    $site->post(['post_content' => '100% plain']);

    $db = app(WpDatabase::class);

    expect(Like::contains($db->posts(), 'post_content', '[et_pb_')->count())->toBe(1)
        ->and(Like::contains($db->posts(), 'post_content', '100%')->count())->toBe(1)
        ->and(Like::contains($db->posts(), 'post_content', '[etXpbX]')->count())->toBe(1)
        ->and(Like::contains($db->posts(), 'post_content', 'nowhere')->count())->toBe(0);
});

it('resolves the site timezone from its options', function (array $options, string $expected) {
    $site = $this->source();

    foreach ($options as $name => $value) {
        $site->option($name, $value);
    }

    expect(app(WpDatabase::class)->timezone())->toBe($expected);
})->with([
    'named zone wins' => [['timezone_string' => 'Europe/Berlin', 'gmt_offset' => '5'], 'Europe/Berlin'],
    'whole offset' => [['timezone_string' => '', 'gmt_offset' => '2'], '+02:00'],
    'negative offset' => [['gmt_offset' => '-5'], '-05:00'],
    'fractional offset' => [['gmt_offset' => '5.5'], '+05:30'],
    'nothing set' => [[], 'UTC'],
]);
