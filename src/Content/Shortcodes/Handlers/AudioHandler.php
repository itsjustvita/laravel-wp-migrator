<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Shortcodes\Handlers;

/**
 * [audio src="" mp3="" ogg="" wav="" m4a=""]
 */
final class AudioHandler extends MediaElementHandler
{
    protected function element(): string
    {
        return 'audio';
    }

    protected function sourceTypes(): array
    {
        return [
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'wav' => 'audio/wav',
            'm4a' => 'audio/mp4',
        ];
    }
}
