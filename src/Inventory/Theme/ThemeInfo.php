<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory\Theme;

/**
 * The header of a theme's style.css.
 */
final readonly class ThemeInfo
{
    /**
     * @param  string|null  $parent  the parent theme's directory, for child themes
     */
    public function __construct(
        public string $slug,
        public string $path,
        public string $name,
        public ?string $version = null,
        public ?string $textDomain = null,
        public ?string $parent = null,
    ) {}

    public function title(): string
    {
        return '"'.$this->name.'"'.($this->version === null ? '' : ' '.$this->version);
    }
}
