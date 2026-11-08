<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\ImportReport;
use ItsJustVita\WpMigrator\Import\ImportRunner;
use ItsJustVita\WpMigrator\Models\Comment;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Tests\Support\User;

function importComments(array $only = ['users', 'posts', 'comments']): ImportReport
{
    return app(ImportRunner::class)->run(new ImportOptions(only: $only));
}

beforeEach(function () {
    config()->set('wp-migrator.comments.enabled', true);
    $site = $this->site = $this->source();
    $this->alice = $site->user(['user_login' => 'alice', 'user_email' => 'alice@example.com']);
    $this->post = $site->post(['post_title' => 'Hello', 'post_author' => $this->alice]);
});

it('imports nothing unless comments are enabled', function () {
    config()->set('wp-migrator.comments.enabled', false);
    $this->site->comment($this->post);

    $report = importComments();

    expect(Comment::count())->toBe(0)
        ->and($report->counts())->not->toHaveKey('comments');
});

it('imports threaded comments with their author and status', function () {
    $first = $this->site->comment($this->post, [
        'comment_author' => 'Visitor',
        'comment_author_email' => 'visitor@example.com',
        'comment_author_url' => 'https://visitor.example',
        'comment_content' => 'First!',
        'comment_date_gmt' => '2026-01-20 08:00:00',
    ]);
    $reply = $this->site->comment($this->post, ['comment_parent' => $first, 'user_id' => $this->alice, 'comment_author' => 'Alice', 'comment_content' => 'Thanks.']);
    $this->site->comment($this->post, ['comment_approved' => '0', 'comment_content' => 'Waiting']);

    $report = importComments();

    $post = Post::query()->sole();
    $byContent = fn (string $content): Comment => Comment::query()->where('content', $content)->sole();

    expect(Comment::count())->toBe(3)
        ->and($byContent('First!')->commentable?->is($post))->toBeTrue()
        ->and($byContent('First!')->author_name)->toBe('Visitor')
        ->and($byContent('First!')->author_email)->toBe('visitor@example.com')
        ->and($byContent('First!')->author_url)->toBe('https://visitor.example')
        ->and($byContent('First!')->status)->toBe('approved')
        ->and($byContent('First!')->created_at?->toIso8601String())->toBe('2026-01-20T08:00:00+00:00')
        ->and($byContent('Thanks.')->parent?->is($byContent('First!')))->toBeTrue()
        ->and($byContent('Thanks.')->author_id)->toBe(User::query()->sole()->id)
        ->and($byContent('Waiting')->status)->toBe('pending')
        ->and($report->counts()['comments']['created'])->toBe(3)
        ->and($post->comments)->toHaveCount(3);
});

it('imports a reply before it would need its parent', function () {
    $reply = $this->site->comment($this->post, ['comment_content' => 'Reply']);
    $parent = $this->site->comment($this->post, ['comment_content' => 'Parent']);
    $this->site->table('comments')->where('comment_ID', $reply)->update(['comment_parent' => $parent]);

    importComments();

    expect(Comment::query()->where('content', 'Reply')->sole()->parent?->content)->toBe('Parent');
});

it('leaves out spam, trash, pingbacks and other comment types', function () {
    $this->site->comment($this->post, ['comment_approved' => 'spam']);
    $this->site->comment($this->post, ['comment_approved' => 'trash']);
    $this->site->comment($this->post, ['comment_type' => 'pingback']);
    $this->site->comment($this->post, ['comment_type' => 'review']);
    $this->site->comment($this->post, ['comment_type' => '', 'comment_content' => 'Old style']);

    importComments();

    expect(Comment::query()->pluck('content')->all())->toBe(['Old style']);
});

it('skips comments on posts that were not imported and imports them once the post is there', function () {
    $draft = $this->site->post(['post_title' => 'Later', 'post_status' => 'trash']);
    $this->site->comment($draft, ['comment_content' => 'Waiting for its post']);

    $first = importComments();
    $this->site->table('posts')->where('ID', $draft)->update(['post_status' => 'publish']);
    $second = importComments();

    expect($first->counts()['comments']['skipped'])->toBe(1)
        ->and($first->skippedItems()[0]['reason'])->toContain('not imported')
        ->and($second->counts()['comments']['created'])->toBe(1)
        ->and(Comment::query()->sole()->content)->toBe('Waiting for its post');
});

it('is idempotent', function () {
    $this->site->comment($this->post);

    importComments();
    $second = importComments();

    expect($second->counts()['comments'])->toMatchArray(['created' => 0, 'updated' => 0, 'unchanged' => 1])
        ->and(Comment::count())->toBe(1);
});

it('warns when comments are requested but disabled', function () {
    config()->set('wp-migrator.comments.enabled', false);

    $this->artisan('wp:import', ['--only' => 'comments'])
        ->expectsOutputToContain('wp-migrator.comments.enabled')
        ->assertSuccessful();
});
