<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use ItsJustVita\WpMigrator\WpMigratorServiceProvider;

it('creates the target tables', function () {
    $tables = ['media', 'posts', 'terms', 'termables', 'menus', 'menu_items', 'seo_meta', 'redirects', 'comments', 'wp_import_map'];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("table {$table} is missing");
    }
});

it('applies the configured table prefix', function () {
    config()->set('wp-migrator.target.table_prefix', 'cms_');

    $this->artisan('migrate:fresh', [
        '--path' => realpath(__DIR__.'/../../../database/migrations'),
        '--realpath' => true,
    ])->assertSuccessful();

    expect(Schema::hasTable('cms_posts'))->toBeTrue()
        ->and(Schema::hasTable('cms_wp_import_map'))->toBeTrue()
        ->and(Schema::hasTable('posts'))->toBeFalse();
});

it('publishes the migrations under their own tag', function () {
    $paths = ServiceProvider::pathsToPublish(WpMigratorServiceProvider::class, 'wp-migrator-migrations');

    expect($paths)->toHaveCount(1)
        ->and(realpath((string) array_key_first($paths)))->toBe(realpath(__DIR__.'/../../../database/migrations'))
        ->and(array_values($paths)[0])->toBe(database_path('migrations'));
});
