<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection\Detectors;

use Illuminate\Database\Query\Builder;
use ItsJustVita\WpMigrator\Source\Like;
use ItsJustVita\WpMigrator\Source\WpDatabase;

/**
 * Finds posts whose content contains a page builder's shortcode prefix.
 */
abstract class ShortcodeDetector extends PostDetector
{
    abstract protected function shortcodePrefix(): string;

    protected function affectedPosts(WpDatabase $db): Builder
    {
        return Like::contains($db->posts(), 'post_content', $this->shortcodePrefix());
    }
}
