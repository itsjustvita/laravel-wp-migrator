<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import\Importers;

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\ImportMap;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Source\Dto\SourceMedia;
use ItsJustVita\WpMigrator\Source\Readers\MediaFilter;
use ItsJustVita\WpMigrator\Source\Readers\MediaReader;
use ItsJustVita\WpMigrator\WpMigrator;

/**
 * @extends EntityImporter<SourceMedia>
 */
final class MediaImporter extends EntityImporter
{
    public function __construct(WpMigrator $mappers, ImportMap $map, private readonly MediaReader $reader)
    {
        parent::__construct($mappers, $map);
    }

    public function name(): string
    {
        return 'media';
    }

    public function shouldRun(ImportOptions $options): bool
    {
        return $options->includes('media');
    }

    public function count(ImportOptions $options): int
    {
        return $this->reader->count(new MediaFilter($options->since));
    }

    protected function sourceType(): SourceType
    {
        return SourceType::Post;
    }

    protected function batches(ImportOptions $options): iterable
    {
        return $this->reader->chunks(new MediaFilter($options->since));
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
        return $this->mappers->mediaMapper()::class;
    }

    protected function map(object $record, ImportContext $context): ?Model
    {
        return $this->mappers->mediaMapper()->map($record, $context);
    }
}
