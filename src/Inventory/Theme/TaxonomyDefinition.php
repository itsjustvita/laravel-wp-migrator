<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory\Theme;

final readonly class TaxonomyDefinition
{
    /**
     * @param  list<string>  $postTypes
     */
    public function __construct(
        public string $name,
        public ?string $label,
        public array $postTypes,
        public ?bool $hierarchical,
        public ?string $rewriteSlug,
        public string $file,
    ) {}
}
