<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/**
 * WP keeps every date twice: in the site's local time and in GMT. The GMT
 * column is authoritative but is zeroed for drafts, so the local column is
 * the fallback, read in the given timezone.
 */
final class WpDates
{
    public static function parse(?string $gmt, ?string $local, string $localTimezone = 'UTC'): ?CarbonImmutable
    {
        if (self::isSet($gmt)) {
            return self::make($gmt, 'UTC');
        }

        if (self::isSet($local)) {
            return self::make($local, $localTimezone);
        }

        return null;
    }

    /**
     * The same instant on the application's clock. Eloquent stores a date's
     * wall clock without converting it, so a value in any other timezone
     * would come back shifted.
     */
    public static function forStorage(?DateTimeInterface $date): ?CarbonImmutable
    {
        return $date === null ? null : CarbonImmutable::instance($date)->setTimezone(date_default_timezone_get());
    }

    /**
     * @phpstan-assert-if-true non-empty-string $value
     */
    private static function isSet(?string $value): bool
    {
        return $value !== null && $value !== '' && ! str_starts_with($value, '0000-00-00');
    }

    private static function make(string $value, string $timezone): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value, $timezone);
        } catch (Throwable) {
            return null;
        }
    }
}
