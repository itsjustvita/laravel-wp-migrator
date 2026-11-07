<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Blocks;

use ItsJustVita\WpMigrator\Support\JsonDepth;

/**
 * Keeps a block tree within the JSON depth a database accepts. Where the tree
 * would nest too deeply, inner blocks are merged into the HTML of their
 * parent, so the markup survives and only the block structure is lost.
 */
final class BlockDepth
{
    /**
     * @param  list<Block>  $blocks
     * @param  int  $maxDepth  the deepest nesting of arrays and objects allowed in the stored list
     * @param  int|null  $merged  set to the number of blocks that were merged into their parent
     *
     * @param-out int $merged
     *
     * @return list<Block>
     */
    public static function limit(array $blocks, int $maxDepth, ?int &$merged = null): array
    {
        $merged = 0;

        return self::limitList($blocks, $maxDepth, $merged);
    }

    /**
     * @param  list<Block>  $blocks
     * @return list<Block>
     */
    private static function limitList(array $blocks, int $budget, int &$merged): array
    {
        $limited = [];

        foreach ($blocks as $block) {
            $limited[] = self::limitBlock($block, $budget - 1, $merged);
        }

        return $limited;
    }

    /**
     * A stored block is an object (one level) holding its attributes, its
     * list of inner blocks and the list of its HTML pieces.
     */
    private static function limitBlock(Block $block, int $budget, int &$merged): Block
    {
        $attrsDepth = max(1, JsonDepth::of($block->attrs));

        if ($attrsDepth > $budget - 1) {
            $merged += self::count($block->innerBlocks);

            return Block::freeform(self::html($block));
        }

        if ($block->innerBlocks === []) {
            return $block;
        }

        // The inner list needs one level, each inner block at least two more.
        if ($budget - 1 < 3) {
            $merged += self::count($block->innerBlocks);

            return new Block($block->name, $block->attrs, self::html($block));
        }

        return new Block(
            $block->name,
            $block->attrs,
            $block->innerHtml,
            self::limitList($block->innerBlocks, $budget - 1, $merged),
            $block->innerContent,
        );
    }

    /**
     * The block's HTML with the HTML of all inner blocks in its place.
     */
    private static function html(Block $block): string
    {
        if ($block->innerContent === null) {
            return $block->innerHtml.implode('', array_map(self::html(...), $block->innerBlocks));
        }

        $html = '';
        $next = 0;

        foreach ($block->innerContent as $part) {
            if ($part !== null) {
                $html .= $part;
            } elseif (isset($block->innerBlocks[$next])) {
                $html .= self::html($block->innerBlocks[$next++]);
            }
        }

        return $html;
    }

    /**
     * @param  list<Block>  $blocks
     */
    private static function count(array $blocks): int
    {
        $count = 0;

        foreach ($blocks as $block) {
            $count += 1 + self::count($block->innerBlocks);
        }

        return $count;
    }
}
