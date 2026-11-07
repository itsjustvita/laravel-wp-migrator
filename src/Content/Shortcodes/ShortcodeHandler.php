<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Shortcodes;

interface ShortcodeHandler
{
    /**
     * Returns the HTML that replaces the shortcode.
     */
    public function render(Shortcode $shortcode, ShortcodeContext $context): string;
}
