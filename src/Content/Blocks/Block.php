<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Blocks;

/**
 * One node of a parsed block tree, stored in posts.blocks as
 * {name, attrs, innerHTML, innerBlocks}.
 *
 * innerHtml holds the block's own markup without its inner blocks. A block
 * with inner blocks also has innerContent: its markup in pieces, with null
 * where an inner block belongs, so wrappers can be put back together.
 */
final readonly class Block
{
    /** HTML outside of any block, such as classic editor content. */
    public const string FREEFORM = 'core/freeform';

    /**
     * @param  array<array-key, mixed>  $attrs
     * @param  list<Block>  $innerBlocks
     * @param  list<string|null>|null  $innerContent
     */
    public function __construct(
        public string $name,
        public array $attrs = [],
        public string $innerHtml = '',
        public array $innerBlocks = [],
        public ?array $innerContent = null,
    ) {}

    public static function freeform(string $html): self
    {
        return new self(self::FREEFORM, innerHtml: $html);
    }

    public function isFreeform(): bool
    {
        return $this->name === self::FREEFORM;
    }

    public function attr(string $key, mixed $default = null): mixed
    {
        return $this->attrs[$key] ?? $default;
    }

    /**
     * The markup inside the block's outermost element: "Title" for
     * <h2 class="x">Title</h2>. Without a single outer element, the markup itself.
     */
    public function unwrapped(): string
    {
        $html = trim($this->innerHtml);

        if (preg_match('/^<([a-zA-Z][a-zA-Z0-9-]*)(?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+>(.*)<\/\1\s*>$/s', $html, $matches) !== 1) {
            return $html;
        }

        return $this->isBalanced($matches[2], $matches[1]) ? $matches[2] : $html;
    }

    /**
     * Whether an element of this name stays open or closed correctly all the
     * way through, which tells <p>a</p><p>b</p> apart from one outer element.
     */
    private function isBalanced(string $html, string $tag): bool
    {
        $depth = 0;

        preg_match_all('/<(\/?)'.preg_quote($tag, '/').'\b[^>]*>/i', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $depth += $match[1] === '/' ? -1 : 1;

            if ($depth < 0) {
                return false;
            }
        }

        return $depth === 0;
    }

    /**
     * @return array{name: string, attrs: array<array-key, mixed>, innerHTML: string, innerBlocks: list<array<string, mixed>>, innerContent?: list<string|null>}
     */
    public function toArray(): array
    {
        $array = [
            'name' => $this->name,
            'attrs' => $this->attrs,
            'innerHTML' => $this->innerHtml,
            'innerBlocks' => array_map(fn (self $block): array => $block->toArray(), $this->innerBlocks),
        ];

        if ($this->innerContent !== null) {
            $array['innerContent'] = $this->innerContent;
        }

        return $array;
    }

    /**
     * Reads the stored form. Stored data can be edited by hand, so anything
     * of the wrong shape falls back to a harmless default instead of failing.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $innerBlocks = [];

        foreach (is_array($data['innerBlocks'] ?? null) ? $data['innerBlocks'] : [] as $inner) {
            if (is_array($inner)) {
                $innerBlocks[] = self::fromArray($inner);
            }
        }

        $name = $data['name'] ?? null;
        $attrs = $data['attrs'] ?? null;
        $html = $data['innerHTML'] ?? null;

        return new self(
            is_string($name) && $name !== '' ? $name : self::FREEFORM,
            is_array($attrs) ? $attrs : [],
            is_string($html) ? $html : '',
            $innerBlocks,
            self::readInnerContent($data['innerContent'] ?? null, count($innerBlocks)),
        );
    }

    /**
     * @return list<string|null>|null
     */
    private static function readInnerContent(mixed $value, int $innerBlocks): ?array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return null;
        }

        $placeholders = 0;

        foreach ($value as $part) {
            if ($part === null) {
                $placeholders++;
            } elseif (! is_string($part)) {
                return null;
            }
        }

        /** @var list<string|null> $value */
        return $placeholders === $innerBlocks ? $value : null;
    }
}
