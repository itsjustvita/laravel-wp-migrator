<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Readers;

use Carbon\CarbonImmutable;

final readonly class PostFilter
{
    /**
     * @param  list<string>|null  $types  null means every type that is not excluded by config
     * @param  list<string>|null  $statuses  null means the statuses configured for import
     * @param  CarbonImmutable|null  $since  only posts modified at or after this moment
     * @param  list<string>  $excludedTypes  types left out in addition to the configured ones
     */
    public function __construct(
        public ?array $types = null,
        public ?array $statuses = null,
        public ?CarbonImmutable $since = null,
        public array $excludedTypes = [],
    ) {}
}
