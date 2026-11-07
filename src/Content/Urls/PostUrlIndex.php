<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Urls;

use Illuminate\Config\Repository;
use Illuminate\Database\Query\Builder;
use ItsJustVita\WpMigrator\Source\WpDatabase;
use ItsJustVita\WpMigrator\Support\MalformedSerializedValue;
use ItsJustVita\WpMigrator\Support\Serialized;

/**
 * Answers which source post a URL path or ID on the old site belongs to. All
 * content posts are read once, on first use. Lookups go by the path the post
 * has now; a path from an older permalink structure still finds the post
 * when its last segment is a slug only one post has.
 */
class PostUrlIndex
{
    /** Paths starting like this are never posts, even when the last segment is a known slug. */
    private const array SYSTEM_SEGMENTS = ['category', 'tag', 'author', 'feed', 'comments', 'page', 'search', 'wp-content', 'wp-admin', 'wp-includes', 'wp-json'];

    /** @var array<int, PostReference> */
    private array $byId = [];

    /** @var array<string, int> */
    private array $byPath = [];

    /** @var array<string, list<int>> */
    private array $bySlug = [];

    /** @var list<string> */
    private array $systemSegments = [];

    private bool $loaded = false;

    public function __construct(
        private readonly WpDatabase $db,
        private readonly Repository $config,
    ) {}

    public function find(string $path): ?PostReference
    {
        $this->load();

        $normalised = $this->normalise($path);

        if ($normalised === '') {
            return null;
        }

        if (isset($this->byPath[$normalised])) {
            return $this->byId[$this->byPath[$normalised]];
        }

        $segments = explode('/', $normalised);

        if (in_array($segments[0], $this->systemSegments, true)) {
            return null;
        }

        $candidates = $this->bySlug[$segments[array_key_last($segments)]] ?? [];

        return count($candidates) === 1 ? $this->byId[$candidates[0]] : null;
    }

    public function findById(int $id): ?PostReference
    {
        $this->load();

        return $this->byId[$id] ?? null;
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        $structure = (string) $this->db->option('permalink_structure');

        $this->systemSegments = [
            ...self::SYSTEM_SEGMENTS,
            $this->normalise($this->optionOr('category_base', 'category')),
            $this->normalise($this->optionOr('tag_base', 'tag')),
        ];

        $rows = $this->contentPosts()->get(['ID', 'post_type', 'post_status', 'post_name', 'post_parent', 'post_date', 'post_author']);

        $slugs = [];
        $parents = [];

        foreach ($rows as $row) {
            $id = (int) $row->ID;
            $slugs[$id] = rawurldecode((string) $row->post_name);
            $parents[$id] = (int) $row->post_parent;
        }

        $authors = str_contains($structure, '%author%') ? $this->authors() : [];
        $categories = str_contains($structure, '%category%') ? $this->categories() : [];
        $defaultCategory = $categories['default'] ?? null;
        $builder = new PermalinkBuilder($structure, $this->postTypeSlugs());

        foreach ($rows as $row) {
            $id = (int) $row->ID;
            $type = (string) $row->post_type;

            $reference = new PostReference(
                id: $id,
                type: $type,
                status: (string) $row->post_status,
                slug: $slugs[$id],
                path: $this->hierarchyPath($id, $slugs, $parents),
                date: (string) $row->post_date,
                author: $authors[(int) $row->post_author] ?? null,
                category: $categories[$id] ?? $defaultCategory,
            );

            $this->byId[$id] = $reference;

            if ($reference->slug !== '') {
                $this->bySlug[$this->normalise($reference->slug)][] = $id;
            }

            $path = $builder->path($reference);

            if ($path !== null) {
                $this->byPath[$this->normalise($path)] = $id;
            }
        }
    }

    private function contentPosts(): Builder
    {
        return $this->db->posts()
            ->whereNotIn('post_type', $this->config->array('wp-migrator.import.excluded_post_types'))
            ->whereIn('post_status', $this->config->array('wp-migrator.import.post_statuses'))
            ->orderBy('ID');
    }

