<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Urls;

/**
 * Decides the new URL of a source post. Register an implementation in
 * wp-migrator.urls.resolver to match the application's routes.
 */
interface UrlResolver
{
    /**
     * The new URL of the post, or null when it has none. A link to such a post
     * stays as it is and shows up in the report.
     */
    public function forPost(PostReference $post): ?string;
}
