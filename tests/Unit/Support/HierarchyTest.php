<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Support\Hierarchy;

it('groups ids by depth, top level first', function () {
    expect(Hierarchy::levels([1 => 0, 2 => 1, 3 => 2, 4 => 0, 5 => 1]))
        ->toBe([[1, 4], [2, 5], [3]]);
});

it('treats a parent outside the set as top level', function () {
    expect(Hierarchy::levels([1 => 99, 2 => 1]))->toBe([[1], [2]]);
});

it('terminates on parent cycles and keeps every id', function () {
    $levels = Hierarchy::levels([1 => 2, 2 => 1, 3 => 3, 4 => 1]);

    $flat = array_merge(...$levels);
    sort($flat);

    expect($flat)->toBe([1, 2, 3, 4]);
});

it('returns nothing for an empty set', function () {
    expect(Hierarchy::levels([]))->toBe([]);
});
