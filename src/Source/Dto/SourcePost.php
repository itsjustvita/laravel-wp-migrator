<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Dto;

use Carbon\CarbonImmutable;
use ItsJustVita\WpMigrator\Content\Blocks\Block;

final readonly class SourcePost
{
    /**
     * @param  CarbonImmutable|null  $publishedAt  post_date: the publish date, the scheduled date for
     *                                             future posts, or the last save for drafts
     * @param  list<SourceTerm>  $terms
     * @param  list<Block>|null  $blocks  the transformed content; null when it was not transformed
     * @param  array<string, mixed>|null  $fields  the resolved custom fields; null when they were not resolved
     */
    public function __construct(
        public int $id,
        public string $type,
        public string $status,
        public string $title,
        public string $slug,
        public string $content,
        public string $excerpt,
        public ?int $authorId,
        public ?int $parentId,
        public int $menuOrder,
        public ?string $mimeType,
        public ?string $guid,
        public ?CarbonImmutable $publishedAt,
        public ?CarbonImmutable $modifiedAt,
        public MetaBag $meta,
        public array $terms = [],
        public ?array $blocks = null,
        public ?array $fields = null,
    ) {}

    /**
     * @param  list<Block>  $blocks
     * @param  array<string, mixed>  $fields
     */
    public function withTransformed(array $blocks, array $fields): self
    {
        return new self(
            $this->id, $this->type, $this->status, $this->title, $this->slug, $this->content, $this->excerpt,
            $this->authorId, $this->parentId, $this->menuOrder, $this->mimeType, $this->guid,
            $this->publishedAt, $this->modifiedAt, $this->meta, $this->terms, $blocks, $fields,
        );
    }

    /**
     * @return list<int>
     */
    public function termIds(): array
    {
        return array_map(fn (SourceTerm $term): int => $term->id, $this->terms);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            'author_id' => $this->authorId,
            'parent_id' => $this->parentId,
            'menu_order' => $this->menuOrder,
            'mime_type' => $this->mimeType,
            'guid' => $this->guid,
            'published_at' => $this->publishedAt?->toIso8601String(),
            'modified_at' => $this->modifiedAt?->toIso8601String(),
            'meta' => $this->meta->toArray(),
            'terms' => $this->termIds(),
        ];
    }
}
