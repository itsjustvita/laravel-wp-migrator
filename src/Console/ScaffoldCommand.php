<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Console;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use ItsJustVita\WpMigrator\Console\Concerns\ScansTheme;
use ItsJustVita\WpMigrator\Inventory\Frontend;
use ItsJustVita\WpMigrator\Inventory\SiteInventory;
use ItsJustVita\WpMigrator\Inventory\Theme\ThemeScanner;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Scaffold\Scaffold;

class ScaffoldCommand extends Command
{
    use ScansTheme;

    protected $signature = 'wp:scaffold
        {--theme= : Directory of the theme; defaults to the active theme next to the uploads directory}
        {--child= : Directory of the child theme}
        {--force : Replace files that already exist}';

    protected $description = 'Write routes, a controller, views and components that show the imported content';

    public function handle(SiteInventory $inventory, ThemeScanner $scanner, Repository $config, Filesystem $files): int
    {
        $scanned = $this->scanSiteAndTheme($inventory, $scanner, $config, $files, 'The files are written from the theme alone, and the front page shows the latest posts.');

        if ($scanned === null) {
            return self::FAILURE;
        }

        [$theme, $site] = $scanned;
        $scaffold = new Scaffold(new Frontend($theme, $site, $config), $this->blockAttributes(array_keys($site->blocks)));
        $force = (bool) $this->option('force');

        $this->newLine();

        foreach ($scaffold->files() as $path => $content) {
            $target = base_path($path);
            $exists = $files->exists($target);

            if ($exists && ! $force) {
                $this->components->twoColumnDetail($path, '<fg=yellow>exists, kept</>');

                continue;
            }

            $files->ensureDirectoryExists(dirname($target));
            $files->put($target, $content);
            $this->components->twoColumnDetail($path, $exists ? '<fg=green>replaced</>' : '<fg=green>created</>');
        }

        foreach ([...$scaffold->notes(), ...$this->registerRoutes($files)] as $note) {
            $this->components->warn($note);
        }

        $this->components->info('The frontend is scaffolded. Start it with php artisan serve; the migration plan lists what to build next.');

        return self::SUCCESS;
    }

    /**
     * Loads routes/wp.php from routes/web.php.
     *
     * @return list<string> notes
     */
    private function registerRoutes(Filesystem $files): array
    {
        $web = base_path('routes/web.php');

        if (! $files->exists($web)) {
            return ['There is no routes/web.php; load routes/wp.php yourself.'];
        }

        $routes = $files->get($web);
        $loaded = strpos($routes, "'/wp.php'");

        if ($loaded === false) {
            // Loaded last, the front page replaces an earlier route for "/", such as the welcome page of a new application.
            $files->append($web, "\nrequire __DIR__.'/wp.php';\n");
            $this->components->twoColumnDetail('routes/web.php', '<fg=green>loads routes/wp.php</>');

            return [];
        }

        // Of two routes for the same URL, the one defined later answers.
        if (preg_match('/Route::(?:(?:get|view|any)\(|match\(\s*\[[^\]]*\]\s*,)\s*[\'"]\/[\'"]/', substr($routes, $loaded)) === 1) {
            return ['routes/web.php defines a route for "/" after it loads routes/wp.php, and the later route wins. Move it up or remove it to show the front page.'];
        }

        return [];
    }

    /**
     * What the attributes of the given blocks hold, read from the imported
     * posts: text, a value, a list or an object.
     *
     * @param  list<string>  $names
     * @return array<string, array<string, string>>
     */
    private function blockAttributes(array $names): array
    {
        $wanted = array_flip(array_filter($names, fn (string $name): bool => str_starts_with($name, 'elementor/')));

        if ($wanted === []) {
            return [];
        }

        $attributes = [];

        try {
            foreach (Post::query()->whereNotNull('blocks')->select(['id', 'blocks'])->lazyById(100) as $post) {
                $this->collect($post->blocks ?? [], $wanted, $attributes);
            }
        } catch (QueryException) {
            // Not imported yet: the views list no attributes.
        }

        // MySQL orders the keys of JSON objects itself; sorted, the views are the same everywhere.
        foreach ($attributes as &$keys) {
            ksort($keys);
        }

        return $attributes;
    }

    /**
     * @param  array<array-key, mixed>  $blocks
     * @param  array<string, int>  $wanted
     * @param  array<string, array<string, string>>  $attributes
     */
    private function collect(array $blocks, array $wanted, array &$attributes): void
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $name = $block['name'] ?? null;

            if (is_string($name) && isset($wanted[$name]) && is_array($block['attrs'] ?? null)) {
                foreach ($block['attrs'] as $key => $value) {
                    if (is_string($key) && ! str_starts_with($key, '_')) {
                        $kind = $this->kind($value);
                        $attributes[$name][$key] = ($attributes[$name][$key] ?? null) === 'text' ? 'text' : $kind;
                    }
                }
            }

            if (is_array($block['innerBlocks'] ?? null)) {
                $this->collect($block['innerBlocks'], $wanted, $attributes);
            }
        }
    }

    private function kind(mixed $value): string
    {
        return match (true) {
            is_string($value) && (str_contains($value, ' ') || str_contains($value, '<')) => 'text',
            is_array($value) && $value !== [] && array_is_list($value) => 'list',
            is_array($value) => 'object',
            default => 'value',
        };
    }
}
