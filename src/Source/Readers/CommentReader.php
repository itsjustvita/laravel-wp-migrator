<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Readers;

use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Query\Builder;
use ItsJustVita\WpMigrator\Source\Dto\SourceComment;
use ItsJustVita\WpMigrator\Support\Hierarchy;
use ItsJustVita\WpMigrator\Support\WpDates;

/**
 * Comments of the configured statuses and types, parents before replies.
 */
class CommentReader extends Reader
{
    public function count(?CarbonImmutable $since = null): int
    {
        return $this->query($since)->count();
    }

    /**
     * @return Generator<int, list<SourceComment>>
     */
    public function chunks(?CarbonImmutable $since = null): Generator
    {
        $parents = [];

        foreach ($this->query($since)->orderBy('comment_ID')->get(['comment_ID', 'comment_parent']) as $row) {
            $parents[self::int($row->comment_ID)] = self::int($row->comment_parent);
        }

        $timezone = $this->db->timezone();

        foreach (Hierarchy::levels($parents) as $level) {
            foreach (array_chunk($level, $this->chunkSize()) as $ids) {
                $comments = [];

                foreach ($this->db->table('comments')->whereIn('comment_ID', $ids)->orderBy('comment_ID')->get() as $row) {
                    $comments[] = new SourceComment(
                        id: self::int($row->comment_ID),
                        postId: self::int($row->comment_post_ID),
                        parentId: self::idOrNull($row->comment_parent),
                        userId: self::idOrNull($row->user_id),
                        authorName: self::string($row->comment_author),
                        authorEmail: self::string($row->comment_author_email),
                        authorUrl: self::string($row->comment_author_url),
                        content: self::string($row->comment_content),
                        approved: self::string($row->comment_approved),
                        type: self::string($row->comment_type),
                        date: WpDates::parse(self::nullableString($row->comment_date_gmt), self::nullableString($row->comment_date), $timezone),
                    );
                }

                yield $comments;
            }
        }
    }

    private function query(?CarbonImmutable $since): Builder
    {
        $statuses = array_map(strval(...), array_keys($this->config->array('wp-migrator.comments.statuses')));

        $query = $this->db->table('comments')
            ->whereIn('comment_approved', $statuses)
            ->whereIn('comment_type', $this->config->array('wp-migrator.comments.types'));

        if ($since !== null) {
            $query->where('comment_date_gmt', '>=', $since->utc()->format('Y-m-d H:i:s'));
        }

        return $query;
    }
}
