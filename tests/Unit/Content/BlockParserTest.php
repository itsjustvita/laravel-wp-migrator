<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Content\Blocks\Block;
use ItsJustVita\WpMigrator\Content\Blocks\BlockParser;

/**
 * @return list<Block>
 */
function parseBlocks(string $content): array
{
    return (new BlockParser)->parse($content);
}

/**
 * All text a tree carries, in order, including nested blocks.
 */
function treeText(array $blocks): string
{
    $text = '';

    foreach ($blocks as $block) {
        if ($block->innerContent === null) {
            $text .= $block->innerHtml;

            continue;
        }

        $inner = 0;

        foreach ($block->innerContent as $part) {
            $text .= $part ?? treeText([$block->innerBlocks[$inner++]]);
        }
    }

    return $text;
}

it('returns nothing for empty content', function () {
    expect(parseBlocks(''))->toBe([])
        ->and(parseBlocks("  \n\n "))->toBe([]);
});

it('parses a block and implies the core namespace', function () {
    $blocks = parseBlocks("<!-- wp:paragraph -->\n<p>Hi</p>\n<!-- /wp:paragraph -->");

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->name)->toBe('core/paragraph')
        ->and($blocks[0]->attrs)->toBe([])
        ->and($blocks[0]->innerHtml)->toBe("\n<p>Hi</p>\n")
        ->and($blocks[0]->innerBlocks)->toBe([])
        ->and($blocks[0]->innerContent)->toBeNull();
});

it('reads json attributes', function () {
    $blocks = parseBlocks('<!-- wp:heading {"level":3,"style":{"color":{"text":"#fff"}}} --><h3>T</h3><!-- /wp:heading -->');

    expect($blocks[0]->attrs)->toBe(['level' => 3, 'style' => ['color' => ['text' => '#fff']]]);
});

it('parses self-closing blocks with and without attributes', function () {
    $blocks = parseBlocks('<!-- wp:latest-posts {"postsToShow":3} /--><!-- wp:spacer /-->');

    expect(array_map(fn (Block $b) => $b->name, $blocks))->toBe(['core/latest-posts', 'core/spacer'])
        ->and($blocks[0]->attrs)->toBe(['postsToShow' => 3])
        ->and($blocks[0]->innerHtml)->toBe('')
        ->and($blocks[1]->attrs)->toBe([]);
});

it('keeps explicit namespaces', function () {
    $blocks = parseBlocks('<!-- wp:acme/hero-banner_2 {"a":1} -->x<!-- /wp:acme/hero-banner_2 -->');

    expect($blocks[0]->name)->toBe('acme/hero-banner_2');
});

it('matches a closer that spells out the implied namespace', function () {
    $blocks = parseBlocks('<!-- wp:paragraph -->x<!-- /wp:core/paragraph -->');

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->name)->toBe('core/paragraph');
});

it('nests blocks and remembers where inner blocks sit in the html', function () {
    $blocks = parseBlocks(
        '<!-- wp:group --><div class="g">'
        .'<!-- wp:paragraph --><p>A</p><!-- /wp:paragraph -->'
        .'<hr/>'
        .'<!-- wp:paragraph --><p>B</p><!-- /wp:paragraph -->'
        .'</div><!-- /wp:group -->'
    );

    $group = $blocks[0];

    expect($group->innerHtml)->toBe('<div class="g"><hr/></div>')
        ->and($group->innerContent)->toBe(['<div class="g">', null, '<hr/>', null, '</div>'])
        ->and(array_map(fn (Block $b) => $b->innerHtml, $group->innerBlocks))->toBe(['<p>A</p>', '<p>B</p>']);
});

