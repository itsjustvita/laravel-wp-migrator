<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import\Importers;

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Content\ContentTransformer;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\ImportMap;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;
use ItsJustVita\WpMigrator\Source\Readers\PostReader;
use ItsJustVita\WpMigrator\WpMigrator;

/**
 * Posts, pages and custom post types, parents before children.
 *
 * @extends EntityImporter<SourcePost>
 */
final class PostImporter extends EntityImporter
{
    public function __construct(
        WpMigrator $mappers,
        ImportMap $map,
        private readonly PostReader $reader,
        private readonly ContentTransformer $content,
    ) {
        parent::__construct($mappers, $map);
    }

    public function name(): string
    {
        return 'posts';
    }

    public function shouldRun(ImportOptions $options): bool
    {
        return $options->includes('posts') || $options->includes('pages');
    }

    public function count(ImportOptions $options): int
    {
        return $this->reader->count($options->postFilter());
    }

    protected function sourceType(): SourceType
    {
        return SourceType::Post;
    }

    protected function batches(ImportOptions $options): iterable
    {
        return $this->reader->chunks($options->postFilter());
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
        return $this->mappers->postMapper($record->type)::class;
    }

    protected function map(object $record, ImportContext $context): ?Model
    {
        return $this->mappers->postMapper($record->type)->map(
            $record->withBlocks($this->content->transform($record, $context)),
            $context,
        );
    }
}
