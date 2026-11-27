<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory;

use Illuminate\Config\Repository;
use Illuminate\Support\Str;
use ItsJustVita\WpMigrator\Inventory\Theme\PostTypeDefinition;
use ItsJustVita\WpMigrator\Inventory\Theme\Template;
use ItsJustVita\WpMigrator\Inventory\Theme\ThemeInventory;
use ItsJustVita\WpMigrator\Mapping\DefaultUserMapper;

/**
 * The routes, views and layouts the site needs in Laravel, worked out from
 * the theme, the source site and the URL patterns. The migration plan
 * describes them and wp:scaffold writes them, so both always agree.
 */
final class Frontend
{
    private const array NOT_CONTENT = ['attachment', 'nav_menu_item', 'revision'];

    private const array NO_ARCHIVE = ['post_format'];

    private const array BUILT_IN_TAXONOMIES = ['category', 'post_tag'];

    public function __construct(
        public readonly ThemeInventory $theme,
        public readonly SiteFacts $site,
        private readonly Repository $config,
    ) {}

    public function front(): FrontendItem
    {
        return new FrontendItem('home', 'Front page', null, '/', 'home', $this->find('front-page') ?? $this->find('home') ?? $this->find('index'));
    }

    /**
     * Content post types: those of the theme, those with posts, and posts and pages.
     *
     * @return list<string>
     */
    public function postTypes(): array
    {
        $types = ['page', 'post'];

        foreach ($this->theme->postTypes as $definition) {
            $types[] = $definition->name;
        }

        foreach (array_keys($this->contentCounts()) as $type) {
            $types[] = $type;
        }

        return array_values(array_unique($types));
    }

    /**
     * The post types that get routes: those with pages of their own, and
     * posts only when the site has some.
     *
     * @return list<string>
     */
    public function routedTypes(): array
    {
        return array_values(array_filter(
            $this->postTypes(),
            fn (string $type): bool => $this->hasPages($type) && ! ($type === 'post' && $this->site->reachable && ! isset($this->site->postCounts['post'])),
        ));
    }

    public function single(string $type): FrontendItem
    {
        return new FrontendItem('single', $this->label($type), $type, $this->postRoute($type), $this->plural($type).'.show', $this->singleTemplate($type));
    }

    public function archive(string $type): ?FrontendItem
    {
        $template = $this->archiveTemplate($type);

        if ($template === false) {
            return null;
        }

        $plural = $this->plural($type);

        return new FrontendItem('archive', $this->label($type), $type, $this->archiveRoute($this->postRoute($type), $plural), $plural.'.index', $template);
    }

    /**
     * Pages and posts the theme has a template of their own for.
     *
     * @return list<FrontendItem>
     */
    public function specific(): array
    {
        $items = [];

        foreach ($this->theme->templates as $template) {
            if (! in_array($template->kind, ['single', 'page'], true) || $template->detail === null) {
                continue;
            }

            $type = $template->kind === 'page' ? 'page' : ($template->subject ?? 'post');
            $route = $this->postRoute($type);

            $items[] = new FrontendItem(
                'specific',
                ($type === 'page' ? 'Page' : Str::headline($type)).' "'.$template->detail.'"',
                $type,
                $route === null ? null : strtr($route, ['{path}' => $template->detail, '{slug}' => $template->detail]),
                $this->plural($type).'.'.$template->detail,
                $template,
                $template->detail,
            );
        }

        return $items;
    }

    /**
     * A layout for each page template. Classic themes store the file in
     * posts.template, block themes the template's slug.
     *
     * @return list<FrontendItem>
     */
    public function layouts(): array
    {
        $items = [];

        foreach ($this->theme->templates as $template) {
            if ($template->kind !== 'custom') {
                continue;
            }

            $stored = str_ends_with($template->path, '.html') ? pathinfo($template->path, PATHINFO_FILENAME) : $template->path;

            $items[] = new FrontendItem('layout', 'Page template "'.$template->subject.'"', $stored, null, 'layouts.'.Str::slug((string) $template->subject), $template);
        }

        return $items;
    }

    /**
     * The archives of taxonomies the redirects or menus lead to, or the
     * theme has a template for.
     *
     * @return list<FrontendItem>
     */
    public function termArchives(): array
    {
        $taxonomies = ['category' => 'Category', 'post_tag' => 'Tag'];

        foreach (array_keys($this->site->taxonomies) as $taxonomy) {
            $taxonomies[$taxonomy] ??= Str::headline($taxonomy);
        }

        foreach ($this->theme->taxonomies as $taxonomy) {
            $taxonomies[$taxonomy->name] = $taxonomy->label ?? Str::headline($taxonomy->name);
        }

        $patterns = $this->config->get('wp-migrator.urls.term_patterns', []);
        $patterns = is_array($patterns) ? $patterns : [];
        $items = [];

        foreach ($taxonomies as $taxonomy => $label) {
            $specific = $this->find('term', $taxonomy);
            $builtIn = $this->builtInTaxonomy($taxonomy);
            $pattern = $patterns[$taxonomy] ?? null;
            $pattern = is_string($pattern) ? $pattern : null;

            if (in_array($taxonomy, self::NO_ARCHIVE, true) || ! $this->needsArchive($taxonomy, $specific !== null, $pattern !== null)) {
                continue;
            }

            // A taxonomy of a plugin that had no archive URLs on the site.
            if (! $builtIn && ! $this->themeTaxonomy($taxonomy) && $this->site->queryVars !== null && ! in_array($taxonomy, $this->site->queryVars, true)) {
                continue;
            }

            $items[] = new FrontendItem(
                'term',
                Str::singular($label).' archive',
                $taxonomy,
                $pattern,
                $this->termPlural($taxonomy).'.show',
                $specific ?? ($builtIn ? null : $this->find('term')) ?? $this->find('archive') ?? $this->find('index'),
            );
        }

        return $items;
    }

