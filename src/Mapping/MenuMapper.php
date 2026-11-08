<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Mapping;

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Source\Dto\SourceMenu;

interface MenuMapper
{
    /**
     * Persists the source menu and returns the target model, or null to skip it.
     */
    public function map(SourceMenu $menu, ImportContext $context): ?Model;
}
