<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Console;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use ItsJustVita\WpMigrator\Detection\UnsupportedContentDetector;
use ItsJustVita\WpMigrator\Source\ExcludedUsers;
use ItsJustVita\WpMigrator\Source\SourceStats;
use ItsJustVita\WpMigrator\Source\WpDatabase;
use Throwable;

class CheckCommand extends Command
{
    private const array REQUIRED_TABLES = [
        'posts', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships', 'users', 'usermeta', 'options',
    ];

    protected $signature = 'wp:check';

    protected $description = 'Validate the source connection and paths and summarise the content of the WP site';

    public function handle(
        WpDatabase $db,
        SourceStats $stats,
        UnsupportedContentDetector $detector,
        Repository $config,
        ExcludedUsers $excluded,
    ): int {
        $this->newLine();
        $this->line('<options=bold> Source</>');

        if (! $this->checkConnection($db) || ! $this->checkTables($db)) {
            return self::FAILURE;
        }

        $siteUrl = $this->checkSiteUrl($db, $config);
        $this->checkUploads($config, $siteUrl);

        $this->printCounts($stats);
        $this->printAdministrators($stats, $excluded);
        $this->printFindings($detector);

        return self::SUCCESS;
    }

    private function checkConnection(WpDatabase $db): bool
    {
        try {
            $db->ping();
        } catch (Throwable $exception) {
            $this->components->twoColumnDetail('Connection', sprintf('<fg=red>FAILED</> "%s"', $db->connectionName()));
            $this->components->error($exception->getMessage());

            return false;
        }

        $this->components->twoColumnDetail('Connection', sprintf(
            '<fg=green>OK</> "%s" (%s %s)',
            $db->connectionName(),
            $db->connection()->getDriverName(),
            $db->serverVersion(),
        ));

        return true;
    }

    private function checkTables(WpDatabase $db): bool
    {
        foreach (self::REQUIRED_TABLES as $table) {
            if ($db->hasTable($table)) {
                continue;
            }

            $this->components->twoColumnDetail('Tables', sprintf('<fg=red>MISSING</> %s', $db->tableName($table)));
            $this->components->error(sprintf(
                'Table %s not found. Check wp-migrator.source.table_prefix (currently "%s").',
                $db->tableName($table),
                $db->prefix(),
            ));

            return false;
        }

        $this->components->twoColumnDetail('Tables', sprintf('<fg=green>OK</> prefix "%s"', $db->prefix()));

        return true;
    }

    private function checkSiteUrl(WpDatabase $db, Repository $config): ?string
    {
        $configured = $config->get('wp-migrator.source.site_url');
        $configured = is_string($configured) && $configured !== '' ? $configured : null;
        $home = $db->option('home') ?? $db->option('siteurl');

        if ($configured !== null) {
            $this->components->twoColumnDetail('Site URL', sprintf('%s <fg=gray>(config)</>', $configured));

            if ($home !== null && rtrim($home, '/') !== rtrim($configured, '/')) {
                $this->warn(sprintf(' The configured site_url differs from the home option (%s).', $home));
            }

            return $configured;
        }

        if ($home !== null) {
            $this->components->twoColumnDetail('Site URL', sprintf('%s <fg=gray>(home option)</>', $home));

            return $home;
        }

        $this->components->twoColumnDetail('Site URL', '<fg=yellow>unknown</>');
        $this->warn(' Neither wp-migrator.source.site_url nor the home option is set.');

        return null;
    }

    private function checkUploads(Repository $config, ?string $siteUrl): void
    {
        $path = $config->get('wp-migrator.source.uploads_path');

        if (! is_string($path) || $path === '') {
            if ($siteUrl === null) {
                $this->components->twoColumnDetail('Uploads', '<fg=red>no uploads_path and no site_url</>');
                $this->warn(' Media files can neither be read locally nor downloaded. Configure uploads_path or site_url.');

                return;
            }

            $this->components->twoColumnDetail('Uploads', '<fg=yellow>not configured</> files will be downloaded');

            return;
        }

        if (! is_dir($path) || ! is_readable($path)) {
            $this->components->twoColumnDetail('Uploads', sprintf('<fg=red>not readable</> %s', $path));

            return;
        }

        $this->components->twoColumnDetail('Uploads', sprintf('<fg=green>readable</> %s', $path));
    }

    private function printCounts(SourceStats $stats): void
    {
        $this->newLine();
        $this->line('<options=bold> Content</>');

        foreach ($stats->postCounts() as $type => $statuses) {
            $breakdown = implode(', ', array_map(
                fn (string $status, int $count): string => $status.' '.$count,
                array_keys($statuses),
                $statuses,
            ));

            $this->components->twoColumnDetail(sprintf('%s <fg=gray>(%s)</>', $type, $breakdown), (string) array_sum($statuses));
        }

        foreach ($stats->taxonomyCounts() as $taxonomy => $count) {
            $this->components->twoColumnDetail(sprintf('%s <fg=gray>(taxonomy)</>', $taxonomy), (string) $count);
        }

        $this->components->twoColumnDetail('Users', (string) $stats->userCount());
        $this->components->twoColumnDetail('Media <fg=gray>(attachments)</>', (string) $stats->mediaCount());
    }

    private function printAdministrators(SourceStats $stats, ExcludedUsers $excluded): void
    {
        $administrators = $stats->administrators();

        if ($administrators === []) {
            return;
        }

        $this->newLine();
        $this->line('<options=bold> Administrators</> <fg=gray>(review before the import)</>');

        $left = $excluded->ids();

        foreach ($administrators as $user) {
            $this->components->twoColumnDetail(
                sprintf('%s <fg=gray>(%s)</>', $user['login'], $user['email']),
                in_array($user['id'], $left, true)
                    ? '<fg=gray>excluded</>'
                    : 'registered '.($user['registered'] ?? 'unknown'),
            );
        }

        $this->line(' <fg=gray>A hacked site can hold an attacker\'s account, which would be imported with a working password.</>');
        $this->line(' <fg=gray>Leave such accounts out with wp-migrator.users.excluded.</>');
    }

    private function printFindings(UnsupportedContentDetector $detector): void
    {
        $this->newLine();
        $this->line('<options=bold> Unsupported content</> <fg=gray>(reported, not migrated)</>');

        $detected = 0;

        foreach ($detector->findings() as $finding) {
            if (! $finding->detected) {
                $this->components->twoColumnDetail($finding->label, '<fg=green>none</>');

                continue;
            }

            $detected++;
            $this->components->twoColumnDetail(
                sprintf('<fg=yellow>%s</>', $finding->label),
                sprintf('<fg=yellow>%s</>', $finding->summary()),
            );
        }

        $this->newLine();

        if ($detected === 0) {
            $this->components->info('No unsupported content found.');

            return;
        }

        $this->warn(sprintf(' %d kind%s of unsupported content found. Affected items are reported by the import but not migrated.', $detected, $detected === 1 ? '' : 's'));
    }
}
