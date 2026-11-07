<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Mapping;

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Source\Dto\SourceUser;

interface UserMapper
{
    /**
     * Persists the source user and returns the target model, or null to skip it.
     */
    public function map(SourceUser $user, ImportContext $context): ?Model;
}
