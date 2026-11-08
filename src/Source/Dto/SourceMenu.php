<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Dto;

/**
 * A classic menu: a term of the nav_menu taxonomy.
 */
final readonly class SourceMenu
{
    /**
     * @param  list<string>  $locations  theme locations the menu is assigned to
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public array $locations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'locations' => $this->locations];
    }
}
