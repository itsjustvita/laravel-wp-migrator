<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Urls;

use Illuminate\Config\Repository;
use ItsJustVita\WpMigrator\Models\Media;

/**
 * Finds the new URL of a file from the old uploads directory, including
 * resized variants. A size that no longer exists falls back to the original.
 *
 * URLs on the application's own host become root-relative, so stored content
 * keeps working when the site moves from staging to its final domain.
 */
class MediaUrls
{
    /** @var array<string, string|null> */
    private array $found = [];

    public function __construct(private readonly Repository $config) {}

    /**
     * @param  string  $file  path relative to the old uploads directory
     */
    public function find(string $file): ?string
    {
        $file = rawurldecode($file);

        if (array_key_exists($file, $this->found)) {
            return $this->found[$file];
        }

        return $this->found[$file] = $this->lookup($file);
    }

    /**
     * The URL of a media record or one of its sizes, in the form stored in content.
     */
    public function of(Media $media, ?string $size = null): string
    {
        return $this->portable($media->url($size));
    }

    private function lookup(string $file): ?string
    {
        $media = $this->byFile($file);

        if ($media !== null) {
            return $this->of($media);
        }

        if (preg_match('/^(.*)-\d+x\d+(\.[^.\/]+)$/', $file, $matches) === 1) {
            $media = $this->byFile($matches[1].$matches[2]) ?? $this->byFile($matches[1].'-scaled'.$matches[2]);

            if ($media === null) {
                return null;
            }

            foreach (array_keys($media->variants ?? []) as $size) {
                if (basename($media->variantPath((string) $size) ?? '') === basename($file)) {
                    return $this->of($media, (string) $size);
                }
            }

            return $this->of($media);
        }

        if (preg_match('/^(.*)(\.[^.\/]+)$/', $file, $matches) === 1) {
            $media = $this->byFile($matches[1].'-scaled'.$matches[2]);

            return $media === null ? null : $this->of($media);
        }

        return null;
    }

    private function byFile(string $file): ?Media
    {
        return Media::query()
            ->where('disk', $this->config->string('wp-migrator.media.disk', 'public'))
            ->where('path', Media::pathFor($file))
            ->first();
    }

    private function portable(string $url): string
    {
        $url = str_replace(' ', '%20', $url);
        $appUrl = $this->config->get('app.url');

        if (is_string($appUrl) && $appUrl !== '') {
            $prefix = rtrim($appUrl, '/').'/';

            if (str_starts_with($url, $prefix)) {
                return '/'.substr($url, strlen($prefix));
            }
        }

        return $url;
    }
}
