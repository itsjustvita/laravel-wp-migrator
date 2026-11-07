<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use ItsJustVita\WpMigrator\Support\WpDates;

it('prefers the gmt column and reads it as utc', function () {
    $date = WpDates::parse('2026-01-15 10:00:00', '2026-01-15 11:00:00');

    expect($date)->toBeInstanceOf(CarbonImmutable::class)
        ->and($date?->toIso8601String())->toBe('2026-01-15T10:00:00+00:00');
});

it('falls back to the local column when the gmt column is zero or empty', function (?string $gmt) {
    $date = WpDates::parse($gmt, '2026-01-15 11:00:00', 'Europe/Berlin');

    expect($date?->toIso8601String())->toBe('2026-01-15T11:00:00+01:00');
})->with([
    'zero' => '0000-00-00 00:00:00',
    'null' => null,
    'empty' => '',
]);

it('returns null when both columns are empty', function () {
    expect(WpDates::parse('0000-00-00 00:00:00', '0000-00-00 00:00:00'))->toBeNull()
        ->and(WpDates::parse(null, null))->toBeNull();
});

it('returns null for unparseable values', function () {
    expect(WpDates::parse('not a date', 'also not'))->toBeNull();
});