it('parses several levels of nesting', function () {
    $blocks = parseBlocks(
        '<!-- wp:columns --><div>'
        .'<!-- wp:column --><div>'
        .'<!-- wp:group --><section><!-- wp:image {"id":5} --><img/><!-- /wp:image --></section><!-- /wp:group -->'
        .'</div><!-- /wp:column -->'
        .'</div><!-- /wp:columns -->'
    );

    $image = $blocks[0]->innerBlocks[0]->innerBlocks[0]->innerBlocks[0];

    expect($image->name)->toBe('core/image')
        ->and($image->attrs)->toBe(['id' => 5]);
});

it('allows a block to nest inside a block of the same name', function () {
    $blocks = parseBlocks('<!-- wp:group -->a<!-- wp:group -->b<!-- /wp:group -->c<!-- /wp:group -->');

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->innerContent)->toBe(['a', null, 'c'])
        ->and($blocks[0]->innerBlocks[0]->innerHtml)->toBe('b');
});

it('turns html between blocks into freeform blocks and drops blank gaps', function () {
    $blocks = parseBlocks("<p>before</p>\n\n<!-- wp:spacer /-->\n\n<!-- wp:spacer /-->\n<p>after</p>\n");

    expect(array_map(fn (Block $b) => $b->name, $blocks))
        ->toBe([Block::FREEFORM, 'core/spacer', 'core/spacer', Block::FREEFORM])
        ->and($blocks[0]->innerHtml)->toBe('<p>before</p>')
        ->and($blocks[3]->innerHtml)->toBe('<p>after</p>');
});

it('returns a single freeform block for content without markers', function () {
    $blocks = parseBlocks("Line one\n\nLine two <!--more--> and <!-- a comment -->");

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->name)->toBe(Block::FREEFORM)
        ->and($blocks[0]->isFreeform())->toBeTrue()
        ->and($blocks[0]->innerHtml)->toBe("Line one\n\nLine two <!--more--> and <!-- a comment -->");
});

it('accepts attributes that contain comment terminators and braces', function (string $json, array $expected) {
    $blocks = parseBlocks('<!-- wp:acme/x '.$json.' -->y<!-- /wp:acme/x -->');

    expect($blocks[0]->name)->toBe('acme/x')
        ->and($blocks[0]->attrs)->toBe($expected)
        ->and($blocks[0]->innerHtml)->toBe('y');
})->with([
    'terminator in a string' => ['{"t":"a --> b"}', ['t' => 'a --> b']],
    'self closer in a string' => ['{"t":"a } /--> b"}', ['t' => 'a } /--> b']],
    'escaped dashes' => ['{"t":"-->"}', ['t' => '-->']],
    'nested braces' => ['{"a":{"b":{"c":1}}}', ['a' => ['b' => ['c' => 1]]]],
    'empty object' => ['{}', []],
]);

it('tolerates extra whitespace inside markers', function () {
    $blocks = parseBlocks("<!--  wp:heading\n{\"level\":2}\n-->x<!--\t/wp:heading  -->");

    expect($blocks[0]->name)->toBe('core/heading')
        ->and($blocks[0]->attrs)->toBe(['level' => 2]);
});

