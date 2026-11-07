<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import\Importers;

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\ImportMap;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\ImportProgress;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Models\ImportMapEntry;
use ItsJustVita\WpMigrator\WpMigrator;
use LogicException;
use Throwable;

/**
 * Runs source records through their mapper, one transaction per record.
 *
 * A record is skipped when the checksum of its source data and mapper class
 * matches the last import. Records that failed, or whose references could not
 * all be resolved, are stored without a checksum and mapped again next time.
 *
 * @template TRecord of object
 */
abstract class EntityImporter implements Importer
{
    public function __construct(
        protected readonly WpMigrator $mappers,
        protected readonly ImportMap $map,
    ) {}

    abstract protected function sourceType(): SourceType;

    /**
     * @return iterable<list<TRecord>>
     */
    abstract protected function batches(ImportOptions $options): iterable;

    /**
     * @param  TRecord  $record
     */
    abstract protected function sourceId(object $record): int;

    /**
     * @param  TRecord  $record
     * @return array<string, mixed>
     */
    abstract protected function sourceData(object $record): array;

    /**
     * @param  TRecord  $record
     * @return class-string
     */
    abstract protected function mapperClass(object $record): string;

    /**
     * @param  TRecord  $record
     */
    abstract protected function map(object $record, ImportContext $context): ?Model;

    /**
     * Whether rows with unresolved references are mapped once more at the end
     * of the run, when the rows they refer to may have been imported.
     */
    protected function retriesIncompleteRows(): bool
    {
        return false;
    }

    /**
     * The records with these source IDs, read again for the second pass.
     *
     * @param  list<int>  $ids
     * @return iterable<list<TRecord>>
     */
    protected function reload(array $ids): iterable
    {
        throw new LogicException(static::class.' must implement reload() to retry incomplete rows.');
    }

    public function run(ImportOptions $options, ImportContext $context, ImportProgress $progress): void
    {
        $report = $context->report;
        $report->begin($this->name());
        $retry = $this->retriesIncompleteRows();

        /** @var array<int, 'created'|'updated'> $deferred */
        $deferred = [];

        foreach ($this->batches($options) as $batch) {
            foreach ($batch as $record) {
                $checkpoint = $retry ? $report->checkpoint() : null;
                $outcome = $this->import($record, $options, $context);

                if ($checkpoint !== null && $outcome !== null && $context->rowIncomplete()) {
                    // Its findings are forgotten; the second pass reports what is still open.
                    $report->rollbackTo($checkpoint);
                    $deferred[$this->sourceId($record)] = $outcome;
                } elseif ($outcome !== null) {
                    $this->recordOutcome($outcome, $context);
                }

                $progress->advance();
            }
        }

        if ($deferred === []) {
            return;
        }

        foreach ($this->reload(array_keys($deferred)) as $batch) {
            foreach ($batch as $record) {
                if ($this->import($record, $options, $context) !== null) {
                    $this->recordOutcome($deferred[$this->sourceId($record)], $context);
                }
            }
        }
    }

    /**
     * @param  'created'|'updated'  $outcome
     */
    private function recordOutcome(string $outcome, ImportContext $context): void
    {
        $outcome === 'created' ? $context->report->created($this->name()) : $context->report->updated($this->name());
    }

    /**
     * Maps one record. Unchanged, skipped and failed records are reported
     * here; for a written record the caller learns whether it was new.
     *
     * @param  TRecord  $record
     * @return 'created'|'updated'|null
     */
    private function import(object $record, ImportOptions $options, ImportContext $context): ?string
    {
        $id = $this->sourceId($record);

        try {
            $checksum = ImportMap::checksum([
                'mapper' => $this->mapperClass($record),
                'source' => $this->sourceData($record),
            ]);

            $entry = $this->map->find($this->sourceType(), $id);

            if (! $options->force && $entry !== null && $entry->checksum === $checksum) {
                $context->report->unchanged($this->name());

                return null;
            }

            $context->startRow();

            $model = (new ImportMapEntry)->getConnection()->transaction(function () use ($record, $context, $id, $checksum, $entry): ?Model {
                $model = $this->map($record, $context);

                if ($model !== null) {
                    $this->map->record(
                        $this->sourceType(),
                        $id,
                        $model,
                        $context->rowIncomplete() ? '' : $checksum,
                        $context->rowLinked() || $entry?->linked === true,
                    );
                }

                return $model;
            });
        } catch (Throwable $exception) {
            $context->report->failed($this->name(), $id, $exception);

            return null;
        }

        if ($model === null) {
            $context->report->skipped($this->name(), $id, sprintf('%s returned no model', class_basename($this->mapperClass($record))));

            return null;
        }

        return $entry === null ? 'created' : 'updated';
    }
}
