<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import\Importers;

use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\ImportProgress;

interface Importer
{
    /** The name used in --only and in the report. */
    public function name(): string;

    public function shouldRun(ImportOptions $options): bool;

    public function count(ImportOptions $options): int;

    public function run(ImportOptions $options, ImportContext $context, ImportProgress $progress): void;
}
