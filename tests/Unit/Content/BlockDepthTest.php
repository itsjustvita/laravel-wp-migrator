<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Content\Blocks\Block;
use ItsJustVita\WpMigrator\Content\Blocks\BlockDepth;
use ItsJustVita\WpMigrator\Support\JsonDepth;

function nested(int $levels, array $attrs = []): Block
{
    $block = new Block('core/paragraph', innerHtml: '<p>core</p>');

    for ($i = 0; $i < $levels; $i++) {
        $block = new Block('core/group', $attrs, '<div></div>', [$block], ['<div>', null, '</div>']);
    }

    return $block;
}

/**
 * The HTML a tree stands for, with every inner block in its place.
 */
function blocksHtml(array $blocks): string
{
    $html = '';

    foreach ($blocks as $block) {
        if ($block->innerContent === null) {
            $html .= $block->innerHtml.blocksHtml($block->innerBlocks);

            continue;
        }

        $next = 0;

        foreach ($block->innerContent as $part) {
            $html .= $part ?? blocksHtml([$block->innerBlocks[$next++]]);
        }
    }

    return $html;
}

function storedDepth(array $blocks): int
{
    return JsonDepth::of(json_decode(json_encode(array_map(fn (Block $b) => $b->toArray(), $blocks)), true));
}

it('measures how deeply json containers nest', function (mixed $value, int $expected) {
    expect(JsonDepth::of($value))->toBe($expected);
})->with([
    'scalar' => ['a', 0],
    'null' => [null, 0],
    'empty list' => [[], 1],
    'flat list' => [[1, 2], 1],
    'nested' => [[1, [2, [3]]], 3],
    'object' => [['a' => ['b' => []]], 3],
]);

it('leaves shallow trees alone', function () {
    $blocks = [nested(3)];

    expect(BlockDepth::limit($blocks, 31))->toEqual($blocks);
});

it('merges blocks that nest too deeply into the html of their parent', function () {
    $blocks = BlockDepth::limit([nested(40)], 31);

    expect(storedDepth($blocks))->toBe(31)
        ->and(blocksHtml($blocks))->toBe(blocksHtml([nested(40)]))
        ->and(blocksHtml($blocks))->toBe(str_repeat('<div>', 40).'<p>core</p>'.str_repeat('</div>', 40));
});

it('keeps the order of html and inner blocks when merging', function () {
    $inner = new Block('core/group', [], '<section></section>', [new Block('core/paragraph', innerHtml: '<p>a</p>')], ['<section>', null, '</section>']);
    $outer = new Block('core/group', [], '<div><hr></div>', [$inner, new Block('core/spacer', innerHtml: 'x')], ['<div>', null, '<hr>', null, '</div>']);

    $merged = BlockDepth::limit([$outer], 4)[0];

    expect($merged->innerBlocks)->toBe([])
        ->and($merged->innerContent)->toBeNull()
        ->and($merged->innerHtml)->toBe('<div><section><p>a</p></section><hr>x</div>');
});

it('turns a block whose attributes nest too deeply into freeform html', function () {
    $deepAttrs = ['a' => ['b' => ['c' => ['d' => ['e' => 1]]]]];
    $block = new Block('acme/x', $deepAttrs, '<div>kept</div>');

    $limited = BlockDepth::limit([$block], 5)[0];

    expect($limited->isFreeform())->toBeTrue()
        ->and($limited->innerHtml)->toBe('<div>kept</div>')
        ->and(storedDepth([$limited]))->toBeLessThanOrEqual(5);
});

it('counts the blocks it merged', function () {
    BlockDepth::limit([nested(40)], 31, $merged);
    BlockDepth::limit([nested(3)], 31, $none);

    // A depth of 31 holds 15 levels: list, block object and attributes take
    // three, every further level two. The other 26 of the 41 blocks merge.
    expect($merged)->toBe(26)
        ->and($none)->toBe(0);
});

it('keeps deep attributes when they fit', function () {
    $blocks = [nested(5, ['style' => ['spacing' => ['padding' => ['top' => '1px']]]])];

    expect(BlockDepth::limit($blocks, 31))->toEqual($blocks);
});
