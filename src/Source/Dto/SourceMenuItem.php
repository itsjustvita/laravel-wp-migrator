<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Dto;

/**
 * An item of a classic menu: a post of type nav_menu_item with its settings
 * in post meta.
 */
final readonly class SourceMenuItem
{
    /**
     * @param  string  $type  post_type, taxonomy, custom or post_type_archive
     * @param  string  $object  the post type or taxonomy linked to, or "custom"
     * @param  string|null  $objectTitle  title or name of what the item links to, for items without their own title
     * @param  list<string>  $classes
     */
    public function __construct(
        public int $id,
        public int $menuId,
        public ?int $parentId,
        public int $order,
        public string $title,
        public string $type,
        public string $object,
        public ?int $objectId,
        public ?string $objectTitle,
        public string $url,
        public string $target,
        public array $classes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
