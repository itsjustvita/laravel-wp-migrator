<?php

declare(strict_types=1);

use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use ItsJustVita\WpMigrator\Auth\WpHasher;
use ItsJustVita\WpMigrator\Tests\Support\User;

function wpVectors(?string $kind = null): array
{
    $vectors = json_decode(file_get_contents(__DIR__.'/../../Fixtures/wp-password-hashes.json'), true)['vectors'];

    return array_values(array_filter($vectors, fn (array $v) => $kind === null || $v['kind'] === $kind));
}

function wpHasher(): WpHasher
{
    return Hash::driver('wordpress');
}

/**
 * A user whose stored hash bypasses the hashed cast, as after an import.
 */
function userWithHash(string $hash, string $email = 'alice@example.com'): User
{
    $user = User::create(['name' => 'Alice', 'email' => $email, 'password' => 'placeholder']);
    DB::table('users')->where('id', $user->id)->update(['password' => $hash]);

    return $user->fresh();
}

it('is registered as a hashing driver', function () {
    expect(wpHasher())->toBeInstanceOf(WpHasher::class);
});

it('verifies hashes made by WP, the way WP checks a login', function (string $kind, string $password, string $hash) {
    config()->set('wp-migrator.passwords.allow_md5', true);

    expect(wpHasher()->check($password, $hash))->toBeTrue()
        ->and(wpHasher()->check($password.'x', $hash))->toBeFalse()
        ->and(wpHasher()->check(' '.$password.' ', $hash))->toBe($kind !== 'bcrypt');
})->with(fn () => array_map(fn (array $v) => [$v['kind'], $v['password'], $v['hash']], wpVectors()));

it('ignores spaces around the password like the WP login does', function () {
    $vector = array_values(array_filter(wpVectors('wp-bcrypt'), fn (array $v) => $v['password'] === '  spaced  '))[0];

    expect(wpHasher()->check('  spaced  ', $vector['hash']))->toBeTrue()
        ->and(wpHasher()->check('spaced', $vector['hash']))->toBeTrue();
});

it('accepts md5 hashes only when the configuration allows them', function () {
    $md5 = wpVectors('md5')[0];

    expect(wpHasher()->check($md5['password'], $md5['hash']))->toBeFalse();

    config()->set('wp-migrator.passwords.allow_md5', true);

    expect(wpHasher()->check($md5['password'], $md5['hash']))->toBeTrue()
        ->and(wpHasher()->check($md5['password'], strtoupper($md5['hash'])))->toBeTrue();
});

it('makes new hashes with the configured laravel driver', function () {
    $hash = wpHasher()->make('secret');

    expect($hash)->toStartWith('$2y$')
        ->and(wpHasher()->check('secret', $hash))->toBeTrue()
        ->and(wpHasher()->needsRehash($hash))->toBeFalse()
        ->and(wpHasher()->info($hash)['algoName'])->toBe('bcrypt');
});

it('asks for a rehash of every WP hash', function (string $kind, string $hash) {
    expect(wpHasher()->needsRehash($hash))->toBeTrue()
        ->and(wpHasher()->info($hash)['algoName'])->toBe($kind === 'bcrypt' ? 'bcrypt' : $kind);
})->with(fn () => array_map(fn (array $v) => [$v['kind'], $v['hash']], array_filter(wpVectors(), fn (array $v) => $v['kind'] !== 'bcrypt')));

it('rejects empty and unknown hashes', function (?string $hash) {
    expect(wpHasher()->check('secret', $hash))->toBeFalse();
})->with([null, '', '*', 'not a hash', '$P$short']);

it('refuses to wrap itself', function () {
    config()->set('wp-migrator.passwords.driver', 'wordpress');

    Hash::forgetDrivers();
    Hash::driver('wordpress');
})->throws(InvalidArgumentException::class);

it('logs a user in with a WP hash and stores a laravel hash instead', function (string $password, string $hash) {
    config()->set('hashing.driver', 'wordpress');
    Hash::forgetDrivers();
    $user = userWithHash($hash);

    expect(Auth::attempt(['email' => 'alice@example.com', 'password' => $password]))->toBeTrue();

    $stored = DB::table('users')->where('id', $user->id)->value('password');

    expect($stored)->toStartWith('$2y$')
        ->and(Hash::check($password, $stored))->toBeTrue()
        ->and(Auth::id())->toBe($user->id);
})->with(fn () => array_map(fn (array $v) => [$v['password'], $v['hash']], array_merge(wpVectors('phpass'), wpVectors('wp-bcrypt'))));

it('leaves the hash alone when the login fails', function () {
    config()->set('hashing.driver', 'wordpress');
    Hash::forgetDrivers();
    $vector = wpVectors('phpass')[0];
    userWithHash($vector['hash']);

    expect(Auth::attempt(['email' => 'alice@example.com', 'password' => 'wrong']))->toBeFalse()
        ->and(DB::table('users')->value('password'))->toBe($vector['hash']);
});

it('keeps laravel hashes working when it is the default driver', function () {
    config()->set('hashing.driver', 'wordpress');
    Hash::forgetDrivers();
    User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret']);

    expect(DB::table('users')->value('password'))->toStartWith('$2y$')
        ->and(Auth::attempt(['email' => 'bob@example.com', 'password' => 'secret']))->toBeTrue();
});

it('asks for a rehash of WP hashes even when the inner driver would not', function () {
    $inner = new class implements Hasher
    {
        public function info($hashedValue): array
        {
            return [];
        }

        public function make($value, array $options = []): string
        {
            return 'x';
        }

        public function check($value, $hashedValue, array $options = []): bool
        {
            return false;
        }

        public function needsRehash($hashedValue, array $options = []): bool
        {
            return false;
        }
    };

    $hasher = new WpHasher($inner, app('config'));

    foreach (wpVectors() as $vector) {
        expect($hasher->needsRehash($vector['hash']))->toBe($vector['kind'] !== 'bcrypt');
    }
});
