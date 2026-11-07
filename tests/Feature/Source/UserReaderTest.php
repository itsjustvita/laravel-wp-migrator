<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Source\Dto\SourceUser;
use ItsJustVita\WpMigrator\Source\Readers\UserFilter;
use ItsJustVita\WpMigrator\Source\Readers\UserReader;
use ItsJustVita\WpMigrator\Tests\Support\SourceSite;

function readAllUsers(): array
{
    return array_merge(...iterator_to_array(app(UserReader::class)->chunks(new UserFilter), false) ?: [[]]);
}

it('reads users with roles taken from their capabilities meta', function () {
    $site = $this->source();
    $id = $site->user([
        'user_login' => 'alice',
        'user_email' => 'alice@example.com',
        'display_name' => 'Alice Example',
        'user_nicename' => 'alice',
        'user_pass' => '$P$BvQhUw2EiJc3cLSm0tn1g/w7qVkT5l.',
        'user_registered' => '2020-05-01 12:00:00',
        'user_url' => 'https://alice.example',
    ]);
    $site->userMeta($id, 'wp_capabilities', serialize(['editor' => true, 'subscriber' => false]));
    $site->userMeta($id, 'first_name', 'Alice');

    $users = readAllUsers();

    expect($users)->toHaveCount(1);

    $user = $users[0];

    expect($user)->toBeInstanceOf(SourceUser::class)
        ->and($user->id)->toBe($id)
        ->and($user->login)->toBe('alice')
        ->and($user->email)->toBe('alice@example.com')
        ->and($user->displayName)->toBe('Alice Example')
        ->and($user->nicename)->toBe('alice')
        ->and($user->url)->toBe('https://alice.example')
        ->and($user->passwordHash)->toBe('$P$BvQhUw2EiJc3cLSm0tn1g/w7qVkT5l.')
        ->and($user->registeredAt?->toIso8601String())->toBe('2020-05-01T12:00:00+00:00')
        ->and($user->roles)->toBe(['editor'])
        ->and($user->rolesMalformed)->toBeFalse()
        ->and($user->meta->get('first_name'))->toBe('Alice');
});

it('reports users without usable capabilities', function () {
    $site = $this->source();
    $none = $site->user(['user_login' => 'nobody']);
    $broken = $site->user(['user_login' => 'broken']);
    $site->userMeta($broken, 'wp_capabilities', 'a:1:{s:6:"editor";');

    [$noRoles, $malformed] = readAllUsers();

    expect($noRoles->roles)->toBe([])
        ->and($noRoles->rolesMalformed)->toBeFalse()
        ->and($malformed->roles)->toBe([])
        ->and($malformed->rolesMalformed)->toBeTrue()
        ->and(app(UserReader::class)->count(new UserFilter))->toBe(2);
});

it('reads capabilities under the configured table prefix', function () {
    $site = new SourceSite(app('db')->connection('wordpress'), 'cms_');
    $site->createSchema();
    config()->set('wp-migrator.source.table_prefix', 'cms_');
    $id = $site->user(['user_login' => 'alice']);
    $site->userMeta($id, 'cms_capabilities', serialize(['author' => true]));
    $site->userMeta($id, 'wp_capabilities', serialize(['administrator' => true]));

    expect(readAllUsers()[0]->roles)->toBe(['author']);
});