    public function authorArchive(): ?FrontendItem
    {
        $pattern = $this->config->get('wp-migrator.urls.author_pattern');
        $author = $this->find('author');

        if (! is_string($pattern) && $author === null) {
            return null;
        }

        return new FrontendItem('author', 'Author archive', null, is_string($pattern) ? $pattern : null, 'authors.show', $author ?? $this->find('archive') ?? $this->find('index'));
    }

    /**
     * The attribute of the user model the import writes the display name to.
     */
    public function authorNameAttribute(): ?string
    {
        $attributes = $this->config->get('wp-migrator.users.attributes');
        $attribute = array_search('display_name', is_array($attributes) ? $attributes : DefaultUserMapper::DEFAULT_ATTRIBUTES, true);

        return is_string($attribute) && $attribute !== '' ? $attribute : null;
    }

    public function search(): ?FrontendItem
    {
        $template = $this->find('search');

        return $template === null ? null : new FrontendItem('search', 'Search', null, '/search', 'search', $template);
    }

    public function notFound(): ?FrontendItem
    {
        $template = $this->find('not-found');

        return $template === null ? null : new FrontendItem('not-found', 'Page not found', null, null, 'errors.404', $template);
    }

    public function label(string $type): string
    {
        return $this->definition($type)->label ?? match ($type) {
            'post' => 'Posts',
            'page' => 'Pages',
            default => Str::headline($type),
        };
    }

    public function plural(string $type): string
    {
        return Str::plural(str_replace('_', '-', $type));
    }

    public function termPlural(string $taxonomy): string
    {
        return match ($taxonomy) {
            'category' => 'categories',
            'post_tag' => 'tags',
            default => Str::plural(str_replace('_', '-', $taxonomy)),
        };
    }

    public function builtInTaxonomy(string $taxonomy): bool
    {
        return in_array($taxonomy, self::BUILT_IN_TAXONOMIES, true);
    }

    /**
     * Whether the posts of a type had pages of their own: the theme did not
     * register it as not public, and the rewrite rules lead to it.
     */
    public function hasPages(string $type): bool
    {
        if (in_array($type, ['page', 'post'], true)) {
            return true;
        }

        $definition = $this->definition($type);

        if ($definition !== null) {
            return $definition->public !== false;
        }

        return $this->site->queryVars === null || in_array($type, $this->site->queryVars, true);
    }

    public function definition(string $type): ?PostTypeDefinition
    {
        foreach ($this->theme->postTypes as $definition) {
            if ($definition->name === $type) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function contentCounts(): array
    {
        $excluded = [...self::NOT_CONTENT, ...$this->config->array('wp-migrator.import.excluded_post_types')];

        return array_filter(
            $this->site->postCounts,
            fn (string $type): bool => ! in_array($type, $excluded, true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function postRoute(string $type): ?string
    {
        $patterns = $this->config->get('wp-migrator.urls.patterns', []);
        $pattern = is_array($patterns) ? ($patterns[$type] ?? $patterns['*'] ?? null) : null;

        return is_string($pattern) ? str_replace('{type}', $type, $pattern) : null;
    }

    /**
     * The template of a kind for one subject, or the general one without a subject.
     */
    public function find(string $kind, ?string $subject = null): ?Template
    {
        foreach ($this->theme->templates as $template) {
            if ($template->kind === $kind && $template->detail === null && $template->subject === $subject) {
                return $template;
            }
        }

        return null;
    }

    private function archiveRoute(?string $route, string $plural): string
    {
        if ($route === null) {
            return '/'.$plural;
        }

        $prefix = (string) preg_replace('#/[^/]*\{[a-z]+\}.*$#', '', $route);

        return $prefix === '' ? '/'.$plural : $prefix;
    }

    private function singleTemplate(string $type): ?Template
    {
        foreach ($this->theme->templates as $template) {
            if ($template->kind === 'single' && $template->subject === $type && $template->detail === null) {
                return $template;
            }

            if ($type === 'page' && $template->kind === 'page' && $template->detail === null) {
                return $template;
            }
        }

        return ($type === 'page' ? null : $this->find('single', 'post')) ?? $this->find('singular') ?? $this->find('index');
    }

    private function archiveTemplate(string $type): Template|false|null
    {
        if ($type === 'post') {
            return $this->find('home') ?? $this->find('index');
        }

        $specific = $this->find('archive', $type);

        if ($specific !== null) {
            return $specific;
        }

        $definition = $this->definition($type);

        return $definition !== null && $definition->hasArchive !== null && $definition->hasArchive !== false
            ? ($this->find('archive') ?? $this->find('index'))
            : false;
    }

    private function themeTaxonomy(string $taxonomy): bool
    {
        foreach ($this->theme->taxonomies as $definition) {
            if ($definition->name === $taxonomy) {
                return true;
            }
        }

        return false;
    }

    /**
     * Archives the new site needs: those the redirects lead to, and those
     * the theme has its own template for, unless the site has no terms.
     */
    private function needsArchive(string $taxonomy, bool $hasTemplate, bool $hasPattern): bool
    {
        if ($this->site->reachable && ($this->site->taxonomies[$taxonomy] ?? 0) === 0) {
            return false;
        }

        return $hasTemplate || $hasPattern || ! $this->builtInTaxonomy($taxonomy);
    }
}
