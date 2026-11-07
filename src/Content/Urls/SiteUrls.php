<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Urls;

use Illuminate\Config\Repository;
use ItsJustVita\WpMigrator\Source\Uploads;
use ItsJustVita\WpMigrator\Source\WpDatabase;

/**
 * Knows what links to the old site look like: its hosts, with or without
 * "www.", and the paths the site and its uploads live under.
 */
class SiteUrls
{
    /** @var list<string>|null */
    private ?array $hosts = null;

    private ?string $homePath = null;

    /** @var list<string>|null */
    private ?array $uploadPaths = null;

    public function __construct(
        private readonly Repository $config,
        private readonly WpDatabase $db,
        private readonly Uploads $uploads,
    ) {}

    public function isOldHost(string $host): bool
    {
        return in_array($this->normaliseHost($host), $this->hosts(), true);
    }

    /**
     * The path the site lives under, without a trailing slash: "" or "/blog".
     */
    public function homePath(): string
    {
        if ($this->homePath !== null) {
            return $this->homePath;
        }

        $path = parse_url((string) $this->homeUrl(), PHP_URL_PATH);

        return $this->homePath = is_string($path) ? rtrim($path, '/') : '';
    }

    /**
     * The paths uploads live under, without a trailing slash.
     *
     * @return list<string>
     */
    public function uploadPaths(): array
    {
        if ($this->uploadPaths !== null) {
            return $this->uploadPaths;
        }

        $paths = [$this->homePath().'/wp-content/uploads'];

        $base = $this->uploads->baseUrl();
        $path = $base === null ? null : parse_url($base, PHP_URL_PATH);

        if (is_string($path) && rtrim($path, '/') !== '') {
            array_unshift($paths, rtrim($path, '/'));
        }

        return $this->uploadPaths = array_values(array_unique($paths));
    }

    private function homeUrl(): ?string
    {
        $configured = $this->config->get('wp-migrator.source.site_url');

        return is_string($configured) && $configured !== '' ? $configured : $this->db->optionOrNull('home');
    }

    /**
     * @return list<string>
     */
    private function hosts(): array
    {
        if ($this->hosts !== null) {
            return $this->hosts;
        }

        $extra = $this->config->get('wp-migrator.urls.old_hosts', []);

        $urls = [
            $this->homeUrl(),
            $this->db->optionOrNull('siteurl'),
            $this->uploads->baseUrl(),
            ...(is_array($extra) ? $extra : []),
        ];

        $hosts = [];

        foreach ($urls as $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }

            $host = parse_url(str_contains($url, '//') ? $url : '//'.$url, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                $hosts[] = $this->normaliseHost($host);
            }
        }

        return $this->hosts = array_values(array_unique($hosts));
    }

    private function normaliseHost(string $host): string
    {
        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
