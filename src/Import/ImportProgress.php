<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import;

interface ImportProgress
{
    public function start(string $importer, int $total): void;

    public function advance(): void;

    public function finish(): void;
}
