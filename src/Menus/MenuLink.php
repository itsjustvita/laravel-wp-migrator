<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Menus;

final readonly class MenuLink
{
    /**
     * @param  bool  $unpublished  the link points to a draft, private or scheduled post
     */
    public function __construct(
        public ?string $url,
        public ?string $linkableType = null,
        public ?int $linkableId = null,
        public bool $unpublished = false,
    ) {}

    public static function unpublished(): self
    {
        return new self(null, unpublished: true);
    }

    /**
     * @return array{url: string|null, linkable_type: string|null, linkable_id: int|null}
     */
    public function attributes(): array
    {
        return ['url' => $this->url, 'linkable_type' => $this->linkableType, 'linkable_id' => $this->linkableId];
    }
}
