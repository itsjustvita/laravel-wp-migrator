<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection\Detectors;

use Illuminate\Database\Query\Builder;
use ItsJustVita\WpMigrator\Source\WpDatabase;

/**
 * Templates of the Elementor theme builder: headers, footers, popups and the
 * layouts of single posts and archives. Pages built with Elementor are
 * converted; these parts of the layout have to be rebuilt in the frontend.
 */
final class ElementorThemeBuilderDetector extends PostDetector
{
    private const array TYPES = [
        'header', 'footer', 'single', 'single-post', 'single-page', 'archive', 'search-results',
        'error-404', 'popup', 'loop-item', 'product', 'product-archive',
    ];

    protected function key(): string
    {
        return 'elementor-theme-builder';
    }

    protected function label(): string
    {
        return 'Elementor theme builder';
    }

    protected function affectedPosts(WpDatabase $db): Builder
    {
        return $this->postsWithMeta($db, '_elementor_template_type', function (Builder $query, string $column): void {
            $query->whereIn($column, self::TYPES);
        })->where($db->tableName('posts').'.post_type', 'elementor_library');
    }
}
