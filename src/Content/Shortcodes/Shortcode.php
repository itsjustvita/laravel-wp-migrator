<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Shortcodes;

/**
 * One parsed shortcode. Named attributes have lowercase string keys,
 * attributes without a name have numeric keys in order of appearance.
 */
final readonly class Shortcode
{
    /**
     * @param  array<int|string, string>  $attrs
     * @param  string|null  $content  null for the self-closing form
     */
    public function __construct(
        public string $tag,
        public array $attrs = [],
        public ?string $content = null,
    ) {}

    public function attr(int|string $key, ?string $default = null): ?string
    {
        return $this->attrs[$key] ?? $default;
    }
}
