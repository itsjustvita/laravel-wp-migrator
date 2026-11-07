<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Database;

use Illuminate\Config\Repository;

/**
 * Resolves the names of the package's own tables, honouring the configured
 * target prefix. Used by the migrations and by every model.
 */
final class Tables
{
    public static function prefixed(string $table): string
    {
        return self::prefix().$table;
    }

    public static function prefix(): string
    {
        return app(Repository::class)->string('wp-migrator.target.table_prefix', '');
    }
}
