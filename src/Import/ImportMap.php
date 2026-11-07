<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Models\ImportMapEntry;

/**
 * Remembers which source row became which target model. Importers consult it
 * to skip unchanged rows, update changed ones and resolve references.
 */
class ImportMap
{
    public function find(SourceType $type, int $sourceId): ?ImportMapEntry
    {
        return $this->entry($type, $sourceId)->first();
    }

    public function targetId(SourceType $type, int $sourceId): ?int
    {
        $id = $this->entry($type, $sourceId)->value('target_id');

        return $id === null ? null : (int) $id;
    }

    public function isUnchanged(SourceType $type, int $sourceId, string $checksum): bool
    {
        return $this->entry($type, $sourceId)->where('checksum', $checksum)->exists();
    }

    public function record(SourceType $type, int $sourceId, Model $target, string $checksum, bool $linked = false): ImportMapEntry
    {
        return ImportMapEntry::query()->updateOrCreate(
            ['source_type' => $type->value, 'source_id' => $sourceId],
            [
                'target_type' => $target->getMorphClass(),
                'target_id' => (int) $target->getKey(),
                'checksum' => $checksum,
                'linked' => $linked,
                'imported_at' => now(),
            ],
        );
    }

    /**
     * A checksum of the source data that ignores key order, so the same row
     * produces the same value no matter how it was assembled.
     */
    public static function checksum(mixed $data): string
    {
        $json = json_encode(self::normalise($data), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('xxh128', $json);
    }

    private static function normalise(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        if (! array_is_list($data)) {
            ksort($data);
        }

        return array_map(self::normalise(...), $data);
    }

    /**
     * @return Builder<ImportMapEntry>
     */
    private function entry(SourceType $type, int $sourceId): Builder
    {
        return ImportMapEntry::query()
            ->where('source_type', $type->value)
            ->where('source_id', $sourceId);
    }
}
