<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection\Detectors;

final class WpBakeryDetector extends ShortcodeDetector
{
    protected function key(): string
    {
        return 'wpbakery';
    }

    protected function label(): string
    {
        return 'WPBakery Page Builder';
    }

    protected function shortcodePrefix(): string
    {
        return '[vc_';
    }
}
