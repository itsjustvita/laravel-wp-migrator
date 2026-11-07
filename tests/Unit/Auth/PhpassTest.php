<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Auth\Phpass;

function passwordVectors(string $kind): array
{
    $vectors = json_decode(file_get_contents(__DIR__.'/../../Fixtures/wp-password-hashes.json'), true)['vectors'];

    return array_values(array_filter($vectors, fn (array $vector) => $vector['kind'] === $kind));
}

it('verifies portable hashes made by WP', function (string $password, string $hash) {
    // WP hashed the trimmed password; trimming at login is the hasher's job, not this class's.
    expect(Phpass::check(trim($password), $hash))->toBeTrue()
        ->and(Phpass::check(trim($password).'x', $hash))->toBeFalse()
        ->and(Phpass::check('', $hash))->toBeFalse();
})->with(fn () => array_map(fn (array $v) => [$v['password'], $v['hash']], passwordVectors('phpass')));

it('accepts the phpBB spelling of the same format', function () {
    $hash = passwordVectors('phpass')[0]['hash'];

    expect(Phpass::check(passwordVectors('phpass')[0]['password'], '$H$'.substr($hash, 3)))->toBeTrue();
});

it('recognises portable hashes', function (string $hash, bool $expected) {
    expect(Phpass::isHash($hash))->toBe($expected);
})->with([
    'wp' => ['$P$B5HJAEq4JYnGTBLnRZ0VhC5c0VbXn/0', true],
    'phpbb' => ['$H$B5HJAEq4JYnGTBLnRZ0VhC5c0VbXn/0', true],
    'too short' => ['$P$B5HJAEq4JYnGTBLnRZ0VhC5c0VbXn/', false],
    'too long' => ['$P$B5HJAEq4JYnGTBLnRZ0VhC5c0VbXn/00', false],
    'bcrypt' => ['$2y$10$abcdefghijklmnopqrstuuMx3N5A3d4Vh6wq9cH8k1o2Vb3Zx1F.a', false],
    'empty' => ['', false],
]);

it('rejects malformed hashes without trying them', function (string $hash) {
    expect(Phpass::check('anything', $hash))->toBeFalse();
})->with([
    'wrong prefix' => ['$X$B5HJAEq4JYnGTBLnRZ0VhC5c0VbXn/0'],
    'rounds out of the alphabet' => ['$P$!5HJAEq4JYnGTBLnRZ0VhC5c0VbXn/0'],
    'too few rounds' => ['$P$45HJAEq4JYnGTBLnRZ0VhC5c0VbXn/0'],
    'truncated' => ['$P$B5HJAEq4'],
]);

it('refuses round counts that would let one login take minutes', function () {
    $start = microtime(true);

    // "N" stands for 2^25 iterations: valid in the format, half a minute of work.
    expect(Phpass::check('anything', '$P$N5HJAEq4JYnGTBLnRZ0VhC5c0VbXn/0'))->toBeFalse()
        ->and(Phpass::check('anything', '$P$z5HJAEq4JYnGTBLnRZ0VhC5c0VbXn/0'))->toBeFalse()
        ->and(microtime(true) - $start)->toBeLessThan(1.0);
});
