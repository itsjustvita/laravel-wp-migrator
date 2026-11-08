<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Mapping;

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Source\Dto\SourceMenuItem;

interface MenuItemMapper
{
    /**
     * Persists the source item and returns the target model, or null to skip it.
     */
    public function map(SourceMenuItem $item, ImportContext $context): ?Model;
}
