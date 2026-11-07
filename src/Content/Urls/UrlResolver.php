<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Urls;

/**
 * Decides the new URLs of source posts and archives. Register an
 * implementation in wp-migrator.urls.resolver to match the application's
 * routes. Links in content and redirects both use it.
 */
interface UrlResolver
{
    /**
     * The new URL of the post, or null when it has none. A link to such a post
     * stays as it is and shows up in the report.
     */
    public function forPost(PostReference $post): ?string;

    /**
     * The new URL of a category or tag archive, or null when there is none.
     */
    public function forTerm(TermReference $term): ?string;

    /**
     * The new URL of an author archive, or null when there is none.
     */
    public function forAuthor(AuthorReference $author): ?string;
}
