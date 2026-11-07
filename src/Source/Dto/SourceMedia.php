<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Dto;

use Carbon\CarbonImmutable;

final readonly class SourceMedia
{
    /**
     * @param  string|null  $file  path relative to the uploads directory
     * @param  array<string, SourceMediaSize>  $sizes
     */
    public function __construct(
        public int $id,
        public ?string $file,
        public ?string $url,
        public ?string $mimeType,
        public string $title,
        public ?string $alt,
        public ?string $caption,
        public ?string $description,
        public ?int $width,
        public ?int $height,
        public ?int $filesize,
        public array $sizes,
        public ?int $parentId,
        public ?int $authorId,
        public ?CarbonImmutable $uploadedAt,
        public ?CarbonImmutable $modifiedAt,
        public bool $metadataMalformed,
        public MetaBag $meta,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'file' => $this->file,
            'url' => $this->url,
            'mime_type' => $this->mimeType,
            'title' => $this->title,
            'alt' => $this->alt,
            'caption' => $this->caption,
            'description' => $this->description,
            'width' => $this->width,
            'height' => $this->height,
            'filesize' => $this->filesize,
            'sizes' => array_map(fn (SourceMediaSize $size): array => $size->toArray(), $this->sizes),
            'parent_id' => $this->parentId,
            'author_id' => $this->authorId,
            'uploaded_at' => $this->uploadedAt?->toIso8601String(),
            'modified_at' => $this->modifiedAt?->toIso8601String(),
            'meta' => $this->meta->toArray(),
        ];
    }
}
