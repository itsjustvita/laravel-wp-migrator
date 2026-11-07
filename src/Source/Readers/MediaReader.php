<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Readers;

use Generator;
use Illuminate\Database\Query\Builder;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\Dto\SourceMedia;
use ItsJustVita\WpMigrator\Source\Dto\SourceMediaSize;
use ItsJustVita\WpMigrator\Support\MalformedSerializedValue;
use ItsJustVita\WpMigrator\Support\WpDates;
use stdClass;

class MediaReader extends Reader
{
    public function count(MediaFilter $filter): int
    {
        return $this->query($filter)->count();
    }

    /**
     * @return Generator<int, list<SourceMedia>>
     */
    public function chunks(MediaFilter $filter): Generator
    {
        $size = $this->chunkSize();
        $timezone = $this->db->timezone();

        foreach ($this->query($filter)->lazyById($size, 'ID')->chunk($size) as $rows) {
            $ids = array_values($rows->map(fn (object $row): int => self::int($row->ID))->all());
            $meta = $this->meta->load('postmeta', 'post_id', $ids);

            $items = [];

            foreach ($rows as $row) {
                $id = self::int($row->ID);
                $items[] = $this->toMedia($row, $meta[$id] ?? MetaBag::empty(), $timezone);
            }

            yield $items;
        }
    }

    private function query(MediaFilter $filter): Builder
    {
        $query = $this->db->table('posts')->where('post_type', 'attachment');

        if ($filter->since !== null) {
            $query->where('post_modified_gmt', '>=', $filter->since->utc()->format('Y-m-d H:i:s'));
        }

        return $query;
    }

    private function toMedia(stdClass $row, MetaBag $meta, string $timezone): SourceMedia
    {
        $metadata = [];
        $malformed = false;

        try {
            $decoded = $meta->decode('_wp_attachment_metadata');
            $metadata = is_array($decoded) ? $decoded : [];
        } catch (MalformedSerializedValue) {
            $malformed = true;
        }

        $sizes = [];

        foreach (is_array($metadata['sizes'] ?? null) ? $metadata['sizes'] : [] as $name => $size) {
            if (! is_array($size) || self::nullableString($size['file'] ?? null) === null) {
                continue;
            }

            $sizes[(string) $name] = new SourceMediaSize(
                file: self::string($size['file']),
                width: self::nullableInt($size['width'] ?? null),
                height: self::nullableInt($size['height'] ?? null),
                mimeType: self::nullableString($size['mime-type'] ?? null),
            );
        }

        return new SourceMedia(
            id: self::int($row->ID),
            file: $meta->get('_wp_attached_file'),
            url: self::nullableString($row->guid),
            mimeType: self::nullableString($row->post_mime_type),
            title: self::string($row->post_title),
            alt: $meta->get('_wp_attachment_image_alt'),
            caption: self::nullableString($row->post_excerpt),
            description: self::nullableString($row->post_content),
            width: self::nullableInt($metadata['width'] ?? null),
            height: self::nullableInt($metadata['height'] ?? null),
            filesize: self::nullableInt($metadata['filesize'] ?? null),
            sizes: $sizes,
            parentId: self::idOrNull($row->post_parent),
            authorId: self::idOrNull($row->post_author),
            uploadedAt: WpDates::parse(self::nullableString($row->post_date_gmt), self::nullableString($row->post_date), $timezone),
            modifiedAt: WpDates::parse(self::nullableString($row->post_modified_gmt), self::nullableString($row->post_modified), $timezone),
            metadataMalformed: $malformed,
            meta: $meta,
        );
    }
}
