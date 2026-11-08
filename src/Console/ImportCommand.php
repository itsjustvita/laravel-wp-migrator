<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Console;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\ImportReport;
use ItsJustVita\WpMigrator\Import\ImportRunner;
use ItsJustVita\WpMigrator\Source\WpDatabase;
use Throwable;

class ImportCommand extends Command
{
    protected $signature = 'wp:import
        {--only= : Comma-separated importers: users, media, terms, posts, pages, menus, comments, redirects}
        {--type= : Comma-separated post types to import}
        {--since= : Only import posts and media modified on or after this date}
        {--dry-run : Roll everything back at the end and copy no files}
        {--force : Map rows again even when they are unchanged, e.g. after changing the configuration}';

    protected $description = 'Import the content of the WP site; unchanged rows are skipped on later runs';

    public function handle(ImportRunner $runner, WpDatabase $db, Repository $config): int
    {
        $only = $this->listOption('only');

        if ($only !== null) {
            foreach ($only as $name) {
                if (! in_array($name, ImportOptions::AVAILABLE, true)) {
                    $this->components->error(sprintf(
                        'Unknown importer "%s". Available: %s.',
                        $name,
                        implode(', ', ImportOptions::AVAILABLE),
                    ));

                    return self::FAILURE;
                }
            }

            if (in_array('comments', $only, true) && ! $config->boolean('wp-migrator.comments.enabled')) {
                $this->components->warn('Comments are turned off. Set wp-migrator.comments.enabled to import them.');
            }
        }

        $since = null;
        $sinceOption = $this->option('since');

        if (is_string($sinceOption) && $sinceOption !== '') {
            try {
                $since = CarbonImmutable::parse($sinceOption);
            } catch (Throwable) {
                $this->components->error(sprintf('Invalid --since date "%s".', $sinceOption));

                return self::FAILURE;
            }
        }

        try {
            $db->ping();
        } catch (Throwable $exception) {
            $this->components->error(sprintf('Cannot open the source connection "%s": %s', $db->connectionName(), $exception->getMessage()));

            return self::FAILURE;
        }

        if (! $db->hasTable('posts')) {
            $this->components->error(sprintf('Table %s not found. Check wp-migrator.source.table_prefix (currently "%s").', $db->tableName('posts'), $db->prefix()));

            return self::FAILURE;
        }

        $options = new ImportOptions(
            only: $only,
            types: $this->listOption('type'),
            since: $since,
            dryRun: (bool) $this->option('dry-run'),
            force: (bool) $this->option('force'),
        );

        if ($options->dryRun) {
            $this->components->warn('Dry run: every change is rolled back at the end and no files are copied.');
        }

        $this->newLine();

        $report = $runner->run($options, new ConsoleProgress($this->output));

        $this->printSummary($report);

        $path = $report->save($config->string('wp-migrator.report.directory'));
        $this->components->info('Report written to '.$path);

        if ($report->hasErrors()) {
            $failed = count($report->errors());
            $this->components->error(sprintf('%d row%s failed; see the report for details.', $failed, $failed === 1 ? '' : 's'));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function printSummary(ImportReport $report): void
    {
        $rows = [];

        foreach ($report->counts() as $importer => $counts) {
            $rows[] = [$importer, ...array_map(
                fn (string $outcome): int => $counts[$outcome],
                ImportReport::OUTCOMES,
            )];
        }

        if ($rows !== []) {
            $this->table(['Importer', 'Created', 'Updated', 'Unchanged', 'Skipped', 'Failed'], $rows);
        }

        $this->components->twoColumnDetail('Warnings', (string) count($report->warnings()));
        $this->components->twoColumnDetail('Unresolved references', (string) count($report->unresolvedReferences()));
        $this->components->twoColumnDetail('Unsupported content', (string) count($report->findings()));
        $this->components->twoColumnDetail('Unknown shortcodes', (string) count($report->unknownShortcodes()));
        $this->components->twoColumnDetail('Blocks without a renderer', (string) count($report->unknownBlocks()));
        $this->newLine();
    }

    /**
     * @return list<string>|null
     */
    private function listOption(string $name): ?array
    {
        $value = $this->option($name);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $value)), fn (string $item): bool => $item !== ''));
    }
}
