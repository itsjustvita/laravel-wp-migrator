<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Tests;

use ItsJustVita\WpMigrator\Tests\Support\SourceSite;
use ItsJustVita\WpMigrator\Tests\Support\User;
use ItsJustVita\WpMigrator\WpMigratorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    private const string TARGET_DATABASE = 'wp_migrator_target';

    private ?SourceSite $source = null;

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            WpMigratorServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('wp-migrator.report.directory', sys_get_temp_dir().'/wp-migrator-tests');

        $app['config']->set('database.connections.wordpress', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        if (self::usesServerDatabase()) {
            $server = [
                'driver' => getenv('WP_MIGRATOR_TEST_DB_DRIVER') ?: 'mysql',
                'host' => getenv('WP_MIGRATOR_TEST_DB_HOST'),
                'port' => getenv('WP_MIGRATOR_TEST_DB_PORT') ?: 3306,
                'username' => getenv('WP_MIGRATOR_TEST_DB_USERNAME') ?: 'root',
                'password' => getenv('WP_MIGRATOR_TEST_DB_PASSWORD') ?: '',
                'charset' => 'utf8mb4',
                'prefix' => '',
            ];

            // Not strict, so tests can store the zero dates real WP tables contain.
            $app['config']->set('database.connections.wordpress', [
                ...$server,
                'database' => getenv('WP_MIGRATOR_TEST_DB_DATABASE') ?: 'wordpress',
                'strict' => false,
            ]);

            $app['config']->set('database.connections.testing', [
                ...$server,
                'database' => self::TARGET_DATABASE,
                'strict' => true,
            ]);
        }
    }

    protected function defineDatabaseMigrations(): void
    {
        if (self::usesServerDatabase()) {
            $this->resetServerDatabases();
        }

        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Whether the suite runs against MySQL or MariaDB instead of SQLite.
     */
    public static function usesServerDatabase(): bool
    {
        return (string) getenv('WP_MIGRATOR_TEST_DB_HOST') !== '';
    }

    private function resetServerDatabases(): void
    {
        $databases = $this->app['db'];
        $source = $databases->connection('wordpress');

        $source->statement(sprintf('create database if not exists `%s`', self::TARGET_DATABASE));

        $source->getSchemaBuilder()->dropAllTables();
        $databases->connection('testing')->getSchemaBuilder()->dropAllTables();
    }

    /**
     * An empty WP site on the "wordpress" test connection, created on first use.
     */
    protected function source(): SourceSite
    {
        return $this->source ??= (new SourceSite($this->app['db']->connection('wordpress')))->createSchema();
    }
}
