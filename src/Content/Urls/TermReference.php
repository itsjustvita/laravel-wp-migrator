<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Urls;

final readonly class TermReference
{
    /**
     * @param  string  $path  the slugs of the term's parents and the term itself: "news/tech"
     */
    public function __construct(
        public int $id,
        public string $taxonomy,
        public string $slug,
        public string $path,
        public string $name,
    ) {}
}
