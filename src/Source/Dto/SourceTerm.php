<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Dto;

final readonly class SourceTerm
{
    public function __construct(
        public int $id,
        public int $taxonomyId,
        public string $taxonomy,
        public string $name,
        public string $slug,
        public string $description,
        public ?int $parentId,
        public MetaBag $meta,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'taxonomy' => $this->taxonomy,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'parent_id' => $this->parentId,
            'meta' => $this->meta->toArray(),
        ];
    }
}
