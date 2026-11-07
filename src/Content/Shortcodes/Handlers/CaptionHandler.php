<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Shortcodes\Handlers;

use ItsJustVita\WpMigrator\Content\Shortcodes\Shortcode;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeContext;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeHandler;

/**
 * [caption id="" align="" width=""]<img /> Caption text[/caption]
 */
final class CaptionHandler implements ShortcodeHandler
{
    private const string LEADING_IMAGE = '/^\s*((?:<a\b[^>]*>\s*)?<img\b[^>]*>(?:\s*<\/a>)?)(.*)$/is';

    public function render(Shortcode $shortcode, ShortcodeContext $context): string
    {
        $content = $shortcode->content ?? '';
        $media = $content;
        $text = '';

        if (preg_match(self::LEADING_IMAGE, $content, $matches) === 1) {
            $media = $matches[1];
            $text = trim($matches[2]);
        }

        if ($text === '') {
            $text = trim($shortcode->attr('caption', '') ?? '');
        }

        $figure = '<figure';
        $id = $shortcode->attr('id', '') ?? '';

        if ($id !== '') {
            $figure .= ' id="'.e($id).'"';
        }

        $align = $shortcode->attr('align', '') ?? '';
        $figure .= ' class="'.e(trim('wp-caption '.$align)).'"';

        $width = $shortcode->attr('width', '') ?? '';

        if (ctype_digit($width)) {
            $figure .= ' style="width:'.$width.'px"';
        }

        $figure .= '>'.$context->process($media);

        if ($text !== '') {
            $figure .= '<figcaption class="wp-caption-text">'.$context->process($text).'</figcaption>';
        }

        return $figure.'</figure>';
    }
}
