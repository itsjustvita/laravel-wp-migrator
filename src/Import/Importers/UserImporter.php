<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import\Importers;

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\ImportMap;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\ImportProgress;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Source\Dto\SourceUser;
use ItsJustVita\WpMigrator\Source\ExcludedUsers;
use ItsJustVita\WpMigrator\Source\Readers\UserFilter;
use ItsJustVita\WpMigrator\Source\Readers\UserReader;
use ItsJustVita\WpMigrator\WpMigrator;

/**
 * @extends EntityImporter<SourceUser>
 */
final class UserImporter extends EntityImporter
{
    public function __construct(
        WpMigrator $mappers,
        ImportMap $map,
        private readonly UserReader $reader,
        private readonly ExcludedUsers $excluded,
    ) {
        parent::__construct($mappers, $map);
    }

    public function run(ImportOptions $options, ImportContext $context, ImportProgress $progress): void
    {
        foreach ($this->excluded->ids() as $id) {
            if ($this->map->find(SourceType::User, $id) !== null) {
                $context->warn($id, 'Left out by users.excluded, but an earlier run imported this account. Delete it in the application, or it can still log in.');
            }
        }

        parent::run($options, $context, $progress);
    }

    public function name(): string
    {
        return 'users';
    }

    public function shouldRun(ImportOptions $options): bool
    {
        return $options->includes('users');
    }

    public function count(ImportOptions $options): int
    {
        return $this->reader->count(new UserFilter($this->excluded->ids()));
    }

    protected function sourceType(): SourceType
    {
        return SourceType::User;
    }

    protected function batches(ImportOptions $options): iterable
    {
        return $this->reader->chunks(new UserFilter($this->excluded->ids()));
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
        return $this->mappers->userMapper()::class;
    }

    protected function map(object $record, ImportContext $context): ?Model
    {
        return $this->mappers->userMapper()->map($record, $context);
    }
}
