<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection\Detectors;

use Illuminate\Database\Query\Builder;
use ItsJustVita\WpMigrator\Detection\Detector;
use ItsJustVita\WpMigrator\Detection\Finding;
use ItsJustVita\WpMigrator\Source\WpDatabase;

/**
 * Base for detectors that find affected posts. Subclasses describe the
 * affected posts as a query; this class turns it into a count and samples.
 */
abstract class PostDetector implements Detector
{
    protected const int SAMPLE_SIZE = 5;

    abstract protected function key(): string;

    abstract protected function label(): string;

    /**
     * A query over the posts table restricted to the affected posts.
     */
    abstract protected function affectedPosts(WpDatabase $db): Builder;

    public function detect(WpDatabase $db): Finding
    {
        $posts = $this->affectedPosts($db);
        $idColumn = $db->tableName('posts').'.ID';

        $count = (clone $posts)->distinct()->count($idColumn);

        if ($count === 0) {
            return Finding::none($this->key(), $this->label());
        }

        $sampleIds = array_values((clone $posts)
            ->distinct()
            ->orderBy($idColumn)
            ->limit(self::SAMPLE_SIZE)
            ->pluck($idColumn)
            ->map(fn (mixed $id): int => (int) $id)
            ->all());

        return Finding::posts($this->key(), $this->label(), $count, $sampleIds);
    }

    /**
     * Content posts that have the given meta key, optionally filtered by value.
     */
    protected function postsWithMeta(WpDatabase $db, string $metaKey, ?callable $valueFilter = null): Builder
    {
        $posts = $db->tableName('posts');
        $meta = $db->tableName('postmeta');

        $query = $db->posts()
            ->join($meta, $meta.'.post_id', '=', $posts.'.ID')
            ->where($meta.'.meta_key', $metaKey);

        if ($valueFilter !== null) {
            $valueFilter($query, $meta.'.meta_value');
        }

        return $query;
    }
}
