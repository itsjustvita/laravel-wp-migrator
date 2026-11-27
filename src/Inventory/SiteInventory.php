<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory;

use Illuminate\Config\Repository;
use ItsJustVita\WpMigrator\Content\Blocks\Block;
use ItsJustVita\WpMigrator\Content\Blocks\BlockParser;
use ItsJustVita\WpMigrator\Content\Blocks\BlockRenderer;
use ItsJustVita\WpMigrator\Content\Elementor\ElementorConverter;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeProcessor;
use ItsJustVita\WpMigrator\Detection\Finding;
use ItsJustVita\WpMigrator\Detection\Suspicious\SuspiciousContent;
use ItsJustVita\WpMigrator\Detection\UnsupportedContentDetector;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\ExcludedUsers;
use ItsJustVita\WpMigrator\Source\Readers\MenuReader;
use ItsJustVita\WpMigrator\Source\Readers\MetaLoader;
use ItsJustVita\WpMigrator\Source\SourceStats;
use ItsJustVita\WpMigrator\Source\WpDatabase;
use ItsJustVita\WpMigrator\Support\MalformedSerializedValue;
use ItsJustVita\WpMigrator\Support\Serialized;
use ItsJustVita\WpMigrator\WpMigrator;
use Throwable;

/**
 * Reads what the migration plan needs from the source database. When the
 * database cannot be read, the plan is written from the theme alone.
 */
class SiteInventory
{
    private const int EXAMPLES = 5;

    public function __construct(
        private readonly WpDatabase $db,
        private readonly Repository $config,
        private readonly SourceStats $stats,
        private readonly MenuReader $menus,
        private readonly UnsupportedContentDetector $detector,
        private readonly ShortcodeProcessor $shortcodes,
        private readonly BlockParser $parser,
        private readonly BlockRenderer $renderer,
        private readonly WpMigrator $registry,
        private readonly ExcludedUsers $excluded,
        private readonly ElementorConverter $elementor,
        private readonly MetaLoader $meta,
        private readonly SuspiciousContent $suspicious,
    ) {}

    public function read(): SiteFacts
    {
        try {
            $this->db->ping();

            [$shortcodes, $blocks, $elementorPages] = $this->scanContent();

            return new SiteFacts(
                reachable: true,
                siteName: $this->db->option('blogname'),
                homeUrl: $this->db->option('home'),
                stylesheet: $this->db->option('stylesheet'),
                template: $this->db->option('template'),
                postCounts: $this->stats->postCounts(),
                taxonomies: array_diff_key($this->stats->taxonomyCounts(), array_flip($this->config->array('wp-migrator.import.excluded_taxonomies'))),
                pageTemplates: $this->pageTemplates(),
                shortcodes: $shortcodes,
                blocks: $blocks,
                plugins: $this->plugins(),
                menus: $this->menus(),
                widgets: $this->widgets(),
                findings: array_values(array_filter($this->detector->findings(), fn (Finding $finding): bool => $finding->detected)),
                userCount: $this->stats->userCount(),
                administrators: $this->administrators(),
                queryVars: $this->queryVars(),
                elementorPages: $elementorPages,
                suspicious: $this->suspicious->findings(),
                frontPage: $this->frontPage(),
                authors: $this->authors(),
            );
        } catch (Throwable $exception) {
            return SiteFacts::unreachable($exception->getMessage());
        }
    }

