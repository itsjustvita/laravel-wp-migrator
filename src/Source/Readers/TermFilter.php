<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Readers;

final readonly class TermFilter
{
    /**
     * @param  list<string>|null  $taxonomies  null means every taxonomy that is not excluded by config
     */
    public function __construct(
        public ?array $taxonomies = null,
    ) {}
}
