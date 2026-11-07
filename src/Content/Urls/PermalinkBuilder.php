<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Urls;

/**
 * Computes the path a post had on the old site from the permalink_structure
 * option. Paths have a leading slash and no trailing slash. Plain permalinks
 * have no path, those posts are only reachable by ?p=ID.
 */
final readonly class PermalinkBuilder
{
    /**
     * @param  array<string, string>  $postTypeSlugs  known rewrite slugs per custom post type
     */
    public function __construct(
        private string $structure,
        private array $postTypeSlugs = [],
    ) {}

    public function path(PostReference $post): ?string
    {
        if ($this->structure === '') {
            return null;
        }

        return match ($post->type) {
            'post' => $this->postPath($post),
            'page' => $post->path === '' ? null : '/'.$post->path,
            default => $this->customPostTypePath($post),
        };
    }

    private function postPath(PostReference $post): ?string
    {
        $date = $post->date !== null && preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/', $post->date, $matches) === 1 && $matches[1] !== '0000'
            ? $matches
            : null;

        $replacements = [
            '%postname%' => $post->slug,
            '%post_id%' => (string) $post->id,
            '%category%' => $post->category ?? '',
            '%author%' => $post->author ?? '',
            '%year%' => $date[1] ?? '',
            '%monthnum%' => $date[2] ?? '',
            '%day%' => $date[3] ?? '',
            '%hour%' => $date[4] ?? '',
            '%minute%' => $date[5] ?? '',
            '%second%' => $date[6] ?? '',
        ];

        foreach ($replacements as $tag => $value) {
            if ($value === '' && str_contains($this->structure, $tag)) {
                return null;
            }
        }

        $path = strtr($this->structure, $replacements);

        if (preg_match('/%[a-z_]+%/', $path) === 1) {
            return null;
        }

        return '/'.trim($path, '/');
    }

    private function customPostTypePath(PostReference $post): ?string
    {
        if ($post->path === '') {
            return null;
        }

        $slug = $this->postTypeSlugs[$post->type] ?? null;

        if ($slug !== null) {
            return '/'.trim($slug, '/').'/'.$post->path;
        }

        $front = trim($this->front(), '/');

        return '/'.($front === '' ? '' : $front.'/').$post->type.'/'.$post->path;
    }

    /**
     * The static start of the structure: "/blog" for /blog/%year%/%postname%/.
     */
    private function front(): string
    {
        $tag = strpos($this->structure, '%');

        return $tag === false ? $this->structure : substr($this->structure, 0, $tag);
    }
}
