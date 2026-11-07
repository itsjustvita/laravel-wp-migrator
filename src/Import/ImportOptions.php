<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import;

use Carbon\CarbonImmutable;
use ItsJustVita\WpMigrator\Source\Readers\PostFilter;

final readonly class ImportOptions
{
    /** Importer names that can be selected with "only". */
    public const array AVAILABLE = ['users', 'media', 'terms', 'posts', 'pages'];

    /** Importer names from the documented set that do not exist yet. */
    public const array PLANNED = ['menus', 'comments', 'redirects'];

    /**
     * @param  list<string>|null  $only  importer names; null runs all of them. "posts" covers
     *                                   every post type except pages, "pages" covers pages
     * @param  list<string>|null  $types  post types to import; overrides the posts/pages split
     * @param  CarbonImmutable|null  $since  only posts and media modified at or after this moment
     * @param  bool  $dryRun  roll everything back at the end and copy no files
     * @param  bool  $force  map rows again even when their checksum is unchanged
     */
    public function __construct(
        public ?array $only = null,
        public ?array $types = null,
        public ?CarbonImmutable $since = null,
        public bool $dryRun = false,
        public bool $force = false,
    ) {}

    public function includes(string $importer): bool
    {
        return $this->only === null || in_array($importer, $this->only, true);
    }

    public function postFilter(): PostFilter
    {
        if ($this->types !== null) {
            return new PostFilter(types: $this->types, since: $this->since);
        }

        $posts = $this->includes('posts');
        $pages = $this->includes('pages');

        return match (true) {
            $posts && $pages => new PostFilter(since: $this->since),
            $pages => new PostFilter(types: ['page'], since: $this->since),
            default => new PostFilter(since: $this->since, excludedTypes: ['page']),
        };
    }
}
