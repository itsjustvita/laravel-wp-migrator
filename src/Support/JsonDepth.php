<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Support;

/**
 * How deeply arrays and objects nest in a value, the way databases count it
 * for JSON columns: a scalar is 0, an array or object is one more than its
 * deepest member. MariaDB accepts at most 31, MySQL at most 100.
 */
final class JsonDepth
{
    public const int MARIADB_LIMIT = 31;

    public static function of(mixed $value): int
    {
        if (! is_array($value)) {
            return 0;
        }

        $deepest = 0;

        foreach ($value as $member) {
            $deepest = max($deepest, self::of($member));
        }

        return 1 + $deepest;
    }
}
