<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import\Importers;

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\ImportMap;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Source\Dto\SourceMenu;
use ItsJustVita\WpMigrator\Source\Readers\MenuReader;
use ItsJustVita\WpMigrator\WpMigrator;

/**
 * @extends EntityImporter<SourceMenu>
 */
final class MenuImporter extends EntityImporter
{
    public function __construct(WpMigrator $mappers, ImportMap $map, private readonly MenuReader $reader)
    {
        parent::__construct($mappers, $map);
    }

    public function name(): string
    {
        return 'menus';
    }

    public function shouldRun(ImportOptions $options): bool
    {
        return $options->includes('menus');
    }

    public function count(ImportOptions $options): int
    {
        return $this->reader->count();
    }

    protected function sourceType(): SourceType
    {
        return SourceType::Term;
    }

    protected function batches(ImportOptions $options): iterable
    {
        return $this->reader->chunks();
    }

    protected function sourceId(object $record): int
    {
        return $record->id;
    }

    protected function sourceData(object $record): array
    {
        return $record->toArray();
    }

    protected function mapperClass(object $record): string
    {
        return $this->mappers->menuMapper()::class;
    }

    protected function map(object $record, ImportContext $context): ?Model
    {
        return $this->mappers->menuMapper()->map($record, $context);
    }
}