it('treats malformed markers as plain html and keeps them', function (string $content) {
    $blocks = parseBlocks($content);

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->isFreeform())->toBeTrue()
        ->and($blocks[0]->innerHtml)->toBe($content);
})->with([
    'invalid json' => '<!-- wp:paragraph {bad} --><p>x</p><!-- /wp:paragraph -->',
    'truncated json' => '<!-- wp:paragraph {"a":1 --><p>x</p><!-- /wp:paragraph -->',
    'json array' => '<!-- wp:paragraph [1,2] --><p>x</p><!-- /wp:paragraph -->',
    'json scalar' => '<!-- wp:paragraph 12 --><p>x</p><!-- /wp:paragraph -->',
    'uppercase name' => '<!-- wp:Paragraph --><p>x</p><!-- /wp:Paragraph -->',
    'name starting with a digit' => '<!-- wp:1para --><p>x</p><!-- /wp:1para -->',
    'no space after opener' => '<!--wp:paragraph --><p>x</p><!--/wp:paragraph -->',
    'no space before terminator' => '<!-- wp:paragraph--><p>x</p><!-- /wp:paragraph-->',
    'no space after attributes' => '<!-- wp:acme/x {"a":1}-->x<!-- /wp:acme/x -->',
    'too many namespaces' => '<!-- wp:a/b/c -->x<!-- /wp:a/b/c -->',
    'closer with attributes' => '<p>x</p><!-- /wp:paragraph {"a":1} -->',
    'closer that is also void' => '<p>x</p><!-- /wp:paragraph /-->',
    'unterminated comment' => '<p>x</p><!-- wp:paragraph',
    'unterminated attributes' => '<p>x</p><!-- wp:paragraph {"a":1}',
    'only an opener of a comment' => '<!--',
    'stray terminator' => '--> x',
    'invalid utf-8 in attributes' => "<!-- wp:acme/x {\"a\":\"\xB1\"} -->y",
]);

it('falls back to freeform for an unclosed block', function () {
    $content = '<!-- wp:paragraph --><p>x</p>';

    $blocks = parseBlocks($content);

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->isFreeform())->toBeTrue()
        ->and($blocks[0]->innerHtml)->toBe($content);
});

it('falls back to freeform for a stray closer', function () {
    $content = '<p>x</p><!-- /wp:paragraph -->';

    $blocks = parseBlocks($content);

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->isFreeform())->toBeTrue()
        ->and($blocks[0]->innerHtml)->toBe($content);
});

it('keeps complete inner blocks when their parent is never closed', function () {
    $blocks = parseBlocks('<!-- wp:group --><div><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></div>');

    expect(array_map(fn (Block $b) => $b->name, $blocks))->toBe([Block::FREEFORM, 'core/paragraph', Block::FREEFORM])
        ->and($blocks[0]->innerHtml)->toBe('<!-- wp:group --><div>')
        ->and($blocks[1]->innerHtml)->toBe('<p>x</p>')
        ->and($blocks[2]->innerHtml)->toBe('</div>');
});

it('dissolves an unclosed inner block into its parent when the parent closes', function () {
    $blocks = parseBlocks('<!-- wp:group --><div><!-- wp:paragraph --><p>x</p></div><!-- /wp:group -->');

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->name)->toBe('core/group')
        ->and($blocks[0]->innerBlocks)->toBe([])
        ->and($blocks[0]->innerHtml)->toBe('<div><!-- wp:paragraph --><p>x</p></div>')
        ->and($blocks[0]->innerContent)->toBeNull();
});

it('keeps blocks that were complete inside a dissolved block', function () {
    $blocks = parseBlocks('<!-- wp:group -->a<!-- wp:column -->b<!-- wp:spacer /-->c<!-- /wp:group -->');

    $group = $blocks[0];

    expect($group->name)->toBe('core/group')
        ->and($group->innerContent)->toBe(['a<!-- wp:column -->b', null, 'c'])
        ->and($group->innerBlocks[0]->name)->toBe('core/spacer');
});

it('treats a closer without a matching opener inside a block as text', function () {
    $blocks = parseBlocks('<!-- wp:group -->a<!-- /wp:column -->b<!-- /wp:group -->');

    expect($blocks[0]->name)->toBe('core/group')
        ->and($blocks[0]->innerHtml)->toBe('a<!-- /wp:column -->b');
});

it('stops nesting at a safe depth without losing content', function () {
    $depth = 200;
    $content = str_repeat('<!-- wp:group -->a', $depth).'core'.str_repeat('b<!-- /wp:group -->', $depth);

    $blocks = parseBlocks($content);

    $measure = function (array $blocks) use (&$measure): int {
        $max = 0;

        foreach ($blocks as $block) {
            $max = max($max, 1 + $measure($block->innerBlocks));
        }

        return $max;
    };

    expect($measure($blocks))->toBeLessThanOrEqual(BlockParser::MAX_DEPTH)
        ->and(substr_count(treeText($blocks), 'a'))->toBe($depth)
        ->and(substr_count(treeText($blocks), 'b'))->toBe($depth)
        ->and(treeText($blocks))->toContain('core');
});

