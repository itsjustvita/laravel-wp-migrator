<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use ItsJustVita\WpMigrator\Tests\Support\User;

function storeHash(string $email, string $hash): void
{
    $user = User::create(['name' => $email, 'email' => $email, 'password' => 'x']);
    DB::table('users')->where('id', $user->id)->update(['password' => $hash]);
}

it('counts users per hash format', function () {
    $vectors = json_decode(file_get_contents(__DIR__.'/../../Fixtures/wp-password-hashes.json'), true)['vectors'];
    $byKind = [];
    foreach ($vectors as $vector) {
        $byKind[$vector['kind']] ??= $vector['hash'];
    }

    storeHash('a@example.com', $byKind['phpass']);
    storeHash('b@example.com', $byKind['phpass']);
    storeHash('c@example.com', $byKind['wp-bcrypt']);
    storeHash('d@example.com', $byKind['md5']);
    storeHash('e@example.com', $byKind['bcrypt']);
    User::create(['name' => 'f', 'email' => 'f@example.com', 'password' => 'secret']);
    storeHash('g@example.com', '');

    $this->artisan('wp:password-stats')
        ->expectsOutputToContain('phpass (WP before 6.8)')
        ->expectsOutputToContain('bcrypt with WP prefix (WP 6.8 and later)')
        ->expectsOutputToContain('md5 (very old WP)')
        ->expectsOutputToContain('Current hash')
        ->expectsOutputToContain('No password')
        ->expectsOutputToContain('4 of 7 users still have a WP hash')
        ->assertSuccessful();
});

it('says when no user is left on a WP hash', function () {
    User::create(['name' => 'f', 'email' => 'f@example.com', 'password' => 'secret']);

    $this->artisan('wp:password-stats')
        ->expectsOutputToContain('No user has a WP hash any more')
        ->assertSuccessful();
});
