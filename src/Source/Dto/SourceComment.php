<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Dto;

use Carbon\CarbonImmutable;

final readonly class SourceComment
{
    /**
     * @param  string  $approved  WP's status: "1", "0", "spam" or "trash"
     */
    public function __construct(
        public int $id,
        public int $postId,
        public ?int $parentId,
        public ?int $userId,
        public string $authorName,
        public string $authorEmail,
        public string $authorUrl,
        public string $content,
        public string $approved,
        public string $type,
        public ?CarbonImmutable $date,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [...get_object_vars($this), 'date' => $this->date?->toIso8601String()];
    }
}
