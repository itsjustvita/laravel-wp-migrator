<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Support;

/**
 * Orders parent/child rows so parents can be imported before their children.
 */
final class Hierarchy
{
    /**
     * Groups IDs by depth, top level first, keeping the given order within a
     * level. A parent outside the set counts as no parent. Parent cycles,
     * which corrupt data can contain, end at the point they would repeat.
     *
     * @param  array<int, int>  $parents  ID => parent ID, 0 for none
     * @return list<list<int>>
     */
    public static function levels(array $parents): array
    {
        $levels = [];

        foreach (array_keys($parents) as $id) {
            $depth = 0;
            $current = $id;
            $seen = [$id => true];

            while (($parent = $parents[$current] ?? 0) !== 0 && isset($parents[$parent]) && ! isset($seen[$parent])) {
                $depth++;
                $current = $parent;
                $seen[$parent] = true;
            }

            $levels[$depth][] = $id;
        }

        ksort($levels);

        return array_values($levels);
    }
}
