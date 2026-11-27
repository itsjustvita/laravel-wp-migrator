<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
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

it('counts the suspicious content in the summary', function () {
    $this->source()->post(['post_content' => '<script>x()</script><iframe src="https://casino.example/"></iframe>']);

    Artisan::call('wp:import', ['--only' => 'posts']);

    expect(Artisan::output())->toMatch('/Suspicious content \.+ 2/');

    $this->source()->table('posts')->update(['post_content' => 'Clean now']);
    Artisan::call('wp:import', ['--only' => 'posts']);

    expect(Artisan::output())->toMatch('/Suspicious content \.+ 0/');
});

it('asks before it prunes and removes what is gone from the old site', function () {
    $site = $this->source();
    $gone = $site->post(['post_title' => 'Gone', 'post_author' => 0]);
    $site->post(['post_title' => 'Kept', 'post_author' => 0]);
    $this->artisan('wp:import')->assertSuccessful();
    $site->table('posts')->where('ID', $gone)->delete();

    $this->artisan('wp:import', ['--prune' => true])
        ->expectsOutputToContain('Gone from the old site, and about to be removed here:')
        ->expectsConfirmation('Remove them from this application?', 'no')
        ->expectsOutputToContain('Nothing is removed. In a script, pass --no-interaction to prune without the question.')
        ->assertSuccessful();

    expect(Post::query()->pluck('title')->all())->toBe(['Gone', 'Kept']);

    $this->artisan('wp:import', ['--prune' => true])
        ->expectsConfirmation('Remove them from this application?', 'yes')
        ->doesntExpectOutputToContain('Nothing is removed.')
        ->assertSuccessful();

    expect(Post::query()->pluck('title')->all())->toBe(['Kept']);
});

it('prunes without asking when it cannot ask', function () {
    $site = $this->source();
    $gone = $site->post(['post_title' => 'Gone', 'post_author' => 0]);
    $site->post(['post_title' => 'Kept', 'post_author' => 0]);
    Artisan::call('wp:import');
    $site->table('posts')->where('ID', $gone)->delete();

    expect(Artisan::call('wp:import', ['--prune' => true, '--no-interaction' => true]))->toBe(0);

    expect(Artisan::output())
        ->toContain('Gone from the old site, and about to be removed here:')
        ->toMatch('/Posts[ .]+1\s/')
        ->toMatch('/Removed[ .]+1\s/')
        ->not->toContain('Remove them from this application?')
        ->and(Post::query()->pluck('title')->all())->toBe(['Kept']);
});

it('shows in a dry run what pruning would remove', function () {
    $site = $this->source();
    $gone = $site->post(['post_title' => 'Gone', 'post_author' => 0]);
    Artisan::call('wp:import');
    $site->table('posts')->where('ID', $gone)->delete();

    expect(Artisan::call('wp:import', ['--prune' => true, '--dry-run' => true]))->toBe(0);

    $files = glob($this->reports.'/wp-migrator-*.md') ?: [];

    expect(Artisan::output())
        ->toMatch('/Would be removed[ .]+1\s/')
        ->not->toContain('about to be removed')
        ->and(Post::count())->toBe(1)
        ->and(implode('', array_map(file_get_contents(...), $files)))->toContain('## Would be removed (1)');
});

it('mentions removals only when pruning', function () {
    $this->source()->post(['post_title' => 'Hello', 'post_author' => 0]);

    Artisan::call('wp:import');
    expect(Artisan::output())->not->toContain('Removed');

    Artisan::call('wp:import', ['--prune' => true]);
    expect(Artisan::output())->toMatch('/Removed[ .]+0\s/')->not->toContain('Gone from the old site');
});

it('prunes only when the whole site is imported', function (array $options) {
    $site = $this->source();
    $gone = $site->post(['post_title' => 'Gone', 'post_author' => 0]);
    Artisan::call('wp:import');
    $site->table('posts')->where('ID', $gone)->delete();

    $this->artisan('wp:import', ['--prune' => true, ...$options])
        ->expectsOutputToContain('--prune compares the whole site with what was imported. Run it without --only and --type.')
        ->assertFailed();

    expect(Post::count())->toBe(1);
})->with([
    'only' => [['--only' => 'posts']],
    'type' => [['--type' => 'post']],
]);
