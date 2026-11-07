<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Shortcodes\Handlers;

use ItsJustVita\WpMigrator\Content\Embeds;
use ItsJustVita\WpMigrator\Content\Shortcodes\Shortcode;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeContext;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeHandler;

/**
 * [embed]https://…[/embed]
 */
final class EmbedHandler implements ShortcodeHandler
{
    public function render(Shortcode $shortcode, ShortcodeContext $context): string
    {
        $url = trim($shortcode->content ?? '');

        if ($url === '') {
            $url = $shortcode->attr('src') ?? $shortcode->attr(0) ?? '';
        }

        return Embeds::render($url);
    }
}
