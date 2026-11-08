<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Mapping;

use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Content\CommentHtml;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Mapping\Concerns\StoresSourceTimestamps;
use ItsJustVita\WpMigrator\Models\Comment;
use ItsJustVita\WpMigrator\Source\Dto\SourceComment;
use ItsJustVita\WpMigrator\Support\SafeUrl;

/**
 * Attaches comments to whatever model their post was imported as. Comments
 * come from visitors: their HTML is reduced to the tags WP allows in
 * comments, and author URLs other than web addresses are dropped. IP
 * addresses and user agents are not imported.
 */
class DefaultCommentMapper implements CommentMapper
{
    use StoresSourceTimestamps;

    public function __construct(protected readonly Repository $config) {}

    public function map(SourceComment $comment, ImportContext $context): ?Model
    {
        $post = $context->map->find(SourceType::Post, $comment->postId);

        if ($post === null) {
            $context->skip(sprintf('the post it belongs to (%d) was not imported', $comment->postId));

            return null;
        }

        $model = $context->existing(SourceType::Comment, $comment->id, Comment::class) ?? new Comment;
        $statuses = $this->config->array('wp-migrator.comments.statuses');

        $model->fill([
            'commentable_type' => $post->target_type,
            'commentable_id' => $post->target_id,
            'parent_id' => $context->resolve(SourceType::Comment, $comment->parentId, $comment->id, 'parent comment', Comment::class),
            'author_id' => $context->resolve(SourceType::User, $comment->userId, $comment->id, 'comment author'),
            'author_name' => $comment->authorName !== '' ? $comment->authorName : null,
            'author_email' => $comment->authorEmail !== '' ? $comment->authorEmail : null,
            'author_url' => $comment->authorUrl !== '' && SafeUrl::isSafe($comment->authorUrl, ['http', 'https']) ? $comment->authorUrl : null,
            'content' => CommentHtml::sanitize($comment->content),
            'status' => (string) ($statuses[$comment->approved] ?? $comment->approved),
        ]);

        $this->saveWithSourceTimestamps($model, $comment->date, $comment->date);

        return $model;
    }
}
