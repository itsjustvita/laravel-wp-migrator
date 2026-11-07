<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source;

use Illuminate\Database\Query\Builder;

/**
 * Substring matching with LIKE where the needle is taken literally.
 *
 * "!" is used as the escape character because a backslash is interpreted
 * differently by MySQL and SQLite string literals.
 */
final class Like
{
    public static function contains(Builder $query, string $column, string $needle): Builder
    {
        return $query->whereRaw(new ContainsExpression($column), ['%'.self::escape($needle).'%']);
    }

    public static function escape(string $needle): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $needle);
    }
}
