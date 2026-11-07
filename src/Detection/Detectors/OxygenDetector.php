<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection\Detectors;

use Illuminate\Database\Query\Builder;
use ItsJustVita\WpMigrator\Source\WpDatabase;

final class OxygenDetector extends PostDetector
{
    protected function key(): string
    {
        return 'oxygen';
    }

    protected function label(): string
    {
        return 'Oxygen Builder';
    }

    protected function affectedPosts(WpDatabase $db): Builder
    {
        return $this->postsWithMeta($db, 'ct_builder_shortcodes', function (Builder $query, string $column): void {
            $query->where($column, '!=', '');
        });
    }
}
