<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import;

use Illuminate\Database\Eloquent\Model;

/**
 * Handed to every mapper call. Gives access to the import map and the report
 * and tracks two facts about the row being mapped: whether a reference could
 * not be resolved (the row is then processed again on the next run) and
 * whether the mapper linked an existing record instead of creating one.
 */
class ImportContext
{
    private bool $rowIncomplete = false;

    private bool $rowLinked = false;

    private ?string $skipReason = null;

    /**
     * @param  list<int>  $excludedUsers  users left out by users.excluded; references to them resolve to null
     */
    public function __construct(
        public readonly ImportMap $map,
        public readonly ImportReport $report,
        public readonly string $importer,
        public readonly bool $dryRun = false,
        public readonly array $excludedUsers = [],
    ) {}

    public function startRow(): void
    {
        $this->rowIncomplete = false;
        $this->rowLinked = false;
        $this->skipReason = null;
    }

    /**
     * Explains why the mapper is about to return no model, for the report.
     */
    public function skip(string $reason): void
    {
        $this->skipReason = $reason;
    }

    public function skipReason(): ?string
    {
        return $this->skipReason;
    }

    public function warn(int $sourceId, string $message): void
    {
        $this->report->warn($this->importer, $sourceId, $message);
    }

    /**
     * The target ID a source reference was imported as, or null. A reference
     * that is set but cannot be resolved is reported and marks the row as
     * incomplete. With $targetClass, only targets of that model class count.
     *
     * @param  class-string<Model>|null  $targetClass
     */
    public function resolve(SourceType $type, ?int $reference, int $sourceId, string $kind, ?string $targetClass = null): ?int
    {
        if ($reference === null || ($type === SourceType::User && in_array($reference, $this->excludedUsers, true))) {
            return null;
        }

        $entry = $this->map->find($type, $reference);

        if ($entry !== null && ($targetClass === null || $entry->target_type === (new $targetClass)->getMorphClass())) {
            return $entry->target_id;
        }

        $this->report->unresolved($this->importer, $sourceId, $kind, $reference);
        $this->rowIncomplete = true;

        return null;
    }

    /**
     * The model a source row was imported as before, when it is of the given class.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $class
     * @return TModel|null
     */
    public function existing(SourceType $type, int $sourceId, string $class): ?Model
    {
        $entry = $this->map->find($type, $sourceId);

        if ($entry === null || $entry->target_type !== (new $class)->getMorphClass()) {
            return null;
        }

        return $class::query()->find($entry->target_id);
    }

    /**
     * Whether an earlier run linked this source row to a record that already existed.
     */
    public function wasLinked(SourceType $type, int $sourceId): bool
    {
        return $this->map->find($type, $sourceId)?->linked === true;
    }

    /**
     * The row is not fully imported and should be mapped again next time.
     */
    public function markIncomplete(): void
    {
        $this->rowIncomplete = true;
    }

    public function rowIncomplete(): bool
    {
        return $this->rowIncomplete;
    }

    /**
     * The mapper returned a record that existed before the import and must not be changed by it.
     */
    public function markLinked(): void
    {
        $this->rowLinked = true;
    }

    public function rowLinked(): bool
    {
        return $this->rowLinked;
    }
}
