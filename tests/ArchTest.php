<?php

declare(strict_types=1);

arch('source files declare strict types')
    ->expect('ItsJustVita\WpMigrator')
    ->toUseStrictTypes();

arch('no debugging functions are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();
