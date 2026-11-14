<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Readers;

final readonly class UserFilter
{
    /**
     * @param  list<int>  $excludedIds  users that are left out
     */
    public function __construct(public array $excludedIds = []) {}
}
