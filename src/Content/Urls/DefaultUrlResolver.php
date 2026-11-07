<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Urls;

use Illuminate\Config\Repository;

/**
 * Builds URLs from patterns: wp-migrator.urls.patterns for posts, chosen by
 * post type with "*" as the fallback (tokens {slug}, {path}, {type}, {id},
 * {year}, {month}, {day}), urls.term_patterns per taxonomy (tokens {slug},
 * {path}, {taxonomy}, {id}) and urls.author_pattern (tokens {slug}, {id}).
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

        return $this->fill($pattern, [
            '{slug}' => $this->encode($post->slug),
            '{path}' => $this->encode($post->path),
            '{type}' => $this->encode($post->type),
            '{id}' => (string) $post->id,
            '{year}' => $date[1] ?? '',
            '{month}' => $date[2] ?? '',
            '{day}' => $date[3] ?? '',
        ]);
    }

    public function forTerm(TermReference $term): ?string
    {
        $patterns = $this->config->get('wp-migrator.urls.term_patterns', []);
        $pattern = is_array($patterns) ? ($patterns[$term->taxonomy] ?? null) : null;

        if (! is_string($pattern)) {
            return null;
        }

        return $this->fill($pattern, [
            '{slug}' => $this->encode($term->slug),
            '{path}' => $this->encode($term->path),
            '{taxonomy}' => $this->encode($term->taxonomy),
            '{id}' => (string) $term->id,
        ]);
    }

    public function forAuthor(AuthorReference $author): ?string
    {
        $pattern = $this->config->get('wp-migrator.urls.author_pattern');

        if (! is_string($pattern)) {
            return null;
        }

        return $this->fill($pattern, [
            '{slug}' => $this->encode($author->nicename),
            '{id}' => (string) $author->id,
        ]);
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function fill(string $pattern, array $tokens): string
    {
        return (string) preg_replace('#(?<!:)/{2,}#', '/', strtr($pattern, $tokens));
    }

    private function encode(string $path): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }
}
