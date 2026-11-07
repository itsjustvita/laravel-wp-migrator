<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Readers;

use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\WpDatabase;

/**
 * Loads the meta rows of many objects in one query per thousand IDs.
 */
class MetaLoader
{
    private const int BATCH = 1000;

    public function __construct(private readonly WpDatabase $db) {}

    /**
     * @param  list<int>  $ids
     * @param  string  $primaryKey  keeps rows of a repeated key in insertion order;
     *                              usermeta calls it umeta_id, the other meta tables meta_id
     * @return array<int, MetaBag>
     */
    public function load(string $table, string $foreignKey, array $ids, string $primaryKey = 'meta_id'): array
    {
        $grouped = [];

        foreach (array_chunk($ids, self::BATCH) as $batch) {
            $rows = $this->db->table($table)
                ->whereIn($foreignKey, $batch)
                ->orderBy($primaryKey)
                ->get([$foreignKey, 'meta_key', 'meta_value']);

            foreach ($rows as $row) {
                $id = (int) $row->{$foreignKey};
                $key = (string) $row->meta_key;
                $grouped[$id][$key][] = (string) ($row->meta_value ?? '');
            }
        }

        return array_map(fn (array $values): MetaBag => new MetaBag($values), $grouped);
    }
}
