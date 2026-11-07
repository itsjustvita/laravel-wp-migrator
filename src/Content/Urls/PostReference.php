<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Urls;

/**
 * What is known about a source post when working out URLs. It comes from the
 * source site, so a link can be resolved before its target has been imported.
 */
final readonly class PostReference
{
    /**
     * @param  string  $slug  decoded post_name
     * @param  string  $path  the slugs of the post's parents and the post itself: "company/team"
     * @param  string|null  $date  post_date in the site's timezone, "Y-m-d H:i:s"
     * @param  string|null  $author  the author's nicename
     * @param  string|null  $category  the slugs of the post's category and its parents: "news/tech"
     */
    public function __construct(
        public int $id,
        public string $type,
        public string $status,
        public string $slug,
        public string $path,
        public ?string $date = null,
        public ?string $author = null,
        public ?string $category = null,
    ) {}
}
