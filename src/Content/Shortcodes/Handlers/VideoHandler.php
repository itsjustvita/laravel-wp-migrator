<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Shortcodes\Handlers;

use ItsJustVita\WpMigrator\Content\Shortcodes\Shortcode;

/**
 * [video src="" poster="" width="" height="" mp4="" webm=""]
 */
final class VideoHandler extends MediaElementHandler
{
    protected function element(): string
    {
        return 'video';
    }

    protected function sourceTypes(): array
    {
        return [
            'mp4' => 'video/mp4',
            'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'ogv' => 'video/ogg',
        ];
    }

    protected function extraAttributes(Shortcode $shortcode): array
    {
        $attributes = [];
        $poster = $this->safeUrl($shortcode->attr('poster'));

        if ($poster !== null) {
            $attributes['poster'] = ' poster="'.e($poster).'"';
        }

        foreach (['width', 'height'] as $dimension) {
            $value = $shortcode->attr($dimension, '') ?? '';

            if (ctype_digit($value)) {
                $attributes[$dimension] = ' '.$dimension.'="'.$value.'"';
            }
        }

        return $attributes;
    }
}
