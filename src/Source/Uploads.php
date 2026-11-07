<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source;

use Illuminate\Config\Repository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

/**
 * Finds the bytes of an uploaded file: in the local copy of the uploads
 * directory first, then on the old site.
 */
class Uploads
{
    private ?string $baseUrl = null;

    private bool $baseUrlResolved = false;

    public function __construct(
        private readonly Repository $config,
        private readonly HttpFactory $http,
        private readonly WpDatabase $db,
    ) {}

    public function localPath(string $file): ?string
    {
        $base = $this->config->get('wp-migrator.source.uploads_path');

        if (! is_string($base) || $base === '') {
            return null;
        }

        $path = rtrim($base, '/').'/'.$file;

        return is_file($path) && is_readable($path) ? $path : null;
    }

    /**
     * The public URL of the uploads directory on the old site: the configured
     * uploads_url, the upload_url_path option, or the site URL plus
     * /wp-content/uploads.
     */
    public function baseUrl(): ?string
    {
        if ($this->baseUrlResolved) {
            return $this->baseUrl;
        }

        $this->baseUrlResolved = true;

        $configured = $this->config->get('wp-migrator.source.uploads_url');

        if (is_string($configured) && $configured !== '') {
            return $this->baseUrl = rtrim($configured, '/');
        }

        $siteUrl = $this->config->get('wp-migrator.source.site_url');
        $siteUrl = is_string($siteUrl) && $siteUrl !== '' ? $siteUrl : null;

        if ($siteUrl === null) {
            $optionPath = $this->db->optionOrNull('upload_url_path');

            if ($optionPath !== null && $optionPath !== '') {
                return $this->baseUrl = rtrim($optionPath, '/');
            }

            $siteUrl = $this->db->optionOrNull('siteurl') ?? $this->db->optionOrNull('home');
        }

        return $this->baseUrl = $siteUrl === null || $siteUrl === '' ? null : rtrim($siteUrl, '/').'/wp-content/uploads';
    }

    public function url(string $file): ?string
    {
        $base = $this->baseUrl();

        if ($base === null) {
            return null;
        }

        return $base.'/'.implode('/', array_map(rawurlencode(...), explode('/', $file)));
    }

    /**
     * A readable stream of the file, or null when it is found nowhere.
     *
     * @return resource|null
     */
    public function open(string $file)
    {
        $local = $this->localPath($file);

        if ($local !== null) {
            $stream = fopen($local, 'rb');

            return $stream === false ? null : $stream;
        }

        return $this->download($file);
    }

    /**
     * @return resource|null
     */
    private function download(string $file)
    {
        if (! $this->config->boolean('wp-migrator.media.download_missing', true)) {
            return null;
        }

        $url = $this->url($file);

        if ($url === null) {
            return null;
        }

        try {
            $response = $this->http
                ->timeout($this->config->integer('wp-migrator.media.download_timeout', 30))
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->toPsrResponse()->getBody();
        $body->rewind();

        return $body->detach();
    }
}
