<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Mapping;

use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ItsJustVita\WpMigrator\Content\Blocks\Block;
use ItsJustVita\WpMigrator\Fields\FieldResolver;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Mapping\Concerns\StoresSourceTimestamps;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Models\Term;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;
use ItsJustVita\WpMigrator\Support\WpDates;

/**
 * Writes posts, pages and custom post types into the package's posts table.
 */
class DefaultPostMapper implements PostMapper
{
    use StoresSourceTimestamps;

    /** Statuses whose post_date is a publish date. */
    protected const array PUBLISHED_STATUSES = ['publish', 'future', 'private'];

    public function __construct(
        protected readonly Repository $config,
        protected readonly FieldResolver $fields,
    ) {}

    public function map(SourcePost $post, ImportContext $context): ?Model
    {
        $model = $context->existing(SourceType::Post, $post->id, Post::class) ?? new Post;

        $model->fill([
            'type' => $post->type,
            'status' => $post->status,
            'title' => $post->title,
            'slug' => $this->slug($post),
            'excerpt' => $post->excerpt !== '' ? $post->excerpt : null,
            'content_raw' => $post->content,
            'fields' => $this->fields($post, $context),
            'template' => $this->template($post),
            'parent_id' => $context->resolve(SourceType::Post, $post->parentId, $post->id, 'parent post', Post::class),
            'menu_order' => $post->menuOrder,
            'author_id' => $context->resolve(SourceType::User, $post->authorId, $post->id, 'author'),
            'featured_media_id' => $context->resolve(SourceType::Post, $this->thumbnailId($post), $post->id, 'featured media', Media::class),
            'published_at' => in_array($post->status, self::PUBLISHED_STATUSES, true) ? WpDates::forStorage($post->publishedAt) : null,
        ]);

        if ($post->blocks !== null) {
            $model->setAttribute('blocks', array_map(fn (Block $block): array => $block->toArray(), $post->blocks));
        }

        $this->saveWithSourceTimestamps($model, $post->publishedAt, $post->modifiedAt);

        $model->terms()->sync($this->termIds($post, $context));

        return $model;
    }

    protected function slug(SourcePost $post): string
    {
        if ($post->slug !== '') {
            return $post->slug;
        }

        $slug = Str::slug($post->title);

        return $slug !== '' ? $slug : (string) $post->id;
    }

    protected function template(SourcePost $post): ?string
    {
        $template = $post->meta->get('_wp_page_template');

        return $template === null || $template === '' || $template === 'default' ? null : $template;
    }

    protected function thumbnailId(SourcePost $post): ?int
    {
        $id = $post->meta->get('_thumbnail_id');

        return $id !== null && ctype_digit($id) && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * The resolved fields of the post, or its plain meta when nothing resolved them.
     *
     * @return array<string, mixed>
     */
    protected function fields(SourcePost $post, ImportContext $context): array
    {
        return $post->fields ?? $this->fields->plain($post->meta, $post->id, $context);
    }

    /**
     * @return list<int>
     */
    protected function termIds(SourcePost $post, ImportContext $context): array
    {
        $ids = [];

        foreach ($post->terms as $term) {
            $id = $context->resolve(SourceType::Term, $term->id, $post->id, 'term', Term::class);

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
