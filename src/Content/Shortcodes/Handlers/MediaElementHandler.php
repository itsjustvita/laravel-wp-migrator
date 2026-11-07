<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Shortcodes\Handlers;

use ItsJustVita\WpMigrator\Content\Shortcodes\Shortcode;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeContext;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeHandler;

/**
 * Shared rendering of [video] and [audio]. URLs with a scheme other than
 * http(s) are dropped, as is a media element without any source.
 */
abstract class MediaElementHandler implements ShortcodeHandler
{
    abstract protected function element(): string;

    /**
     * @return array<string, string> file format attribute => MIME type
     */
    abstract protected function sourceTypes(): array;

    /**
     * @return array<string, string> attribute HTML built from the shortcode
     */
    protected function extraAttributes(Shortcode $shortcode): array
    {
        return [];
    }

    public function render(Shortcode $shortcode, ShortcodeContext $context): string
    {
        $src = $this->safeUrl($shortcode->attr('src'));
        $sources = '';

        foreach ($this->sourceTypes() as $format => $type) {
            $url = $this->safeUrl($shortcode->attr($format));

            if ($url !== null) {
                $sources .= '<source src="'.e($url).'" type="'.$type.'" />';
            }
        }

        $content = $shortcode->content ?? '';

        if ($src === null && $sources === '' && trim($content) === '') {
            return '';
        }

        $attributes = ' controls'.($src === null ? '' : ' src="'.e($src).'"');

        foreach ($this->extraAttributes($shortcode) as $html) {
            $attributes .= $html;
        }

        $preload = $shortcode->attr('preload');

        if ($preload !== null && in_array($preload, ['none', 'metadata', 'auto'], true)) {
            $attributes .= ' preload="'.$preload.'"';
        }

        foreach (['loop', 'autoplay', 'muted'] as $flag) {
            if (in_array($shortcode->attr($flag), ['on', '1', 'true'], true)) {
                $attributes .= ' '.$flag;
            }
        }

        $element = $this->element();

        return '<figure class="wp-block-'.$element.'"><'.$element.$attributes.'>'.$sources.$context->process($content).'</'.$element.'></figure>';
    }

    protected function safeUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        if (preg_match('/^([a-zA-Z][a-zA-Z0-9+.-]*):/', $url, $matches) === 1
            && ! in_array(strtolower($matches[1]), ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }
}
