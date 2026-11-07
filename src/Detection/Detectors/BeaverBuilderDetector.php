<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection\Detectors;

use Illuminate\Database\Query\Builder;
use ItsJustVita\WpMigrator\Source\WpDatabase;

final class BeaverBuilderDetector extends PostDetector
{
    protected function key(): string
    {
        return 'beaver-builder';
    }

    protected function label(): string
    {
        return 'Beaver Builder';
    }

    protected function affectedPosts(WpDatabase $db): Builder
    {
        return $this->postsWithMeta($db, '_fl_builder_enabled', function (Builder $query, string $column): void {
            $query->where($column, '1');
        });
    }
}
