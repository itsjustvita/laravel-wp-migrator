<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Blocks;

/**
 * A block the parser has seen the opening marker of, collecting its content
 * until the closing marker arrives. The outermost level of a document is one
 * of these as well.
 *
 * @internal
 */
final class OpenBlock
{
    /** @var list<string|Block> */
    private array $parts = [];

    /**
     * @param  array<array-key, mixed>  $attrs
     */
    public function __construct(
        public readonly string $name = '',
        public readonly array $attrs = [],
        public readonly string $marker = '',
    ) {}

    public function append(string|Block $part): void
    {
        $last = array_key_last($this->parts);

        if (is_string($part) && $last !== null && is_string($this->parts[$last])) {
            $this->parts[$last] .= $part;

            return;
        }

        $this->parts[] = $part;
    }

    /**
     * @return list<string|Block>
     */
    public function parts(): array
    {
        return $this->parts;
    }

    public function toBlock(): Block
    {
        $html = '';
        $innerBlocks = [];
        $innerContent = [];

        foreach ($this->parts as $part) {
            if ($part instanceof Block) {
                $innerBlocks[] = $part;
                $innerContent[] = null;
            } else {
                $html .= $part;
                $innerContent[] = $part;
            }
        }

        return new Block($this->name, $this->attrs, $html, $innerBlocks, $innerBlocks === [] ? null : $innerContent);
    }
}
