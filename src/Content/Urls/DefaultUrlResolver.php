<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Urls;

use Illuminate\Config\Repository;

/**
 * Builds URLs from the patterns in wp-migrator.urls.patterns, chosen by post
 * type with "*" as the fallback. Tokens: {slug}, {path}, {type}, {id}, {year},
 * {month}, {day}.
 */
class DefaultUrlResolver implements UrlResolver
{
    public function __construct(private readonly Repository $config) {}

    public function forPost(PostReference $post): ?string
    {
        $patterns = $this->config->get('wp-migrator.urls.patterns', []);
        $pattern = is_array($patterns) ? ($patterns[$post->type] ?? $patterns['*'] ?? null) : null;

        if (! is_string($pattern)) {
            return null;
        }

        $date = $post->date !== null && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $post->date, $matches) === 1 ? $matches : [];

        $url = strtr($pattern, [
            '{slug}' => $this->encode($post->slug),
            '{path}' => $this->encode($post->path),
            '{type}' => $this->encode($post->type),
            '{id}' => (string) $post->id,
            '{year}' => $date[1] ?? '',
            '{month}' => $date[2] ?? '',
            '{day}' => $date[3] ?? '',
        ]);

        return (string) preg_replace('#(?<!:)/{2,}#', '/', $url);
    }

    private function encode(string $path): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }
}
