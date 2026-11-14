<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Console;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use ItsJustVita\WpMigrator\Inventory\MigrationPlan;
use ItsJustVita\WpMigrator\Inventory\SiteFacts;
use ItsJustVita\WpMigrator\Inventory\SiteInventory;
use ItsJustVita\WpMigrator\Inventory\Theme\InventoryException;
use ItsJustVita\WpMigrator\Inventory\Theme\ThemeScanner;

class InventoryCommand extends Command
{
    protected $signature = 'wp:inventory
        {--theme= : Directory of the theme; defaults to the active theme next to the uploads directory}
        {--child= : Directory of the child theme}
        {--output= : Where to write the plan; defaults to docs/wp-migration-plan.md}
        {--force : Overwrite an existing plan}';

    protected $description = 'Scan the theme and the source database and write a migration plan';

    public function handle(
        SiteInventory $inventory,
        ThemeScanner $scanner,
        MigrationPlan $plan,
        Repository $config,
        Filesystem $files,
    ): int {
        $output = $this->stringOption('output') ?? base_path('docs/wp-migration-plan.md');

        if ($files->exists($output) && ! $this->option('force')) {
            $this->components->error(sprintf('%s already exists. Pass --force to replace it, ticked boxes included.', $output));

            return self::FAILURE;
        }

        $site = $inventory->read();

        if (! $site->reachable) {
            $this->components->warn(sprintf('The source database could not be read (%s). The plan is written from the theme alone.', $site->error));
        }

        $paths = $this->themePaths($site, $config, $files);

        if ($paths === null) {
            return self::FAILURE;
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

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($output));
        $files->put($output, $plan->render($theme, $site));

        foreach ($theme->warnings as $warning) {
            $this->components->warn($warning);
        }

        $this->components->info(sprintf('Migration plan written to %s', $output));

        return self::SUCCESS;
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
        $template = $this->slug($site->template);

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
        $stylesheet = $this->slug($site->stylesheet);

        if ($stylesheet === null || $stylesheet === $this->slug($site->template) || basename($theme) !== $site->template) {
            return null;
        }

        $child = dirname($theme).'/'.$stylesheet;

        return $files->isDirectory($child) ? $child : null;
    }

    /**
     * A theme directory name from the options table, never a path.
     */
    private function slug(?string $value): ?string
    {
        return $value !== null && preg_match('/^[A-Za-z0-9._-]+$/', $value) === 1 && trim($value, '.') !== '' ? $value : null;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
