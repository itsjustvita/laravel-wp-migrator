<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory\Theme;

final readonly class AssetDefinition
{
    /**
     * @param  list<string>  $dependencies
     */
    public function __construct(
        public string $handle,
        public ?string $source,
        public array $dependencies,
        public string $file,
    ) {}
}
