<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection\Detectors;

final class WpmlDetector extends TableDetector
{
    protected function key(): string
    {
        return 'wpml';
    }

    protected function label(): string
    {
        return 'WPML (multilingual)';
    }

    protected function table(): string
    {
        return 'icl_translations';
    }
}
