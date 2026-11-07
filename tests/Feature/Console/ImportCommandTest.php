<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use ItsJustVita\WpMigrator\Facades\WpMigrator;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Mapping\PostMapper;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;

final class ThrowingPostMapper implements PostMapper
{
    public function map(SourcePost $post, ImportContext $context): ?Model
    {
        throw new RuntimeException('Cannot map this post');
    }
}

beforeEach(function () {
    $this->reports = sys_get_temp_dir().'/wp-migrator-reports-'.uniqid();
    config()->set('wp-migrator.report.directory', $this->reports);
    config()->set('wp-migrator.media.download_missing', false);
});

afterEach(function () {
    File::deleteDirectory($this->reports);
});

it('runs the import, prints a summary and writes the report', function () {
    $this->source()->post(['post_title' => 'Hello']);

    $this->artisan('wp:import')
        ->expectsOutputToContain('Report written to')
        ->assertSuccessful();

    $files = glob($this->reports.'/wp-migrator-*.md') ?: [];

    expect(Post::count())->toBe(1)
        ->and($files)->toHaveCount(1)
        ->and(file_get_contents($files[0]))->toContain('| posts | 1 | 0 | 0 | 0 | 0 |');
});

it('passes the options on to the import', function () {
    $site = $this->source();
    $site->post(['post_title' => 'Old', 'post_modified_gmt' => '2025-06-01 00:00:00']);
    $site->post(['post_title' => 'An event', 'post_type' => 'event']);
    $site->post(['post_title' => 'A post']);

    $this->artisan('wp:import', ['--only' => 'posts', '--type' => 'event,post', '--since' => '2026-01-01'])
        ->assertSuccessful();

    expect(Post::query()->orderBy('id')->pluck('title')->all())->toBe(['An event', 'A post']);
});

it('writes nothing in a dry run', function () {
    $this->source()->post(['post_title' => 'Hello']);

    $this->artisan('wp:import', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(Post::count())->toBe(0)
        ->and(glob($this->reports.'/wp-migrator-*.md') ?: [])->toHaveCount(1);
});

it('rejects unknown importers', function () {
    $this->source();

    $this->artisan('wp:import', ['--only' => 'posts,widgets'])
        ->expectsOutputToContain('Unknown importer "widgets"')
        ->assertFailed();
});

it('says which importers are not available yet', function () {
    $this->source();

    $this->artisan('wp:import', ['--only' => 'menus,comments'])
        ->expectsOutputToContain('menus, comments: not available yet')
        ->assertSuccessful();
});

it('rejects an invalid since date', function () {
    $this->source();

    $this->artisan('wp:import', ['--since' => 'someday'])
        ->expectsOutputToContain('--since')
        ->assertFailed();
});

it('fails when the source connection cannot be opened', function () {
    config()->set('database.connections.wordpress', ['driver' => 'sqlite', 'database' => '/nonexistent/wordpress.sqlite']);
    app('db')->purge('wordpress');

    $this->artisan('wp:import')
        ->expectsOutputToContain('does not exist')
        ->assertFailed();
});

it('fails when rows could not be imported', function () {
    $site = $this->source();
    $site->post(['post_title' => 'Hello']);
    $site->post(['post_title' => 'Broken', 'post_type' => 'broken']);
    WpMigrator::mapPostType('broken', ThrowingPostMapper::class);

    $this->artisan('wp:import', ['--only' => 'posts'])
        ->expectsOutputToContain('1 row failed')
        ->assertFailed();

    expect(Post::count())->toBe(1);
});
