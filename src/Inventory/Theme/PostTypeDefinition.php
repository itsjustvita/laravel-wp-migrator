<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory\Theme;

final readonly class PostTypeDefinition
{
    /**
     * @param  list<string>  $supports
     */
    public function __construct(
        public string $name,
        public ?string $label,
        public ?bool $public,
        public bool|string|null $hasArchive,
        public ?bool $hierarchical,
        public ?string $rewriteSlug,
        public array $supports,
        public string $file,
    ) {}
}
