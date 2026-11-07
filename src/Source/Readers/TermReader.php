<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Readers;

use Generator;
use Illuminate\Database\Query\Builder;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\Dto\SourceTerm;
use ItsJustVita\WpMigrator\Support\Hierarchy;

class TermReader extends Reader
{
    public function count(TermFilter $filter): int
    {
        return $this->query($filter)->count();
    }

    /**
     * @return Generator<int, list<SourceTerm>>
     */
    public function chunks(TermFilter $filter): Generator
    {
        foreach (array_chunk($this->all($filter), $this->chunkSize()) as $terms) {
            yield $terms;
        }
    }

    /**
     * All matching terms, parents before children, by ID within a level.
     *
     * @return list<SourceTerm>
     */
    public function all(TermFilter $filter): array
    {
        $rows = $this->query($filter)->orderBy($this->db->tableName('terms').'.term_id')->get([
            $this->db->tableName('terms').'.term_id',
            $this->db->tableName('terms').'.name',
            $this->db->tableName('terms').'.slug',
            $this->db->tableName('term_taxonomy').'.term_taxonomy_id',
            $this->db->tableName('term_taxonomy').'.taxonomy',
            $this->db->tableName('term_taxonomy').'.description',
            $this->db->tableName('term_taxonomy').'.parent',
        ]);

        $ids = array_values($rows->map(fn (object $row): int => self::int($row->term_id))->all());
        $meta = $this->meta->load('termmeta', 'term_id', $ids);

        $terms = [];
        $parents = [];

        foreach ($rows as $row) {
            $id = self::int($row->term_id);
            $parents[$id] = self::int($row->parent);

            $terms[$id] = new SourceTerm(
                id: $id,
                taxonomyId: self::int($row->term_taxonomy_id),
                taxonomy: self::string($row->taxonomy),
                name: self::string($row->name),
                slug: rawurldecode(self::string($row->slug)),
                description: self::string($row->description),
                parentId: self::idOrNull($row->parent),
                meta: $meta[$id] ?? MetaBag::empty(),
            );
        }

        $ordered = [];

        foreach (Hierarchy::levels($parents) as $level) {
            foreach ($level as $id) {
                $ordered[] = $terms[$id];
            }
        }

        return $ordered;
    }

    private function query(TermFilter $filter): Builder
    {
        $taxonomies = $this->db->tableName('term_taxonomy');
        $terms = $this->db->tableName('terms');

        $query = $this->db->table('term_taxonomy')
            ->join($terms, $terms.'.term_id', '=', $taxonomies.'.term_id');

        if ($filter->taxonomies === null) {
            $query->whereNotIn($taxonomies.'.taxonomy', $this->config->array('wp-migrator.import.excluded_taxonomies'));
        } else {
            $query->whereIn($taxonomies.'.taxonomy', $filter->taxonomies);
        }

        return $query;
    }
}
