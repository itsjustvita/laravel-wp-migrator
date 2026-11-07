<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Shortcodes;

use Closure;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Models\Media;

/**
 * What a shortcode handler can work with: the post it appears in, the import
 * and a way to process shortcodes inside its own content.
 */
final readonly class ShortcodeContext
{
    /**
     * @param  Closure(string): string  $process
     */
    public function __construct(
        public int $postId,
        public ImportContext $import,
        private Closure $process,
    ) {}

    public function process(string $content): string
    {
        return ($this->process)($content);
    }

    /**
     * The imported media record of a source attachment. A missing one is
     * reported and the post is mapped again on the next run.
     */
    public function media(int $sourceId, string $kind = 'media'): ?Media
    {
        $id = $this->import->resolve(SourceType::Post, $sourceId, $this->postId, $kind, Media::class);

        return $id === null ? null : Media::query()->find($id);
    }
}
