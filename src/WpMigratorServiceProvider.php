<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use ItsJustVita\WpMigrator\Console\CheckCommand;
use ItsJustVita\WpMigrator\Console\ImportCommand;
use ItsJustVita\WpMigrator\Content\Urls\DefaultUrlResolver;
use ItsJustVita\WpMigrator\Content\Urls\UrlResolver;
use ItsJustVita\WpMigrator\Models\Comment;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Models\Menu;
use ItsJustVita\WpMigrator\Models\MenuItem;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Models\Redirect;
use ItsJustVita\WpMigrator\Models\SeoMeta;
use ItsJustVita\WpMigrator\Models\Term;
use ItsJustVita\WpMigrator\View\Components\Blocks;

class WpMigratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/wp-migrator.php', 'wp-migrator');

        $this->app->singleton(WpMigrator::class);

        $this->app->bind(UrlResolver::class, function (Application $app): UrlResolver {
            $resolver = $app->make(Repository::class)->get('wp-migrator.urls.resolver', DefaultUrlResolver::class);

            if (! is_string($resolver) || ! is_subclass_of($resolver, UrlResolver::class)) {
                throw new InvalidArgumentException('wp-migrator.urls.resolver must name a class that implements '.UrlResolver::class.'.');
            }

            return $app->make($resolver);
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'wp-migrator');

        Blade::component('wp-blocks', Blocks::class);

        Relation::morphMap([
            'wp_migrator_post' => Post::class,
            'wp_migrator_term' => Term::class,
            'wp_migrator_media' => Media::class,
            'wp_migrator_menu' => Menu::class,
            'wp_migrator_menu_item' => MenuItem::class,
            'wp_migrator_comment' => Comment::class,
            'wp_migrator_seo_meta' => SeoMeta::class,
            'wp_migrator_redirect' => Redirect::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckCommand::class,
                ImportCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/wp-migrator.php' => config_path('wp-migrator.php'),
            ], 'wp-migrator-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/wp-migrator'),
            ], 'wp-migrator-views');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'wp-migrator-migrations');
        }
    }
}