it('never throws on garbage', function (string $content) {
    expect(parseBlocks($content))->toBeArray();
})->with([
    '<!-- wp:',
    '<!-- wp:a',
    '<!-- wp:a ',
    '<!-- wp:a {',
    '<!-- wp:a {"b":',
    '<!-- wp:a { -->',
    '<!-- /wp:',
    '<!-- /wp:a',
    "\xFF\xFE<!-- wp:a --><\x00",
    '<!-- wp:a --><!-- wp:b --><!-- /wp:a --><!-- /wp:b -->',
    '<!-- /wp:a --><!-- wp:a -->',
]);

it('stays fast on many broken markers', function (string $content) {
    $start = microtime(true);
    $blocks = parseBlocks($content);

    expect(microtime(true) - $start)->toBeLessThan(5.0)
        ->and($blocks)->toHaveCount(1);
})->with([
    'no terminator after any marker but the last' => [str_repeat('<!-- wp:a {"b":1 ', 100000).'-->'],
    'a terminator after every marker' => [str_repeat('<!-- wp:a {"b":1 -->', 100000)],
    'terminators that almost end the attributes' => [str_repeat('<!-- wp:a {"b":"} -->', 50000)],
    'no terminator at all' => [str_repeat('<!-- wp:a {"b":1 ', 100000)],
    'markers before one distant terminator' => [str_repeat('<!-- wp:a {"b":"', 20000).str_repeat('x', 1000000).'"} -->'],
    'markers before many distant terminators' => [str_repeat('<!-- wp:a {"b":"', 20000).str_repeat('x', 500000).str_repeat('"} -->', 40)],
]);

it('treats attributes that contain a comment opener as plain html', function () {
    $content = '<!-- wp:acme/x {"html":"<!-- note -->"} -->y<!-- /wp:acme/x -->';

    $blocks = parseBlocks($content);

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->isFreeform())->toBeTrue()
        ->and($blocks[0]->innerHtml)->toBe($content);
});

it('converts to and from the stored array form', function () {
    $tree = parseBlocks('<p>free</p><!-- wp:group {"a":1} --><div><!-- wp:spacer /--></div><!-- /wp:group -->');

    $stored = json_decode(json_encode(array_map(fn (Block $b) => $b->toArray(), $tree)), true);

    expect($stored[0])->toBe(['name' => Block::FREEFORM, 'attrs' => [], 'innerHTML' => '<p>free</p>', 'innerBlocks' => []])
        ->and($stored[1]['name'])->toBe('core/group')
        ->and($stored[1]['innerHTML'])->toBe('<div></div>')
        ->and($stored[1]['innerContent'])->toBe(['<div>', null, '</div>'])
        ->and($stored[1]['innerBlocks'][0]['name'])->toBe('core/spacer');

    $restored = array_map(Block::fromArray(...), $stored);

    expect(array_map(fn (Block $b) => $b->toArray(), $restored))->toBe($stored);
});

it('reads damaged stored arrays without failing', function () {
    $block = Block::fromArray(['name' => 5, 'attrs' => 'x', 'innerHTML' => null, 'innerBlocks' => ['nope', ['name' => 'core/spacer']], 'innerContent' => 'y']);

    expect($block->name)->toBe(Block::FREEFORM)
        ->and($block->attrs)->toBe([])
        ->and($block->innerHtml)->toBe('')
        ->and($block->innerBlocks)->toHaveCount(1)
        ->and($block->innerBlocks[0]->name)->toBe('core/spacer')
        ->and($block->innerContent)->toBeNull();
});
