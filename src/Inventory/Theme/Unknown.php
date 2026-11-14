<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory\Theme;

/**
 * Stands for a value that is only known when the code runs.
 */
final class Unknown
{
    private static ?self $instance = null;

    public static function value(): self
    {
        return self::$instance ??= new self;
    }
}
