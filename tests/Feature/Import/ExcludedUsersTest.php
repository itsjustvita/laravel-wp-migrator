<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\ImportReport;
use ItsJustVita\WpMigrator\Import\ImportRunner;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Source\ExcludedUsers;
use ItsJustVita\WpMigrator\Tests\Support\User;

function importWithExclusions(array $excluded): ImportReport
{
    config()->set('wp-migrator.users.excluded', $excluded);

    return app(ImportRunner::class)->run(new ImportOptions);
}

it('finds excluded users by ID, login and email address, ignoring case', function () {
    $site = $this->source();
    $first = $site->user(['user_login' => 'alice']);
    $second = $site->user(['user_login' => 'Intruder', 'user_email' => 'X@Evil.example']);
    $third = $site->user(['user_login' => 'bob']);
    $site->user(['user_login' => 'carol']);

    config()->set('wp-migrator.users.excluded', [(string) $first, 'intruder', 'bob@EXAMPLE.com', 999, '  ', null]);

    expect(app(ExcludedUsers::class)->ids())->toBe([$first, $second, $third]);

    config()->set('wp-migrator.users.excluded', ['x@evil.example']);

    expect(app(ExcludedUsers::class)->ids())->toBe([$second]);
});

it('tries a string of digits as an ID, a login and an email address', function () {
    $site = $this->source();
    $numeric = $site->user(['user_login' => '12345']);
    $byId = $site->user(['user_login' => 'alice']);

    config()->set('wp-migrator.users.excluded', ['12345', (string) $byId]);
    expect(app(ExcludedUsers::class)->ids())->toBe([$numeric, $byId]);

    config()->set('wp-migrator.users.excluded', [12345]);
    expect(app(ExcludedUsers::class)->ids())->toBe([]);

    config()->set('wp-migrator.users.excluded', [$numeric.'st-admin', '12345']);
    expect(app(ExcludedUsers::class)->unmatched())->toBe([$numeric.'st-admin']);

    $lookalike = $site->user(['user_login' => (string) $byId]);
    config()->set('wp-migrator.users.excluded', [(string) $byId]);
    expect(app(ExcludedUsers::class)->ids())->toBe([$byId, $lookalike]);
});

it('names the entries that match no user', function () {
    $site = $this->source();
    $site->user(['user_login' => 'alice']);

    config()->set('wp-migrator.users.excluded', ['Alice', 'intrudr', 404, 'nobody@example.com']);
    $excluded = app(ExcludedUsers::class);

    expect($excluded->unmatched())->toBe(['intrudr', '404', 'nobody@example.com'])
        ->and($excluded->ids())->toHaveCount(1);

    $report = importWithExclusions(['intrudr']);

    expect($report->toMarkdown())->toContain('users.excluded: "intrudr" matches no user; check the spelling');
});

it('excludes nobody by default or with an old configuration', function () {
    $this->source()->user(['user_login' => 'alice']);

    config()->set('wp-migrator.users.excluded', []);
    expect(app(ExcludedUsers::class)->ids())->toBe([]);

    config()->set('wp-migrator.users', ['attributes' => config('wp-migrator.users.attributes')]);
    expect(app(ExcludedUsers::class)->ids())->toBe([]);
});

it('leaves excluded users out of the import', function () {
    $site = $this->source();
    $site->user(['user_login' => 'alice', 'user_email' => 'alice@example.com']);
    $site->user(['user_login' => 'intruder', 'user_email' => 'intruder@evil.example']);

    $report = importWithExclusions(['intruder']);

    expect(User::query()->pluck('email')->all())->toBe(['alice@example.com'])
        ->and($report->counts()['users']['created'])->toBe(1)
        ->and($report->warnings())->toBe([])
        ->and($report->toMarkdown())->toContain('Left out by users.excluded: user 2');
});

it('imports the posts of excluded users without an author and without open references', function () {
    $site = $this->source();
    $intruder = $site->user(['user_login' => 'intruder']);
    $site->post(['post_title' => 'Planted', 'post_author' => $intruder]);

    $report = importWithExclusions(['intruder']);

    expect(Post::query()->where('title', 'Planted')->sole()->author_id)->toBeNull()
        ->and($report->unresolvedReferences())->toBe([]);

    $second = importWithExclusions(['intruder']);

    expect($second->counts()['posts']['unchanged'])->toBe(1);
});

it('warns about an excluded user that an earlier run imported', function () {
    $site = $this->source();
    $site->user(['user_login' => 'intruder']);
    importWithExclusions([]);

    $report = importWithExclusions(['intruder']);

    expect($report->warnings())->toHaveCount(1)
        ->and($report->warnings()[0]['message'])->toContain('an earlier run imported this account')
        ->and(User::count())->toBe(1);
});