    /**
     * @param  array<int, string>  $slugs
     * @param  array<int, int>  $parents
     */
    private function hierarchyPath(int $id, array $slugs, array $parents): string
    {
        $segments = [$slugs[$id]];
        $seen = [$id => true];
        $current = $parents[$id] ?? 0;

        while ($current !== 0 && isset($slugs[$current]) && ! isset($seen[$current])) {
            array_unshift($segments, $slugs[$current]);
            $seen[$current] = true;
            $current = $parents[$current] ?? 0;
        }

        return trim(implode('/', $segments), '/');
    }

    /**
     * @return array<int, string>
     */
    private function authors(): array
    {
        $authors = [];

        foreach ($this->db->table('users')->get(['ID', 'user_nicename']) as $row) {
            $authors[(int) $row->ID] = (string) $row->user_nicename;
        }

        return $authors;
    }

    /**
     * The category path of every post that has a category, by post ID, using
     * the category with the lowest ID. "default" holds the default category.
     *
     * @return array<int|string, string>
     */
    private function categories(): array
    {
        $taxonomies = $this->db->tableName('term_taxonomy');
        $terms = $this->db->tableName('terms');
        $relationships = $this->db->tableName('term_relationships');

        $slugs = [];
        $parents = [];

        $rows = $this->db->table('term_taxonomy')
            ->join($terms, $terms.'.term_id', '=', $taxonomies.'.term_id')
            ->where($taxonomies.'.taxonomy', 'category')
            ->get([$taxonomies.'.term_id', $taxonomies.'.parent', $terms.'.slug']);

        foreach ($rows as $row) {
            $id = (int) $row->term_id;
            $slugs[$id] = rawurldecode((string) $row->slug);
            $parents[$id] = (int) $row->parent;
        }

        $assignments = $this->db->table('term_relationships')
            ->join($taxonomies, $taxonomies.'.term_taxonomy_id', '=', $relationships.'.term_taxonomy_id')
            ->where($taxonomies.'.taxonomy', 'category')
            ->orderBy($taxonomies.'.term_id', 'desc')
            ->get([$relationships.'.object_id', $taxonomies.'.term_id']);

        $categories = [];

        foreach ($assignments as $row) {
            $termId = (int) $row->term_id;

            if (isset($slugs[$termId])) {
                $categories[(int) $row->object_id] = $this->hierarchyPath($termId, $slugs, $parents);
            }
        }

        $default = (int) $this->db->option('default_category');

        if (isset($slugs[$default])) {
            $categories['default'] = $this->hierarchyPath($default, $slugs, $parents);
        }

        return $categories;
    }

    /**
     * Rewrite slugs of custom post types: configured ones first, then what can
     * be read from the compiled rewrite rules.
     *
     * @return array<string, string>
     */
    private function postTypeSlugs(): array
    {
        $configured = $this->config->get('wp-migrator.urls.post_type_slugs', []);
        $slugs = is_array($configured) ? array_map(strval(...), $configured) : [];

        foreach ($this->rewriteRules() as $rule => $target) {
            if (preg_match('/^index\.php\?([a-zA-Z0-9_-]+)=\$matches\[1\]/', (string) $target, $matches) !== 1) {
                continue;
            }

            $bracket = strpos((string) $rule, '(');

            if ($bracket === false || isset($slugs[$matches[1]])) {
                continue;
            }

            $slug = trim(substr((string) $rule, 0, $bracket), '/');

            if ($slug !== '' && preg_match('/[\[\]\\\\?*+]/', $slug) !== 1) {
                $slugs[$matches[1]] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function rewriteRules(): array
    {
        try {
            $rules = Serialized::decode((string) $this->db->option('rewrite_rules'));
        } catch (MalformedSerializedValue) {
            return [];
        }

        return is_array($rules) ? $rules : [];
    }

    private function optionOr(string $name, string $default): string
    {
        $value = $this->db->option($name);

        return $value === null || $value === '' ? $default : $value;
    }

    private function normalise(string $path): string
    {
        return mb_strtolower(trim(rawurldecode($path), '/'));
    }
}
