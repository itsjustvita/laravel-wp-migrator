<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Mapping;

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Source\Dto\SourceMedia;

interface MediaMapper
{
    /**
     * Persists the source media and returns the target model, or null to skip it.
     */
    public function map(SourceMedia $media, ImportContext $context): ?Model;
}
