<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection\Detectors;

final class MultisiteDetector extends TableDetector
{
    protected function key(): string
    {
        return 'multisite';
    }

    protected function label(): string
    {
        return 'Multisite network';
    }

    protected function table(): string
    {
        return 'blogs';
    }
}
