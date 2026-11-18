<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Console\Concerns;

use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use ItsJustVita\WpMigrator\Inventory\SiteFacts;
use ItsJustVita\WpMigrator\Inventory\SiteInventory;
use ItsJustVita\WpMigrator\Inventory\Theme\InventoryException;
use ItsJustVita\WpMigrator\Inventory\Theme\ThemeInventory;
use ItsJustVita\WpMigrator\Inventory\Theme\ThemeScanner;

/**
 * Reads the source site and scans its theme for commands with the options
 * --theme and --child.
 */
trait ScansTheme
{
    /**
     * @param  string  $withoutDatabase  what the command does when the source database cannot be read
     * @return array{0: ThemeInventory, 1: SiteFacts, 2: string}|null the theme, the site and the theme directory
     */
    protected function scanSiteAndTheme(SiteInventory $inventory, ThemeScanner $scanner, Repository $config, Filesystem $files, string $withoutDatabase): ?array
    {
        $site = $inventory->read();

        if (! $site->reachable) {
            $this->components->warn(sprintf('The source database could not be read (%s). %s', $site->error, $withoutDatabase));
        }

        $paths = $this->themePaths($site, $config, $files);

        if ($paths === null) {
            return null;
        }

        [$themePath, $childPath] = $paths;

        $this->components->twoColumnDetail('Theme', $themePath);

        if ($childPath !== null) {
            $this->components->twoColumnDetail('Child theme', $childPath);
        }

        try {
            $theme = $scanner->scan($themePath, $childPath, array_keys($site->postCounts), array_keys($site->taxonomies));
        } catch (InventoryException $exception) {
            $this->components->error($exception->getMessage());

            return null;
        }

        return [$theme, $site, $themePath];
    }

    /**
     * The theme and child theme to scan: the options, or the active theme
     * in the themes directory next to the uploads directory.
     *
     * @return array{0: string, 1: string|null}|null
     */
    private function themePaths(SiteFacts $site, Repository $config, Filesystem $files): ?array
    {
        $theme = $this->stringOption('theme');
        $child = $this->stringOption('child');

        if ($theme !== null) {
            return [$theme, $child ?? $this->childNextTo($theme, $site, $files)];
        }

        $uploads = $config->get('wp-migrator.source.uploads_path');
        $template = $this->themeSlug($site->template);

        if (is_string($uploads) && $uploads !== '' && $template !== null) {
            $themes = dirname($uploads).'/themes';

            if ($files->isDirectory($themes.'/'.$template)) {
                return [$themes.'/'.$template, $child ?? $this->childNextTo($themes.'/'.$template, $site, $files)];
            }
        }

        $this->components->error(sprintf(
            'The theme could not be found%s. Pass its directory with --theme, and that of a child theme with --child.',
            $template === null ? '' : sprintf(' (the site uses "%s")', $template),
        ));

        return null;
    }

    /**
     * The active child theme, when it lies next to the scanned theme.
     */
    private function childNextTo(string $theme, SiteFacts $site, Filesystem $files): ?string
    {
        $stylesheet = $this->themeSlug($site->stylesheet);

        if ($stylesheet === null || $stylesheet === $this->themeSlug($site->template) || basename($theme) !== $site->template) {
            return null;
        }

        $child = dirname($theme).'/'.$stylesheet;

        return $files->isDirectory($child) ? $child : null;
    }

    /**
     * A theme directory name from the options table, never a path.
     */
    private function themeSlug(?string $value): ?string
    {
        return $value !== null && preg_match('/^[A-Za-z0-9._-]+$/', $value) === 1 && trim($value, '.') !== '' ? $value : null;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
