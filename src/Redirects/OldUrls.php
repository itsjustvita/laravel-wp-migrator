<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Redirects;

use Generator;
use Illuminate\Config\Repository;
use ItsJustVita\WpMigrator\Content\Urls\AuthorReference;
use ItsJustVita\WpMigrator\Content\Urls\MediaUrls;
use ItsJustVita\WpMigrator\Content\Urls\PostUrlIndex;
use ItsJustVita\WpMigrator\Content\Urls\TermReference;
use ItsJustVita\WpMigrator\Content\Urls\UrlResolver;
use ItsJustVita\WpMigrator\Import\ImportMap;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Source\WpDatabase;
use ItsJustVita\WpMigrator\Support\Hierarchy;

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
        private readonly UrlResolver $resolver,
        private readonly ImportMap $map,
        private readonly MediaUrls $media,
        private readonly Repository $config,
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
        yield from $this->terms('category', $pretty ? $this->base('category_base', 'category') : null, 'cat');
        yield from $this->terms('post_tag', $pretty ? $this->base('tag_base', 'tag') : null, 'tag');
        yield from $this->authors($pretty);
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
        $taxonomies = $this->db->tableName('term_taxonomy');
        $terms = $this->db->tableName('terms');

        $rows = $this->db->table('term_taxonomy')
            ->join($terms, $terms.'.term_id', '=', $taxonomies.'.term_id')
            ->where($taxonomies.'.taxonomy', $taxonomy)
            ->orderBy($terms.'.term_id')
            ->get([$terms.'.term_id', $terms.'.slug', $terms.'.name', $taxonomies.'.parent']);

        $slugs = [];
        $parents = [];
        $names = [];

        foreach ($rows as $row) {
            $id = (int) $row->term_id;
            $slugs[$id] = rawurldecode((string) $row->slug);
            $names[$id] = (string) $row->name;
            $parents[$id] = (int) $row->parent;
        }

        foreach (Hierarchy::levels($parents) as $level) {
            foreach ($level as $id) {
                $term = new TermReference($id, $taxonomy, $slugs[$id], $this->termPath($id, $slugs, $parents), $names[$id]);

                $from = [];

                if ($base !== null) {
                    $from[] = '/'.$base.'/'.($taxonomy === 'category' ? $term->path : $term->slug);
                }

                $from[] = '/?'.$queryVar.'='.($queryVar === 'cat' ? $id : $term->slug);

                yield new RedirectCandidate($from, $this->resolver->forTerm($term), $id, $taxonomy.' '.$term->slug);
            }
        }
    }

    /**
     * Authors with at least one post; WP has no archive for the others.
     *
     * @return Generator<int, RedirectCandidate>
     */
    private function authors(bool $pretty): Generator
    {
        $ids = $this->db->posts()
            ->whereNotIn('post_type', $this->config->array('wp-migrator.import.excluded_post_types'))
            ->whereIn('post_status', $this->config->array('wp-migrator.import.post_statuses'))
            ->distinct()
            ->pluck('post_author')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        $rows = $this->db->table('users')->whereIn('ID', $ids)->orderBy('ID')->get(['ID', 'user_nicename', 'display_name']);

        foreach ($rows as $row) {
            $author = new AuthorReference((int) $row->ID, (string) $row->user_nicename, (string) $row->display_name);
            $from = $pretty ? ['/author/'.$author->nicename] : [];
            $from[] = '/?author='.$author->id;

            yield new RedirectCandidate($from, $this->resolver->forAuthor($author), $author->id, 'author '.$author->nicename);
        }
    }

    /**
     * @param  array<int, string>  $slugs
     * @param  array<int, int>  $parents
     */
    private function termPath(int $id, array $slugs, array $parents): string
    {
        $segments = [$slugs[$id]];
        $seen = [$id => true];
        $current = $parents[$id] ?? 0;

        while ($current !== 0 && isset($slugs[$current]) && ! isset($seen[$current])) {
            array_unshift($segments, $slugs[$current]);
            $seen[$current] = true;
            $current = $parents[$current] ?? 0;
        }

        return implode('/', $segments);
    }

    private function base(string $option, string $default): string
    {
        $base = trim((string) $this->db->option($option), '/');

        return $base === '' ? $default : $base;
    }
}
