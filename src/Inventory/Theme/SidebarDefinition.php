<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory\Theme;

final readonly class SidebarDefinition
{
    public function __construct(
        public string $id,
        public string $name,
        public string $file,
    ) {}
}
