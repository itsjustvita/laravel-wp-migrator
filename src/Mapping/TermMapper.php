<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Mapping;

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Source\Dto\SourceTerm;

interface TermMapper
{
    /**
     * Persists the source term and returns the target model, or null to skip it.
     */
    public function map(SourceTerm $term, ImportContext $context): ?Model;
}
