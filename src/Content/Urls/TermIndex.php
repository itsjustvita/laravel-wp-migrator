<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Urls;

use Illuminate\Config\Repository;
use ItsJustVita\WpMigrator\Source\WpDatabase;
use ItsJustVita\WpMigrator\Support\Hierarchy;

/**
 * The terms of the source site with the slug paths of their parents, read
 * once on first use.
 */
class TermIndex
{
    /** @var array<int, TermReference>|null */
    private ?array $byId = null;

    /** @var array<string, list<TermReference>> */
    private array $byTaxonomy = [];

    public function __construct(
        private readonly WpDatabase $db,
        private readonly Repository $config,
    ) {}

    public function find(int $id): ?TermReference
    {
        return $this->load()[$id] ?? null;
    }

    /**
     * The terms of one taxonomy, parents before children.
     *
     * @return list<TermReference>
     */
    public function taxonomy(string $taxonomy): array
    {
        $this->load();

        return $this->byTaxonomy[$taxonomy] ?? [];
    }

    /**
     * @return array<int, TermReference>
     */
    private function load(): array
    {
        if ($this->byId !== null) {
            return $this->byId;
        }

        $taxonomies = $this->db->tableName('term_taxonomy');
        $terms = $this->db->tableName('terms');

        $rows = $this->db->table('term_taxonomy')
            ->join($terms, $terms.'.term_id', '=', $taxonomies.'.term_id')
            ->whereNotIn($taxonomies.'.taxonomy', $this->config->array('wp-migrator.import.excluded_taxonomies'))
            ->orderBy($terms.'.term_id')
            ->get([$terms.'.term_id', $terms.'.slug', $terms.'.name', $taxonomies.'.taxonomy', $taxonomies.'.parent']);

        $slugs = [];
        $parents = [];
        $rowsById = [];

        foreach ($rows as $row) {
            $id = (int) $row->term_id;
            $slugs[$id] = rawurldecode((string) $row->slug);
            $parents[$id] = (int) $row->parent;
            $rowsById[$id] = $row;
        }

        $this->byId = [];

        foreach (Hierarchy::levels($parents) as $level) {
            foreach ($level as $id) {
                $row = $rowsById[$id];
                $reference = new TermReference($id, (string) $row->taxonomy, $slugs[$id], $this->path($id, $slugs, $parents), (string) $row->name);

                $this->byId[$id] = $reference;
                $this->byTaxonomy[$reference->taxonomy][] = $reference;
            }
        }

        return $this->byId;
    }

    /**
     * @param  array<int, string>  $slugs
     * @param  array<int, int>  $parents
     */
    private function path(int $id, array $slugs, array $parents): string
    {
        $segments = [$slugs[$id]];
        $seen = [$id => true];
        $current = $parents[$id] ?? 0;

        while ($current !== 0 && isset($slugs[$current]) && ! isset($seen[$current])) {
            array_unshift($segments, $slugs[$current]);
            $seen[$current] = true;
            $current = $parents[$current] ?? 0;
        }

        return implode('/', $segments);
    }
}
