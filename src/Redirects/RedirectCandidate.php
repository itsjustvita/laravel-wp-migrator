<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Redirects;

/**
 * The old URLs of one post, archive or attachment and where they go now.
 */
final readonly class RedirectCandidate
{
    /**
     * @param  list<string>  $from
     * @param  string  $label  what the URLs belong to, for the report: "category news"
     */
    public function __construct(
        public array $from,
        public ?string $to,
        public int $sourceId,
        public string $label,
    ) {}
}
