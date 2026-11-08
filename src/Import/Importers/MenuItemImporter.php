<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import\Importers;

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\ImportMap;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Source\Dto\SourceMenuItem;
use ItsJustVita\WpMigrator\Source\Readers\MenuItemReader;
use ItsJustVita\WpMigrator\WpMigrator;

/**
 * @extends EntityImporter<SourceMenuItem>
 */
final class MenuItemImporter extends EntityImporter
{
    public function __construct(WpMigrator $mappers, ImportMap $map, private readonly MenuItemReader $reader)
    {
        parent::__construct($mappers, $map);
    }

    public function name(): string
    {
        return 'menu items';
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
        return SourceType::Post;
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
        return $this->mappers->menuItemMapper()::class;
    }

    protected function map(object $record, ImportContext $context): ?Model
    {
        return $this->mappers->menuItemMapper()->map($record, $context);
    }
}
