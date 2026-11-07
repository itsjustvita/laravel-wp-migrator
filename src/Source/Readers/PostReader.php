<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Readers;

use Generator;
use Illuminate\Database\Query\Builder;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;
use ItsJustVita\WpMigrator\Source\Dto\SourceTerm;
use ItsJustVita\WpMigrator\Support\Hierarchy;
use ItsJustVita\WpMigrator\Support\WpDates;

class PostReader extends Reader
{
    public function count(PostFilter $filter): int
    {
        return $this->query($filter)->count();
    }

    /**
     * IDs of the matching posts grouped by depth in the parent hierarchy,
     * top level first, so parents are imported before their children.
     * Posts whose parent is outside the selection count as top level.
     *
     * @return list<list<int>>
     */
    public function idsByDepth(PostFilter $filter): array
    {
        $parents = [];

        foreach ($this->query($filter)->orderBy('ID')->get(['ID', 'post_parent']) as $row) {
            $parents[self::int($row->ID)] = self::int($row->post_parent);
        }

        return Hierarchy::levels($parents);
    }

    /**
     * Matching posts in batches of the configured chunk size, parents first.
     *
     * @return Generator<int, list<SourcePost>>
     */
    public function chunks(PostFilter $filter): Generator
    {
        foreach ($this->idsByDepth($filter) as $level) {
            foreach (array_chunk($level, $this->chunkSize()) as $ids) {
                yield $this->byIds($ids);
            }
        }
    }

    /**
     * @param  list<int>  $ids
     * @return list<SourcePost>
     */
    public function byIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->db->table('posts')->whereIn('ID', $ids)->orderBy('ID')->get();
        $meta = $this->meta->load('postmeta', 'post_id', $ids);
        $terms = $this->termsFor($ids);
        $timezone = $this->db->timezone();

        $posts = [];

        foreach ($rows as $row) {
            $id = self::int($row->ID);

            $posts[] = new SourcePost(
                id: $id,
                type: self::string($row->post_type),
                status: self::string($row->post_status),
                title: self::string($row->post_title),
                slug: rawurldecode(self::string($row->post_name)),
                content: self::string($row->post_content),
                excerpt: self::string($row->post_excerpt),
                authorId: self::idOrNull($row->post_author),
                parentId: self::idOrNull($row->post_parent),
                menuOrder: self::int($row->menu_order),
                mimeType: self::nullableString($row->post_mime_type),
                guid: self::nullableString($row->guid),
                publishedAt: WpDates::parse(self::nullableString($row->post_date_gmt), self::nullableString($row->post_date), $timezone),
                modifiedAt: WpDates::parse(self::nullableString($row->post_modified_gmt), self::nullableString($row->post_modified), $timezone),
                meta: $meta[$id] ?? MetaBag::empty(),
                terms: $terms[$id] ?? [],
            );
        }

        return $posts;
    }

    private function query(PostFilter $filter): Builder
    {
        $query = $this->db->posts();

        if ($filter->types === null) {
            $query->whereNotIn('post_type', $this->config->array('wp-migrator.import.excluded_post_types'));
        } else {
            $query->whereIn('post_type', $filter->types);
        }

        if ($filter->excludedTypes !== []) {
            $query->whereNotIn('post_type', $filter->excludedTypes);
        }

        $query->whereIn('post_status', $filter->statuses ?? $this->config->array('wp-migrator.import.post_statuses'));

        if ($filter->since !== null) {
            $query->where('post_modified_gmt', '>=', $filter->since->utc()->format('Y-m-d H:i:s'));
        }

        return $query;
    }

    /**
     * The terms attached to each of the given posts, ordered by taxonomy.
     *
     * @param  list<int>  $ids
     * @return array<int, list<SourceTerm>>
     */
    private function termsFor(array $ids): array
    {
        $relationships = $this->db->tableName('term_relationships');
        $taxonomies = $this->db->tableName('term_taxonomy');
        $terms = $this->db->tableName('terms');

        $rows = $this->db->table('term_relationships')
            ->join($taxonomies, $taxonomies.'.term_taxonomy_id', '=', $relationships.'.term_taxonomy_id')
            ->join($terms, $terms.'.term_id', '=', $taxonomies.'.term_id')
            ->whereIn($relationships.'.object_id', $ids)
            ->whereNotIn($taxonomies.'.taxonomy', $this->config->array('wp-migrator.import.excluded_taxonomies'))
            ->orderBy($relationships.'.object_id')
            ->orderBy($taxonomies.'.taxonomy')
            ->orderBy($terms.'.term_id')
            ->get([
                $relationships.'.object_id',
                $taxonomies.'.term_taxonomy_id',
                $taxonomies.'.term_id',
                $taxonomies.'.taxonomy',
                $taxonomies.'.description',
                $taxonomies.'.parent',
                $terms.'.name',
                $terms.'.slug',
            ]);

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[self::int($row->object_id)][] = new SourceTerm(
                id: self::int($row->term_id),
                taxonomyId: self::int($row->term_taxonomy_id),
                taxonomy: self::string($row->taxonomy),
                name: self::string($row->name),
                slug: rawurldecode(self::string($row->slug)),
                description: self::string($row->description),
                parentId: self::idOrNull($row->parent),
                meta: MetaBag::empty(),
            );
        }

        return $grouped;
    }
}
