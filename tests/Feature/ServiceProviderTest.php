<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use ItsJustVita\WpMigrator\WpMigratorServiceProvider;

it('registers the service provider', function () {
    expect($this->app->providerIsLoaded(WpMigratorServiceProvider::class))->toBeTrue();
});

it('merges the default configuration', function () {
    expect(config('wp-migrator.source.connection'))->toBe('wordpress')
        ->and(config('wp-migrator.source.table_prefix'))->toBe('wp_')
        ->and(config('wp-migrator.source.uploads_path'))->toBeNull()
        ->and(config('wp-migrator.source.site_url'))->toBeNull()
        ->and(config('wp-migrator.target.table_prefix'))->toBe('');
});

it('publishes the configuration file under its own tag', function () {
    $paths = ServiceProvider::pathsToPublish(WpMigratorServiceProvider::class, 'wp-migrator-config');

    expect($paths)->toHaveCount(1)
        ->and(array_values($paths)[0])->toBe(config_path('wp-migrator.php'));
});
