<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Mapping\Concerns;

use ItsJustVita\WpMigrator\Models\Menu;

/**
 * Classic menus and block navigation menus share one slug column. A slug
 * already taken by another menu gets the source ID appended.
 */
trait UniqueMenuSlug
{
    protected function uniqueSlug(Menu $menu, string $slug, int $sourceId): string
    {
        $taken = Menu::query()
            ->where('slug', $slug)
            ->when($menu->exists, fn ($query) => $query->whereKeyNot($menu->getKey()))
            ->exists();

        return $taken ? $slug.'-'.$sourceId : $slug;
    }
}
