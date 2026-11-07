<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Dto;

/**
 * One resized variant as listed in the attachment metadata. The file lives
 * in the same directory as the original.
 */
final readonly class SourceMediaSize
{
    public function __construct(
        public string $file,
        public ?int $width,
        public ?int $height,
        public ?string $mimeType,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'width' => $this->width,
            'height' => $this->height,
            'mime_type' => $this->mimeType,
        ];
    }
}
