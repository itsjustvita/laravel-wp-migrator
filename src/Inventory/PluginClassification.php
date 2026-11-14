<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory;

/**
 * What an active plugin means for the migration.
 *
 * status is one of: handled (the import covers it), replace (the new site
 * needs its own solution), unsupported (its content is not migrated),
 * obsolete (nothing to do in Laravel) and unknown.
 */
final readonly class PluginClassification
{
    public function __construct(
        public string $file,
        public string $name,
        public string $status,
        public string $advice,
    ) {}

    public function needsWork(): bool
    {
        return in_array($this->status, ['replace', 'unsupported', 'unknown'], true);
    }
}
