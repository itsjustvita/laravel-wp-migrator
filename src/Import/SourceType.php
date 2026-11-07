<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import;

/**
 * The WP entity a source ID belongs to. Rows of wp_posts share one ID space
 * regardless of post type, so attachments and menu items are posts here and
 * menus (nav_menu) are terms.
 */
enum SourceType: string
{
    case Post = 'post';
    case Term = 'term';
    case User = 'user';
    case Comment = 'comment';
}
