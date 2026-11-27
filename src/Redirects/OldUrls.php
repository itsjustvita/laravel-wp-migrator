<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Redirects;

use Generator;
use Illuminate\Config\Repository;
use ItsJustVita\WpMigrator\Content\Urls\AuthorReference;
use ItsJustVita\WpMigrator\Content\Urls\MediaUrls;
use ItsJustVita\WpMigrator\Content\Urls\PostUrlIndex;
use ItsJustVita\WpMigrator\Content\Urls\TermIndex;
use ItsJustVita\WpMigrator\Content\Urls\UrlResolver;
use ItsJustVita\WpMigrator\Import\ImportMap;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Source\SourceStats;
use ItsJustVita\WpMigrator\Source\WpDatabase;

/**
 * Works out the URLs the old site answered to: the permalinks of posts,
 * pages and custom post types, category, tag and author archives, attachment
 * pages, and the ?p=, ?page_id=, ?cat=, ?tag=, ?author= and ?attachment_id=
 * forms that work regardless of the permalink structure.
 */
class OldUrls
{
    public function __construct(
        private readonly WpDatabase $db,
        private readonly PostUrlIndex $posts,
        private readonly TermIndex $terms,
        private readonly UrlResolver $resolver,
        private readonly ImportMap $map,
        private readonly MediaUrls $media,
        private readonly Repository $config,
        private readonly SourceStats $stats,
    ) {}

    /**
     * @return Generator<int, RedirectCandidate>
     */
    public function candidates(): Generator
    {
        $structure = (string) $this->db->option('permalink_structure');
        $pretty = $structure !== '';

        yield from $this->posts();
        yield from $this->attachments($structure);

        // The archives of a blog list posts. Without posts they showed nothing, and the new site has no such archives.
        if ($this->posts->hasType('post')) {
            yield from $this->terms('category', $pretty ? $this->base('category_base', 'category') : null, 'cat');
            yield from $this->terms('post_tag', $pretty ? $this->base('tag_base', 'tag') : null, 'tag');
            yield from $this->authors($pretty);
        }
    }

    /**
     * @return Generator<int, RedirectCandidate>
     */
    private function posts(): Generator
    {
        foreach ($this->posts->all() as $post) {
            $from = [];
            $path = $this->posts->oldPath($post->id);

            if ($path !== null) {
                $from[] = $path;
            }

            $from[] = '/?p='.$post->id;

            if ($post->type === 'page') {
                $from[] = '/?page_id='.$post->id;
            }

            yield new RedirectCandidate($from, $this->resolver->forPost($post), $post->id, $post->type.' '.$post->slug);
        }
    }

    /**
     * Attachment pages go to the file. An attachment's page lived below the
     * post it was uploaded to, or at the top level when it had none.
     *
     * @return Generator<int, RedirectCandidate>
     */
    private function attachments(string $structure): Generator
    {
        $rows = $this->db->table('posts')->where('post_type', 'attachment')->orderBy('ID')->get(['ID', 'post_name', 'post_parent']);

        foreach ($rows as $row) {
            $id = (int) $row->ID;
            $slug = rawurldecode((string) $row->post_name);
            $entry = $this->map->find(SourceType::Post, $id);

            if ($entry === null || $entry->target_type !== (new Media)->getMorphClass()) {
                continue;
            }

            $media = Media::query()->find($entry->target_id);

            if ($media === null || $media->path === '') {
                continue;
            }

            $from = [];

            if ($structure !== '' && $slug !== '') {
                $parentPath = $this->posts->oldPath((int) $row->post_parent);

                if ($parentPath !== null) {
                    $separator = ctype_digit($slug) || str_contains($structure, '%category%') ? '/attachment/' : '/';
                    $from[] = $parentPath.$separator.$slug;
                } else {
                    $from[] = '/'.$slug;
                }
            }

            $from[] = '/?attachment_id='.$id;

            yield new RedirectCandidate($from, $this->media->of($media), $id, 'attachment '.$slug);
        }
    }

    /**
     * @return Generator<int, RedirectCandidate>
     */
    private function terms(string $taxonomy, ?string $base, string $queryVar): Generator
    {
        foreach ($this->terms->taxonomy($taxonomy) as $term) {
            $from = [];

            if ($base !== null) {
                $from[] = '/'.$base.'/'.($taxonomy === 'category' ? $term->path : $term->slug);
            }

            $from[] = '/?'.$queryVar.'='.($queryVar === 'cat' ? $term->id : $term->slug);

            yield new RedirectCandidate($from, $this->resolver->forTerm($term), $term->id, $taxonomy.' '.$term->slug);
        }
    }

    /**
     * Authors with at least one post; WP has no archive for the others.
     *
     * @return Generator<int, RedirectCandidate>
     */
    private function authors(bool $pretty): Generator
    {
        $authors = $this->stats->authors(
            $this->config->array('wp-migrator.import.excluded_post_types'),
            $this->config->array('wp-migrator.import.post_statuses'),
        );

        foreach ($authors as $row) {
            $author = new AuthorReference($row['id'], $row['slug'], $row['name']);
            $from = $pretty ? ['/author/'.$author->nicename] : [];
            $from[] = '/?author='.$author->id;

            yield new RedirectCandidate($from, $this->resolver->forAuthor($author), $author->id, 'author '.$author->nicename);
        }
    }

    private function base(string $option, string $default): string
    {
        $base = trim((string) $this->db->option($option), '/');

        return $base === '' ? $default : $base;
    }
}
