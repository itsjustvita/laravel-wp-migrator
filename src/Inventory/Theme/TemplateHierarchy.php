<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory\Theme;

/**
 * Places theme files in the template hierarchy by their names, the way WP
 * picks a template: single-{post type}.php, archive-{post type}.php,
 * page-{slug}.php, taxonomy-{taxonomy}-{term}.php and so on. Block themes
 * name their templates the same way in templates/*.html.
 */
final class TemplateHierarchy
{
    private const array PARTIALS = ['header', 'footer', 'sidebar', 'comments', 'searchform'];

    private const array PART_DIRECTORIES = ['template-parts', 'partials', 'parts'];

    private const array MIME_TYPES = ['image', 'video', 'audio', 'application', 'text'];

    /**
     * @param  list<string>  $postTypes  known post types, to split single-{type}-{slug} correctly
     * @param  list<string>  $taxonomies  known taxonomies, for taxonomy-{taxonomy}-{term}
     */
    public function __construct(
        private readonly array $postTypes = ['post', 'page'],
        private readonly array $taxonomies = ['category', 'post_tag'],
    ) {}

    /**
     * @param  string  $path  relative to the theme directory, with forward slashes
     * @param  string  $contents  the file's beginning, for page template headers
     * @param  array<string, array{title: string, postTypes: list<string>}>  $customTemplates  from theme.json
     */
    public function classify(string $path, string $contents = '', array $customTemplates = []): ?Template
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $directory = dirname($path);
        $base = pathinfo($path, PATHINFO_FILENAME);

        if ($extension === 'html') {
            return match ($directory) {
                'parts' => new Template($path, 'part', $base),
                'templates' => isset($customTemplates[$base])
                    ? new Template($path, 'custom', $customTemplates[$base]['title'], postTypes: $customTemplates[$base]['postTypes'])
                    : $this->byName($path, $base) ?? new Template($path, 'custom', $base, postTypes: ['page']),
                default => null,
            };
        }

        if ($extension !== 'php') {
            return null;
        }

        $custom = $this->customTemplate($path, $contents);

        if ($custom !== null) {
            return $custom;
        }

        if ($directory !== '.') {
            $top = explode('/', $directory)[0];

            return in_array($top, self::PART_DIRECTORIES, true)
                ? new Template($path, 'part', substr($path, 0, -4))
                : null;
        }

        return $this->byName($path, $base);
    }

    private function customTemplate(string $path, string $contents): ?Template
    {
        if (preg_match('/^[ \t\/*#@]*Template Name:(.*)$/mi', $contents, $matches) !== 1) {
            return null;
        }

        $postTypes = ['page'];

        if (preg_match('/^[ \t\/*#@]*Template Post Type:(.*)$/mi', $contents, $types) === 1) {
            $postTypes = array_values(array_filter(array_map(trim(...), explode(',', $types[1])), fn (string $type): bool => $type !== ''));
        }

        return new Template($path, 'custom', trim($matches[1]), postTypes: $postTypes);
    }

    private function byName(string $path, string $base): ?Template
    {
        foreach (self::PARTIALS as $partial) {
            if ($base === $partial || str_starts_with($base, $partial.'-')) {
                return new Template($path, 'partial', $partial, $this->rest($base, $partial));
            }
        }

        return match (true) {
            $base === 'index' => new Template($path, 'index'),
            $base === 'front-page' => new Template($path, 'front-page'),
            $base === 'home' => new Template($path, 'home'),
            $base === 'singular' => new Template($path, 'singular'),
            $base === 'single' => new Template($path, 'single', 'post'),
            str_starts_with($base, 'single-') => $this->split($path, 'single', substr($base, 7), $this->postTypes),
            $base === 'page' || str_starts_with($base, 'page-') => new Template($path, 'page', null, $this->rest($base, 'page')),
            $base === 'archive' => new Template($path, 'archive'),
            str_starts_with($base, 'archive-') => new Template($path, 'archive', substr($base, 8)),
            $base === 'category' || str_starts_with($base, 'category-') => new Template($path, 'term', 'category', $this->rest($base, 'category')),
            $base === 'tag' || str_starts_with($base, 'tag-') => new Template($path, 'term', 'post_tag', $this->rest($base, 'tag')),
            $base === 'taxonomy' => new Template($path, 'term'),
            str_starts_with($base, 'taxonomy-') => $this->split($path, 'term', substr($base, 9), $this->taxonomies),
            $base === 'author' || str_starts_with($base, 'author-') => new Template($path, 'author', null, $this->rest($base, 'author')),
            $base === 'date' => new Template($path, 'date'),
            $base === 'search' => new Template($path, 'search'),
            $base === '404' => new Template($path, 'not-found'),
            $base === 'attachment' => new Template($path, 'attachment'),
            in_array(explode('-', $base)[0], self::MIME_TYPES, true) => new Template($path, 'attachment', null, $base),
            default => null,
        };
    }

    /**
     * Splits "event-summer" into the known name "event" and the slug
     * "summer". Names themselves may contain dashes, so the longest known
     * name that fits wins.
     *
     * @param  list<string>  $known
     */
    private function split(string $path, string $kind, string $rest, array $known): Template
    {
        $match = null;

        foreach ($known as $name) {
            if (($rest === $name || str_starts_with($rest, $name.'-')) && strlen($name) > strlen($match ?? '')) {
                $match = $name;
            }
        }

        if ($match === null) {
            return new Template($path, $kind, $rest);
        }

        $detail = substr($rest, strlen($match) + 1);

        return new Template($path, $kind, $match, $detail === '' ? null : $detail);
    }

    private function rest(string $base, string $prefix): ?string
    {
        $rest = substr($base, strlen($prefix) + 1);

        return $rest === '' ? null : $rest;
    }
}
