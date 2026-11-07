<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Urls;

use ItsJustVita\WpMigrator\Import\ImportContext;

/**
 * Rewrites links to the old site: upload URLs point to the new media files and
 * post links to the routes from the URL resolver. The text gets one single
 * pass, so a new URL is never rewritten again. What cannot be resolved stays
 * in place and is reported.
 */
class UrlRewriter
{
    private ?string $pattern = null;

    public function __construct(
        private readonly SiteUrls $site,
        private readonly MediaUrls $media,
        private readonly PostUrlIndex $posts,
        private readonly UrlResolver $resolver,
    ) {}

    public function rewrite(string $text, int $postId, ImportContext $context): string
    {
        if ($text === '') {
            return '';
        }

        return preg_replace_callback(
            $this->pattern(),
            fn (array $matches): string => $this->replaceMatch($matches, $postId, $context),
            $text,
        ) ?? $text;
    }

    /**
     * Three kinds of URLs: absolute ones (their host is checked afterwards),
     * root-relative ones in link attributes, and root-relative upload paths
     * wherever they appear, as in srcset or CSS. Square brackets end a URL,
     * because shortcodes may follow right after it.
     */
    private function pattern(): string
    {
        if ($this->pattern !== null) {
            return $this->pattern;
        }

        $uploads = implode('|', array_map(fn (string $path): string => preg_quote($path, '~'), $this->site->uploadPaths()));

        return $this->pattern = '~(?<![\w.:/-])(?<absolute>(?:https?:)?//[^\s"\'<>()\[\]\\\\]+)'
            .'|(?<=\s)(?<attribute>(?:href|src|poster)\s*=\s*)(?<quote>["\'])(?<relative>/(?!/)[^"\'\s>]*)(?P=quote)'
            .'|(?<![\w.:/-])(?<upload>(?:'.$uploads.')/[^\s"\'<>()\[\]\\\\]+)~i';
    }

    /**
     * @param  array<array-key, string>  $matches
     */
    private function replaceMatch(array $matches, int $postId, ImportContext $context): string
    {
        if (($matches['attribute'] ?? '') !== '') {
            $url = $this->resolveRelative($matches['relative'], $postId, $context);

            return $matches['attribute'].$matches['quote'].$url.$matches['quote'];
        }

        if (($matches['upload'] ?? '') !== '') {
            [$url, $trailing] = $this->splitTrailing($matches['upload']);

            return $this->resolveRelative($url, $postId, $context).$trailing;
        }

        [$url, $trailing] = $this->splitTrailing($matches['absolute']);

        return $this->resolveAbsolute($url, $postId, $context).$trailing;
    }

    /**
     * Punctuation after a URL in running text does not belong to it.
     *
     * @return array{0: string, 1: string}
     */
    private function splitTrailing(string $url): array
    {
        $trimmed = rtrim($url, '.,;:!?');

        return [$trimmed, substr($url, strlen($trimmed))];
    }

    private function resolveAbsolute(string $url, int $postId, ImportContext $context): string
    {
        if (preg_match('~^(?:https?:)?//([^/?#:]+)(?::\d+)?([^?#]*)(.*)$~is', $url, $matches) !== 1
            || ! $this->site->isOldHost($matches[1])) {
            return $url;
        }

        return $this->resolve($matches[2], $matches[3], $url, true, $postId, $context);
    }

    private function resolveRelative(string $url, int $postId, ImportContext $context): string
    {
        if (preg_match('~^([^?#]*)(.*)$~s', $url, $matches) !== 1) {
            return $url;
        }

        return $this->resolve($matches[1], $matches[2], $url, false, $postId, $context);
    }

    /**
     * @param  string  $suffix  query string and fragment, as written
     */
    private function resolve(string $path, string $suffix, string $original, bool $absolute, int $postId, ImportContext $context): string
    {
        foreach ($this->site->uploadPaths() as $uploads) {
            if (str_starts_with($path, $uploads.'/')) {
                $found = $this->media->find(substr($path, strlen($uploads) + 1));

                if ($found === null) {
                    $context->report->unresolved($context->importer, $postId, 'media url', $original);
                    $context->markIncomplete();

                    return $original;
                }

                return $found;
            }
        }

        $home = $this->site->homePath();

        if ($path !== $home && $path !== $home.'/' && ! str_starts_with($path, $home.'/')) {
            return $original;
        }

        $local = substr($path, strlen($home));
        [$query, $fragment] = $this->splitSuffix($suffix);

        if ($local === '' || $local === '/') {
            return $this->resolveFrontPage($query, $fragment, $original, $absolute, $postId, $context);
        }

        $reference = $this->posts->find($local);

        return $this->linkTo($reference, $query, $fragment, $original, $postId, $context);
    }

    private function resolveFrontPage(string $query, string $fragment, string $original, bool $absolute, int $postId, ImportContext $context): string
    {
        foreach (['p', 'page_id'] as $parameter) {
            $id = $this->queryValue($query, $parameter);

            if ($id !== null) {
                return $this->linkTo($this->posts->findById($id), $this->withoutParameter($query, $parameter), $fragment, $original, $postId, $context);
            }
        }

        return $absolute ? '/'.$query.$fragment : $original;
    }

    private function linkTo(?PostReference $reference, string $query, string $fragment, string $original, int $postId, ImportContext $context): string
    {
        $url = $reference === null ? null : $this->resolver->forPost($reference);

        if ($url === null) {
            $context->report->unresolved($context->importer, $postId, 'link', $original);

            return $original;
        }

        return $url.$query.$fragment;
    }

    /**
     * @return array{0: string, 1: string} the query with its "?" and the fragment with its "#"
     */
    private function splitSuffix(string $suffix): array
    {
        $hash = strpos($suffix, '#');

        if ($hash === false) {
            return [$suffix, ''];
        }

        return [substr($suffix, 0, $hash), substr($suffix, $hash)];
    }

    private function queryValue(string $query, string $parameter): ?int
    {
        if (preg_match('/[?&;]'.preg_quote($parameter, '/').'=(\d+)(?:&|$)/', $query, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function withoutParameter(string $query, string $parameter): string
    {
        $pairs = preg_split('/&amp;|&/', ltrim($query, '?')) ?: [];
        $separator = str_contains($query, '&amp;') ? '&amp;' : '&';

        $remaining = array_filter($pairs, fn (string $pair): bool => ! str_starts_with($pair, $parameter.'='));

        return $remaining === [] ? '' : '?'.implode($separator, $remaining);
    }
}
