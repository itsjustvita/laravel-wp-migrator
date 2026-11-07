<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Mapping\DefaultUserMapper;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\Dto\SourceUser;
use ItsJustVita\WpMigrator\Tests\Support\User;

function sourceUser(int $id, string $email = 'alice@example.com', array $roles = ['editor'], string $hash = '$P$BvQhUw2EiJc3cLSm0tn1g/w7qVkT5l.'): SourceUser
{
    return new SourceUser($id, 'alice', $email, 'Alice Example', 'alice', null, $hash, CarbonImmutable::parse('2020-05-01 12:00:00'), $roles, false, MetaBag::empty());
}

it('creates a user and keeps the original password hash despite the hashed cast', function () {
    $context = importContext('users');

    $user = app(DefaultUserMapper::class)->map(sourceUser(5), $context);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->name)->toBe('Alice Example')
        ->and($user->email)->toBe('alice@example.com')
        ->and($user->created_at?->toIso8601String())->toBe('2020-05-01T12:00:00+00:00')
        ->and(User::query()->value('password'))->toBe('$P$BvQhUw2EiJc3cLSm0tn1g/w7qVkT5l.');
});

it('links an existing user with the same email and leaves their password alone', function () {
    $existing = User::create(['name' => 'App Admin', 'email' => 'alice@example.com', 'password' => 'secret']);
    $appHash = User::query()->value('password');
    $context = importContext('users');

    $user = app(DefaultUserMapper::class)->map(sourceUser(5), $context);

    expect($user->is($existing))->toBeTrue()
        ->and(User::count())->toBe(1)
        ->and(User::query()->value('password'))->toBe($appHash)
        ->and($context->report->warnings()[0]['message'])->toContain('existing user');
});

it('updates the password of a user it imported earlier', function () {
    $context = importContext('users');
    $mapper = app(DefaultUserMapper::class);

    $user = $mapper->map(sourceUser(5), $context);
    $context->map->record(SourceType::User, 5, $user, 'x');
    $mapper->map(sourceUser(5, hash: '$wp$2y$10$newhash'), $context);

    expect(User::count())->toBe(1)
        ->and(User::query()->value('password'))->toBe('$wp$2y$10$newhash');
});

it('maps roles through config into the configured attribute', function () {
    Schema::table('users', fn ($table) => $table->string('role')->nullable());
    config()->set('wp-migrator.users.roles', ['administrator' => 'admin']);
    config()->set('wp-migrator.users.role_attribute', 'role');

    $admin = app(DefaultUserMapper::class)->map(sourceUser(5, roles: ['administrator']), importContext('users'));
    $editor = app(DefaultUserMapper::class)->map(sourceUser(6, 'bob@example.com', ['editor']), importContext('users'));

    expect($admin->role)->toBe('admin')
        ->and($editor->role)->toBe('editor');
});

it('gives users without an email a placeholder and warns', function () {
    $context = importContext('users');

    $user = app(DefaultUserMapper::class)->map(sourceUser(5, email: ''), $context);

    expect($user->email)->toBe('wp-user-5@invalid.localhost')
        ->and($context->report->warnings()[0]['message'])->toContain('placeholder');
});
