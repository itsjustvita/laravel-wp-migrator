<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Urls;

final readonly class AuthorReference
{
    public function __construct(
        public int $id,
        public string $nicename,
        public string $name,
    ) {}
}
