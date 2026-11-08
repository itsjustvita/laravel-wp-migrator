<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Readers;

use Generator;
use Illuminate\Database\Query\Builder;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\Dto\SourceMenuItem;
use ItsJustVita\WpMigrator\Support\Hierarchy;
use ItsJustVita\WpMigrator\Support\MalformedSerializedValue;
use stdClass;

/**
 * Published items of classic menus, parents before children. Drafts are
 * unsaved changes from the customizer and left out.
 */
class MenuItemReader extends Reader
{
    public function count(): int
    {
        return $this->query()->count();
    }

    /**
     * @return Generator<int, list<SourceMenuItem>>
     */
    public function chunks(): Generator
    {
        $posts = $this->db->tableName('posts');
        $rows = $this->query()->orderBy($posts.'.ID')->get([$posts.'.ID', $posts.'.post_title', $posts.'.menu_order', $this->db->tableName('term_taxonomy').'.term_id as menu_id']);

        $ids = array_values($rows->map(fn (object $row): int => self::int($row->ID))->all());
        $meta = $this->meta->load('postmeta', 'post_id', $ids);
        $parents = [];
        $byId = [];

        foreach ($rows as $row) {
            $id = self::int($row->ID);
            $parents[$id] = self::int(($meta[$id] ?? MetaBag::empty())->get('_menu_item_menu_item_parent'));
            $byId[$id] = $row;
        }

        $titles = $this->objectTitles($meta);

        foreach (Hierarchy::levels($parents) as $level) {
            foreach (array_chunk($level, $this->chunkSize()) as $chunk) {
                yield array_map(fn (int $id): SourceMenuItem => $this->toItem($byId[$id], $meta[$id] ?? MetaBag::empty(), $titles), $chunk);
            }
        }
    }

    private function query(): Builder
    {
        $posts = $this->db->tableName('posts');
        $relationships = $this->db->tableName('term_relationships');
        $taxonomies = $this->db->tableName('term_taxonomy');

        return $this->db->table('posts')
            ->join($relationships, $relationships.'.object_id', '=', $posts.'.ID')
            ->join($taxonomies, $taxonomies.'.term_taxonomy_id', '=', $relationships.'.term_taxonomy_id')
            ->where($taxonomies.'.taxonomy', 'nav_menu')
            ->where($posts.'.post_type', 'nav_menu_item')
            ->where($posts.'.post_status', 'publish');
    }

    /**
     * @param  array<string, array<int, string>>  $titles
     */
    private function toItem(stdClass $row, MetaBag $meta, array $titles): SourceMenuItem
    {
        $id = self::int($row->ID);
        $type = (string) $meta->get('_menu_item_type');
        $objectId = self::idOrNull($meta->get('_menu_item_object_id'));

        $titleGroup = match ($type) {
            'post_type' => 'posts',
            'taxonomy' => 'terms',
            default => null,
        };

        return new SourceMenuItem(
            id: $id,
            menuId: self::int($row->menu_id),
            parentId: self::idOrNull($meta->get('_menu_item_menu_item_parent')),
            order: self::int($row->menu_order),
            title: self::string($row->post_title),
            type: $type,
            object: (string) $meta->get('_menu_item_object'),
            objectId: $objectId,
            objectTitle: $titleGroup === null || $objectId === null ? null : ($titles[$titleGroup][$objectId] ?? null),
            url: (string) $meta->get('_menu_item_url'),
            target: (string) $meta->get('_menu_item_target'),
            classes: $this->classes($meta),
        );
    }

    /**
     * @return list<string>
     */
    private function classes(MetaBag $meta): array
    {
        try {
            $classes = $meta->decode('_menu_item_classes');
        } catch (MalformedSerializedValue) {
            return [];
        }

        $list = is_array($classes) ? $classes : [$classes];

        return array_values(array_filter(array_map(fn (mixed $class): string => trim(self::string($class)), $list), fn (string $class): bool => $class !== ''));
    }

    /**
     * Titles of the posts and names of the terms the items link to.
     *
     * @param  array<int, MetaBag>  $meta
     * @return array<string, array<int, string>>
     */
    private function objectTitles(array $meta): array
    {
        $postIds = [];
        $termIds = [];

        foreach ($meta as $bag) {
            $objectId = self::int($bag->get('_menu_item_object_id'));

            match ($bag->get('_menu_item_type')) {
                'post_type' => $postIds[] = $objectId,
                'taxonomy' => $termIds[] = $objectId,
                default => null,
            };
        }

        $titles = ['posts' => [], 'terms' => []];

        foreach (array_chunk(array_unique($postIds), 1000) as $chunk) {
            foreach ($this->db->table('posts')->whereIn('ID', $chunk)->get(['ID', 'post_title']) as $row) {
                $titles['posts'][self::int($row->ID)] = self::string($row->post_title);
            }
        }

        foreach (array_chunk(array_unique($termIds), 1000) as $chunk) {
            foreach ($this->db->table('terms')->whereIn('term_id', $chunk)->get(['term_id', 'name']) as $row) {
                $titles['terms'][self::int($row->term_id)] = self::string($row->name);
            }
        }

        return $titles;
    }
}
