<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection\Detectors;

use Illuminate\Database\Query\Builder;
use ItsJustVita\WpMigrator\Source\WpDatabase;

final class ElementorDetector extends PostDetector
{
    protected function key(): string
    {
        return 'elementor';
    }

    protected function label(): string
    {
        return 'Elementor';
    }

    protected function affectedPosts(WpDatabase $db): Builder
    {
        return $this->postsWithMeta($db, '_elementor_data', function (Builder $query, string $column): void {
            $query->whereNotIn($column, ['', '[]']);
        });
    }
}
