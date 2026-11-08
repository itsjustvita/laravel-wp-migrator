<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import\Importers;

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\ImportMap;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;
use ItsJustVita\WpMigrator\Source\Readers\PostFilter;
use ItsJustVita\WpMigrator\Source\Readers\PostReader;
use ItsJustVita\WpMigrator\WpMigrator;

/**
 * The navigation menus of block themes, stored as wp_navigation posts.
 *
 * @extends EntityImporter<SourcePost>
 */
final class NavigationImporter extends EntityImporter
{
    public function __construct(WpMigrator $mappers, ImportMap $map, private readonly PostReader $reader)
    {
        parent::__construct($mappers, $map);
    }

    public function name(): string
    {
        return 'navigation menus';
    }

    public function shouldRun(ImportOptions $options): bool
    {
        return $options->includes('menus');
    }

    public function count(ImportOptions $options): int
    {
        return $this->reader->count(self::filter());
    }

    private static function filter(): PostFilter
    {
        return new PostFilter(types: ['wp_navigation'], statuses: ['publish']);
    }

    protected function sourceType(): SourceType
    {
        return SourceType::Post;
    }

    protected function batches(ImportOptions $options): iterable
    {
        return $this->reader->chunks(self::filter());
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
        return $this->mappers->navigationMapper()::class;
    }

    protected function map(object $record, ImportContext $context): ?Model
    {
        return $this->mappers->navigationMapper()->map($record, $context);
    }
}
