<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\ImportMap;
use ItsJustVita\WpMigrator\Import\ImportReport;
use ItsJustVita\WpMigrator\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

function importContext(string $importer = 'test', bool $dryRun = false, ?ImportReport $report = null): ImportContext
{
    return new ImportContext(app(ImportMap::class), $report ?? new ImportReport, $importer, $dryRun);
}
