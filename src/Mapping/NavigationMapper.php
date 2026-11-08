<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Mapping;

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;

interface NavigationMapper
{
    /**
     * Persists the source navigation and returns the target model, or null to skip it.
     */
    public function map(SourcePost $navigation, ImportContext $context): ?Model;
}
