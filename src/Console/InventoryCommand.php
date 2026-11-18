<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Console;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use ItsJustVita\WpMigrator\Console\Concerns\ScansTheme;
use ItsJustVita\WpMigrator\Inventory\MigrationPlan;
use ItsJustVita\WpMigrator\Inventory\SiteInventory;
use ItsJustVita\WpMigrator\Inventory\Theme\ThemeScanner;

class InventoryCommand extends Command
{
    use ScansTheme;

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

        $scanned = $this->scanSiteAndTheme($inventory, $scanner, $config, $files, 'The plan is written from the theme alone.');

        if ($scanned === null) {
            return self::FAILURE;
        }

        [$theme, $site, $themePath] = $scanned;

        $files->ensureDirectoryExists(dirname($output));
        $files->put($output, $plan->render($theme, $site, null, $this->installedPlugins($themePath, $files)));

        foreach ($theme->warnings as $warning) {
            $this->components->warn($warning);
        }

        $this->components->info(sprintf('Migration plan written to %s', $output));

        return self::SUCCESS;
    }

    /**
     * The plugins in the plugins directory next to the themes directory.
     *
     * @return list<string>
     */
    private function installedPlugins(string $theme, Filesystem $files): array
    {
        $themes = dirname(rtrim($theme, '/\\'));
        $directory = dirname($themes).'/plugins';

        if (basename($themes) !== 'themes' || ! $files->isDirectory($directory)) {
            return [];
        }

        $plugins = [];

        // Both skip hidden entries.
        foreach ($files->directories($directory) as $path) {
            $plugins[] = basename($path).'/'.basename($path).'.php';
        }

        foreach ($files->files($directory) as $file) {
            if ($file->getExtension() === 'php' && $file->getFilename() !== 'index.php') {
                $plugins[] = $file->getFilename();
            }
        }

        sort($plugins);

        return $plugins;
    }
}
