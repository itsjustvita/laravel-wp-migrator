<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Detection\UnsupportedContentDetector;
use ItsJustVita\WpMigrator\Import\Importers\CommentImporter;
use ItsJustVita\WpMigrator\Import\Importers\Importer;
use ItsJustVita\WpMigrator\Import\Importers\MediaImporter;
use ItsJustVita\WpMigrator\Import\Importers\MenuImporter;
use ItsJustVita\WpMigrator\Import\Importers\MenuItemImporter;
use ItsJustVita\WpMigrator\Import\Importers\NavigationImporter;
use ItsJustVita\WpMigrator\Import\Importers\PostImporter;
use ItsJustVita\WpMigrator\Import\Importers\TermImporter;
use ItsJustVita\WpMigrator\Import\Importers\UserImporter;
use ItsJustVita\WpMigrator\Models\Comment;
use ItsJustVita\WpMigrator\Models\ImportMapEntry;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Models\Menu;
use ItsJustVita\WpMigrator\Models\MenuItem;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Models\Redirect;
use ItsJustVita\WpMigrator\Models\Term;
use ItsJustVita\WpMigrator\Redirects\RedirectImporter;

/**
 * Runs the importers in dependency order: users, media, terms, posts, menus,
 * comments and finally the redirects, which point to what the others imported.
 */
class ImportRunner
{
    /** @var list<class-string<Importer>> */
    public const array IMPORTERS = [
        UserImporter::class,
        MediaImporter::class,
        TermImporter::class,
        PostImporter::class,
        MenuImporter::class,
        MenuItemImporter::class,
        NavigationImporter::class,
        CommentImporter::class,
        RedirectImporter::class,
    ];

    public function __construct(
        private readonly Container $container,
        private readonly ImportMap $map,
        private readonly UnsupportedContentDetector $detector,
        private readonly DatabaseManager $databases,
        private readonly Repository $config,
    ) {}

    public function run(ImportOptions $options, ImportProgress $progress = new NullProgress): ImportReport
    {
        $report = new ImportReport($options->dryRun);
        $report->addFindings($this->detector->findings());

        if ($options->since !== null) {
            $report->note('Posts and media modified since '.$options->since->utc()->toDateTimeString().' UTC');
        }

        if ($options->force) {
            $report->note('Forced: unchanged rows were mapped again');
        }

        $transactions = $options->dryRun ? $this->beginDryRun() : [];

        try {
            foreach (self::IMPORTERS as $class) {
                $importer = $this->container->make($class);

                if (! $importer->shouldRun($options)) {
                    continue;
                }

                $progress->start($importer->name(), $importer->count($options));
                $importer->run($options, new ImportContext($this->map, $report, $importer->name(), $options->dryRun), $progress);
                $progress->finish();
            }
        } finally {
            foreach ($transactions as $connection) {
                $connection->rollBack();
            }
        }

        $report->finish();

        return $report;
    }

    /**
     * Opens a transaction on every connection the import writes to.
     *
     * @return list<Connection>
     */
    private function beginDryRun(): array
    {
        $models = [ImportMapEntry::class, Post::class, Media::class, Term::class, Menu::class, MenuItem::class, Comment::class, Redirect::class];
        $userModel = $this->config->get('wp-migrator.users.model') ?? $this->config->get('auth.providers.users.model');

        if (is_string($userModel) && is_subclass_of($userModel, Model::class)) {
            $models[] = $userModel;
        }

        $names = array_values(array_unique(array_map(
            fn (string $model): string => (new $model)->getConnectionName() ?? $this->databases->getDefaultConnection(),
            $models,
        )));

        $connections = [];

        foreach ($names as $name) {
            $connection = $this->databases->connection($name);
            $connection->beginTransaction();
            $connections[] = $connection;
        }

        return $connections;
    }
}
