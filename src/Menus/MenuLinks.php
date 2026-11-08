<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Menus;

use ItsJustVita\WpMigrator\Content\Urls\PostUrlIndex;
use ItsJustVita\WpMigrator\Content\Urls\TermIndex;
use ItsJustVita\WpMigrator\Content\Urls\UrlResolver;
use ItsJustVita\WpMigrator\Content\Urls\UrlRewriter;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Support\SafeUrl;

/**
 * Where a menu entry points: its new URL and, for posts and terms, the model
 * the target was imported as. The URL comes from the source site, so it
 * exists even before the target is imported.
 */
class MenuLinks
{
    public function __construct(
        private readonly PostUrlIndex $posts,
        private readonly TermIndex $terms,
        private readonly UrlResolver $resolver,
        private readonly UrlRewriter $urls,
    ) {}

    public function post(int $postId, int $itemId, ImportContext $context): MenuLink
    {
        $reference = $this->posts->findById($postId);

        // WP hides menu entries for drafts and private posts; so does the import.
        if ($reference !== null && $reference->status !== 'publish') {
            return MenuLink::unpublished();
        }

        $url = $reference === null ? null : $this->resolver->forPost($reference);

        if ($url === null) {
            $context->report->unresolved($context->importer, $itemId, 'menu link', 'post '.$postId);

            return new MenuLink(null);
        }

        return $this->withTarget(new MenuLink($url), SourceType::Post, $postId, $itemId, $context);
    }

    public function term(int $termId, int $itemId, ImportContext $context): MenuLink
    {
        $reference = $this->terms->find($termId);
        $url = $reference === null ? null : $this->resolver->forTerm($reference);

        if ($url === null) {
            $context->report->unresolved($context->importer, $itemId, 'menu link', 'term '.$termId);

            return new MenuLink(null);
        }

        return $this->withTarget(new MenuLink($url), SourceType::Term, $termId, $itemId, $context);
    }

    public function custom(string $url, int $itemId, ImportContext $context): MenuLink
    {
        if ($url === '') {
            return new MenuLink(null);
        }

        if (! SafeUrl::isSafe($url)) {
            $context->warn($itemId, sprintf('The menu link "%s" is not a safe link and was left out.', mb_substr($url, 0, 80)));

            return new MenuLink(null);
        }

        return new MenuLink($this->urls->rewrite($url, $itemId, $context));
    }

    private function withTarget(MenuLink $link, SourceType $type, int $sourceId, int $itemId, ImportContext $context): MenuLink
    {
        $entry = $context->map->find($type, $sourceId);

        if ($entry === null) {
            $context->report->unresolved($context->importer, $itemId, 'menu link target', $type->value.' '.$sourceId);
            $context->markIncomplete();

            return $link;
        }

        return new MenuLink($link->url, $entry->target_type, $entry->target_id);
    }
}
