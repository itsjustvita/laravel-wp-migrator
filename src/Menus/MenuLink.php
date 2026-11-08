<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Menus;

final readonly class MenuLink
{
    public function __construct(
        public ?string $url,
        public ?string $linkableType = null,
        public ?int $linkableId = null,
    ) {}

    /**
     * @return array{url: string|null, linkable_type: string|null, linkable_id: int|null}
     */
    public function attributes(): array
    {
        return ['url' => $this->url, 'linkable_type' => $this->linkableType, 'linkable_id' => $this->linkableId];
    }
}
