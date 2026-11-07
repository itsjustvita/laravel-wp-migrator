<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content;

use ItsJustVita\WpMigrator\Content\Blocks\Block;

/**
 * Renders embedded media. No oEmbed requests are made: the common video hosts
 * get a player built from the URL, using the privacy-friendly domain where
 * there is one. Everything else, including links within the site, becomes a
 * plain link.
 */
final class Embeds
{
    private const array YOUTUBE_HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'];

    private const array VIMEO_HOSTS = ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'];

    /**
     * @param  string|null  $caption  HTML
     */
    public static function render(string $url, ?string $caption = null): string
    {
        $url = trim($url);
        $figcaption = $caption === null || trim($caption) === '' ? '' : '<figcaption>'.$caption.'</figcaption>';

        if (preg_match('#^/(?!/)#', $url) === 1) {
            return self::link($url, $figcaption);
        }

        $parts = parse_url($url);

        if ($parts === false
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || ($parts['host'] ?? '') === '') {
            return '';
        }

        $player = self::playerUrl($parts);

        if ($player !== null) {
            return '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">'
                .'<iframe src="'.e($player).'" title="Embedded video" loading="lazy" allowfullscreen></iframe>'
                .'</div>'.$figcaption.'</figure>';
        }

        return self::link($url, $figcaption);
    }

    private static function link(string $url, string $figcaption): string
    {
        return '<figure class="wp-block-embed"><a href="'.e($url).'">'.e($url).'</a>'.$figcaption.'</figure>';
    }

    /**
     * A block's URL lives in its attributes, its caption in the stored HTML.
     * Without a usable URL the stored HTML is all there is.
     */
    public static function forBlock(Block $block, string $content): string
    {
        $url = $block->attr('url');

        if (! is_string($url) || $url === '') {
            return $content;
        }

        $caption = preg_match('/<figcaption\b[^>]*>(.*?)<\/figcaption>/is', $content, $matches) === 1 ? $matches[1] : null;
        $html = self::render($url, $caption);

        return $html === '' ? $content : $html;
    }

    /**
     * @param  array{host?: string, path?: string, query?: string}  $parts
     */
    private static function playerUrl(array $parts): ?string
    {
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        if ($host === 'youtu.be' && preg_match('#^/([\w-]+)#', $path, $matches) === 1) {
            return 'https://www.youtube-nocookie.com/embed/'.$matches[1];
        }

        if (in_array($host, self::YOUTUBE_HOSTS, true)) {
            parse_str($parts['query'] ?? '', $query);
            $id = $query['v'] ?? null;

            if (is_string($id) && preg_match('/^[\w-]+$/', $id) === 1) {
                return 'https://www.youtube-nocookie.com/embed/'.$id;
            }

            if (preg_match('#^/(?:embed|shorts|v)/([\w-]+)#', $path, $matches) === 1) {
                return 'https://www.youtube-nocookie.com/embed/'.$matches[1];
            }

            return null;
        }

        if (in_array($host, self::VIMEO_HOSTS, true) && preg_match('#^/(?:video/)?(\d+)#', $path, $matches) === 1) {
            return 'https://player.vimeo.com/video/'.$matches[1];
        }

        return null;
    }
}