    /**
     * Shortcodes and blocks in the content that the package cannot render
     * yet, and the number of pages converted from their Elementor data.
     * Those pages are scanned the way the import converts them.
     *
     * @return array{0: array<string, array{count: int, post_ids: list<int>}>, 1: array<string, array{count: int, post_ids: list<int>}>, 2: int}
     */
    private function scanContent(): array
    {
        $shortcodes = [];
        $blocks = [];
        $elementorPages = 0;
        $convertsElementor = $this->config->boolean('wp-migrator.content.elementor', true);

        $posts = $this->db->posts()
            ->whereNotIn('post_type', $this->config->array('wp-migrator.import.excluded_post_types'))
            ->whereIn('post_status', $this->config->array('wp-migrator.import.post_statuses'))
            ->select(['ID', 'post_content']);

        foreach ($posts->lazyById(200, 'ID')->chunk(200) as $rows) {
            $ids = array_values($rows->map(fn (object $row): int => (int) $row->ID)->all());
            $meta = $convertsElementor ? $this->meta->load('postmeta', 'post_id', $ids) : [];

            foreach ($rows as $post) {
                $id = (int) $post->ID;
                $tree = $this->elementorTree($meta[$id] ?? MetaBag::empty());

                if ($tree !== null) {
                    $elementorPages++;
                    $this->countBlocks($tree, $blocks, $id);
                    $this->countShortcodes($this->html($tree), $shortcodes, $id);

                    continue;
                }

                $content = (string) $post->post_content;
                $this->countShortcodes($content, $shortcodes, $id);

                if (str_contains($content, '<!-- wp:')) {
                    $this->countBlocks($this->parser->parse($content), $blocks, $id);
                }
            }
        }

        return [$shortcodes, $blocks, $elementorPages];
    }

    /**
     * The blocks a page built with Elementor is converted into, or null.
     *
     * @return list<Block>|null
     */
    private function elementorTree(MetaBag $meta): ?array
    {
        if (! ElementorConverter::builtWith($meta)) {
            return null;
        }

        $tree = $this->elementor->convert((string) $meta->get('_elementor_data'), function (): void {});

        return $tree === null || $tree === [] ? null : $tree;
    }

    /**
     * @param  list<Block>  $tree
     */
    private function html(array $tree): string
    {
        return implode('', array_map(fn (Block $block): string => $block->innerHtml.$this->html($block->innerBlocks), $tree));
    }

    /**
     * @param  array<string, array{count: int, post_ids: list<int>}>  $shortcodes
     */
    private function countShortcodes(string $content, array &$shortcodes, int $id): void
    {
        if (! str_contains($content, '[')) {
            return;
        }

        $this->shortcodes->process($content, fn (): bool => false, fn (): string => '', function (string $tag) use (&$shortcodes, $id): void {
            if (! $this->registry->hasShortcode($tag)) {
                $this->count($shortcodes, $tag, $id);
            }
        });
    }

    /**
     * @param  list<Block>  $tree
     * @param  array<string, array{count: int, post_ids: list<int>}>  $blocks
     */
    private function countBlocks(array $tree, array &$blocks, int $postId): void
    {
        foreach ($tree as $block) {
            if (! $block->isFreeform() && $block->name !== 'core/block' && ! $this->renderer->hasRenderer($block->name)) {
                $this->count($blocks, $block->name, $postId);
            }

            $this->countBlocks($block->innerBlocks, $blocks, $postId);
        }
    }

    /**
     * @param  array<string, array{count: int, post_ids: list<int>}>  $items
     */
    private function count(array &$items, string $name, int $postId): void
    {
        $items[$name] ??= ['count' => 0, 'post_ids' => []];
        $items[$name]['count']++;

        if (count($items[$name]['post_ids']) < self::EXAMPLES && ! in_array($postId, $items[$name]['post_ids'], true)) {
            $items[$name]['post_ids'][] = $postId;
        }
    }

    /**
     * @return array<string, list<array{title: string, type: string}>>
     */
    private function pageTemplates(): array
    {
        $posts = $this->db->tableName('posts');
        $meta = $this->db->tableName('postmeta');

        $rows = $this->db->posts()
            ->join($meta, $meta.'.post_id', '=', $posts.'.ID')
            ->where($meta.'.meta_key', '_wp_page_template')
            ->whereNotIn($meta.'.meta_value', ['', 'default'])
            ->whereIn($posts.'.post_status', $this->config->array('wp-migrator.import.post_statuses'))
            ->orderBy($posts.'.ID')
            ->get([$meta.'.meta_value', $posts.'.post_title', $posts.'.post_type']);

        $templates = [];

        foreach ($rows as $row) {
            $templates[(string) $row->meta_value][] = ['title' => (string) $row->post_title, 'type' => (string) $row->post_type];
        }

        return $templates;
    }

