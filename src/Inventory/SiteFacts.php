<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory;

use ItsJustVita\WpMigrator\Detection\Finding;

/**
 * What the source database says about the site, for the migration plan.
 */
final readonly class SiteFacts
{
    /**
     * @param  array<string, array<string, int>>  $postCounts  post type => status => count
     * @param  array<string, int>  $taxonomies  taxonomy => number of terms
     * @param  array<string, list<array{title: string, type: string}>>  $pageTemplates  template file => posts using it
     * @param  array<string, array{count: int, post_ids: list<int>}>  $shortcodes  shortcodes the package has no handler for
     * @param  array<string, array{count: int, post_ids: list<int>}>  $blocks  blocks the package has no view for
     * @param  list<string>  $plugins  active plugin files
     * @param  array<string, array{menu: string, items: int}>  $menus  location => assigned menu
     * @param  array<string, int>  $widgets  sidebar ID => number of widgets
     * @param  list<Finding>  $findings  content that is not migrated
     */
    public function __construct(
        public bool $reachable,
        public ?string $error = null,
        public ?string $siteName = null,
        public ?string $homeUrl = null,
        public ?string $stylesheet = null,
        public ?string $template = null,
        public array $postCounts = [],
        public array $taxonomies = [],
        public array $pageTemplates = [],
        public array $shortcodes = [],
        public array $blocks = [],
        public array $plugins = [],
        public array $menus = [],
        public array $widgets = [],
        public array $findings = [],
    ) {}

    public static function unreachable(string $error): self
    {
        return new self(false, $error);
    }
}
