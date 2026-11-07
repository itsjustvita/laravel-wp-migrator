<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import\Importers;

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\ImportMap;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Source\Dto\SourceTerm;
use ItsJustVita\WpMigrator\Source\Readers\TermFilter;
use ItsJustVita\WpMigrator\Source\Readers\TermReader;
use ItsJustVita\WpMigrator\WpMigrator;

/**
 * @extends EntityImporter<SourceTerm>
 */
final class TermImporter extends EntityImporter
{
    public function __construct(WpMigrator $mappers, ImportMap $map, private readonly TermReader $reader)
    {
        parent::__construct($mappers, $map);
    }

    public function name(): string
    {
        return 'terms';
    }

    public function shouldRun(ImportOptions $options): bool
    {
        return $options->includes('terms');
    }

    public function count(ImportOptions $options): int
    {
        return $this->reader->count(new TermFilter);
    }

    protected function sourceType(): SourceType
    {
        return SourceType::Term;
    }

    protected function batches(ImportOptions $options): iterable
    {
        return $this->reader->chunks(new TermFilter);
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
        return $this->mappers->termMapper($record->taxonomy)::class;
    }

    protected function map(object $record, ImportContext $context): ?Model
    {
        return $this->mappers->termMapper($record->taxonomy)->map($record, $context);
    }
}
