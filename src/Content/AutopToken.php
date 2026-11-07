<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content;

/**
 * One piece of the text {@see Autop} works on. An object with declared
 * properties takes a fraction of the memory of an array with the same keys,
 * which matters for documents with hundreds of thousands of tags.
 *
 * @internal
 */
final readonly class AutopToken
{
    /**
     * @param  'text'|'open'|'close'|'void'|'verbatim'|'comment'  $type
     */
    public function __construct(
        public string $type,
        public string $raw,
        public string $name = '',
        public bool $block = false,
    ) {}
}
