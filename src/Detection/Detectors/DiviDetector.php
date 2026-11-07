<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection\Detectors;

final class DiviDetector extends ShortcodeDetector
{
    protected function key(): string
    {
        return 'divi';
    }

    protected function label(): string
    {
        return 'Divi Builder';
    }

    protected function shortcodePrefix(): string
    {
        return '[et_pb_';
    }
}
