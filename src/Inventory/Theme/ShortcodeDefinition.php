<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory\Theme;

final readonly class ShortcodeDefinition
{
    public function __construct(
        public string $tag,
        public string $callback,
        public string $file,
    ) {}
}
