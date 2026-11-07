<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Readers;

use Carbon\CarbonImmutable;

final readonly class MediaFilter
{
    public function __construct(
        public ?CarbonImmutable $since = null,
    ) {}
}