    /**
     * @return list<string>
     */
    private function plugins(): array
    {
        $plugins = $this->decodedOption('active_plugins');

        return array_values(array_filter($plugins, is_string(...)));
    }

    /**
     * @return array<string, array{menu: string, items: int}>
     */
    private function menus(): array
    {
        $relationships = $this->db->tableName('term_relationships');
        $taxonomies = $this->db->tableName('term_taxonomy');
        $posts = $this->db->tableName('posts');

        $counts = $this->db->table('term_relationships')
            ->join($taxonomies, $taxonomies.'.term_taxonomy_id', '=', $relationships.'.term_taxonomy_id')
            ->join($posts, $posts.'.ID', '=', $relationships.'.object_id')
            ->where($taxonomies.'.taxonomy', 'nav_menu')
            ->where($posts.'.post_type', 'nav_menu_item')
            ->where($posts.'.post_status', 'publish')
            ->groupBy($taxonomies.'.term_id')
            ->select($taxonomies.'.term_id as menu')
            ->selectRaw('count(*) as items')
            ->pluck('items', 'menu');

        $menus = [];

        foreach ($this->menus->chunks() as $chunk) {
            foreach ($chunk as $menu) {
                foreach ($menu->locations as $location) {
                    $menus[$location] = ['menu' => $menu->name, 'items' => (int) ($counts[$menu->id] ?? 0)];
                }
            }
        }

        return $menus;
    }

    /**
     * @return array<string, int>
     */
    private function widgets(): array
    {
        $widgets = [];

        foreach ($this->decodedOption('sidebars_widgets') as $sidebar => $list) {
            if (is_string($sidebar) && $sidebar !== 'wp_inactive_widgets' && is_array($list)) {
                $widgets[$sidebar] = count($list);
            }
        }

        return $widgets;
    }

    /**
     * The slug of the page WP shows on the front page, when it shows one.
     */
    private function frontPage(): ?string
    {
        if ($this->db->option('show_on_front') !== 'page') {
            return null;
        }

        $slug = $this->db->posts()
            ->where('ID', (int) $this->db->option('page_on_front'))
            ->where('post_type', 'page')
            ->value('post_name');

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * The users with an author archive on the old site, by its slug, without
     * those the import leaves out.
     *
     * @return array<array-key, int>
     */
    private function authors(): array
    {
        $excluded = $this->excluded->ids();
        $authors = [];

        foreach ($this->stats->authors($this->config->array('wp-migrator.import.excluded_post_types'), $this->config->array('wp-migrator.import.post_statuses')) as $author) {
            if ($author['slug'] !== '' && ! in_array($author['id'], $excluded, true)) {
                $authors[$author['slug']] = $author['id'];
            }
        }

        ksort($authors, SORT_STRING);

        return $authors;
    }

    /**
     * @return list<array{login: string, registered: string|null, excluded: bool}>
     */
    private function administrators(): array
    {
        $excluded = $this->excluded->ids();

        return array_map(fn (array $user): array => [
            'login' => $user['login'],
            'registered' => $user['registered'],
            'excluded' => in_array($user['id'], $excluded, true),
        ], $this->stats->administrators());
    }

    /**
     * The query variables the rewrite rules lead to, and the post types they
     * name. A post type or taxonomy without one had no pages of its own.
     *
     * @return list<string>|null
     */
    private function queryVars(): ?array
    {
        $rules = $this->decodedOption('rewrite_rules');

        if ($rules === []) {
            return null;
        }

        $vars = [];

        foreach ($rules as $target) {
            if (! is_string($target)) {
                continue;
            }

            parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

            foreach ($query as $name => $value) {
                $vars[(string) $name] = true;

                if ($name === 'post_type' && is_string($value)) {
                    $vars[$value] = true;
                }
            }
        }

        $vars = array_keys($vars);
        sort($vars);

        return array_map(strval(...), $vars);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodedOption(string $name): array
    {
        try {
            $value = Serialized::decode((string) $this->db->option($name));
        } catch (MalformedSerializedValue) {
            return [];
        }

        return is_array($value) ? $value : [];
    }
}
