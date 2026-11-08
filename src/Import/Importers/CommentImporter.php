<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import\Importers;

use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\ImportMap;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Source\Dto\SourceComment;
use ItsJustVita\WpMigrator\Source\Readers\CommentReader;
use ItsJustVita\WpMigrator\WpMigrator;

/**
 * @extends EntityImporter<SourceComment>
 */
final class CommentImporter extends EntityImporter
{
    public function __construct(WpMigrator $mappers, ImportMap $map, private readonly CommentReader $reader, private readonly Repository $config)
    {
        parent::__construct($mappers, $map);
    }

    public function name(): string
    {
        return 'comments';
    }

    public function shouldRun(ImportOptions $options): bool
    {
        return $options->includes('comments') && $this->config->boolean('wp-migrator.comments.enabled');
    }

    public function count(ImportOptions $options): int
    {
        return $this->reader->count($options->since);
    }

    protected function sourceType(): SourceType
    {
        return SourceType::Comment;
    }

    protected function batches(ImportOptions $options): iterable
    {
        return $this->reader->chunks($options->since);
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
        return $this->mappers->commentMapper()::class;
    }

    protected function map(object $record, ImportContext $context): ?Model
    {
        return $this->mappers->commentMapper()->map($record, $context);
    }
}
