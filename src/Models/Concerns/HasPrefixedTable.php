<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Models\Concerns;

use ItsJustVita\WpMigrator\Database\Tables;

/**
 * Prepends the configured target table prefix to the model's table name.
 */
trait HasPrefixedTable
{
    public function getTable(): string
    {
        return Tables::prefixed($this->table ?? parent::getTable());
    }
}
