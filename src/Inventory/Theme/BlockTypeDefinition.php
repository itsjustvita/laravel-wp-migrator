<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory\Theme;

final readonly class BlockTypeDefinition
{
    public function __construct(
        public string $name,
        public string $file,
    ) {}
}
