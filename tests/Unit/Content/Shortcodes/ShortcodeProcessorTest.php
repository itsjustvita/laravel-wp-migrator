<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Content\Shortcodes\Shortcode;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeProcessor;

/**
 * Renders known shortcodes as a readable description and collects unknown tags.
 *
 * @param  list<string>  $known
 * @return array{0: string, 1: list<string>}
 */
function processShortcodes(string $html, array $known = ['box', 'gallery', 'inner']): array
{
    $unknown = [];

    $result = (new ShortcodeProcessor)->process(
        $html,
        fn (string $tag): bool => in_array($tag, $known, true),
        function (Shortcode $shortcode) {
            $attrs = json_encode($shortcode->attrs);

            return '{'.$shortcode->tag.' '.$attrs.($shortcode->content === null ? '' : ' |'.$shortcode->content.'|').'}';
        },
        function (string $tag) use (&$unknown): void {
            $unknown[] = $tag;
        },
    );

    return [$result, $unknown];
}

it('leaves text without shortcodes alone', function (string $html) {
    expect(processShortcodes($html))->toBe([$html, []]);
})->with([
    'empty' => [''],
    'plain' => ['<p>Just text</p>'],
    'lone bracket' => ['a [ b ] c'],
    'unterminated' => ['a [gallery'],
    'closing tag without opener' => ['a [/box] b'],
    'number' => ['See note [1] and [2]'],
    'starts with a digit' => ['[3d]'],
    'space after bracket' => ['[ gallery ]'],
    'empty brackets' => ['[]'],
]);

it('parses self-closing forms', function (string $html, string $expected) {
    expect(processShortcodes($html)[0])->toBe($expected);
})->with([
    'no attributes' => ['a [gallery] b', 'a {gallery []} b'],
    'slash' => ['[gallery/]', '{gallery []}'],
    'space and slash' => ['[gallery /]', '{gallery []}'],
    'attributes' => ['[gallery ids="1,2" columns="3"]', '{gallery {"ids":"1,2","columns":"3"}}'],
    'attributes and slash' => ['[gallery ids="1" /]', '{gallery {"ids":"1"}}'],
]);

it('parses enclosing forms', function (string $html, string $expected) {
    expect(processShortcodes($html)[0])->toBe($expected);
})->with([
    'content' => ['[box]inside[/box]', '{box [] |inside|}'],
    'attributes and content' => ['[box a="b"]in side[/box] rest', '{box {"a":"b"} |in side|} rest'],
    'html content' => ['[box]<a href="x">y</a>[/box]', '{box [] |<a href="x">y</a>|}'],
    'empty content' => ['[box][/box]', '{box [] ||}'],
    'multiline content' => ["[box]a\n\nb[/box]", "{box [] |a\n\nb|}"],
    'two in a row' => ['[box]a[/box][box]b[/box]', '{box [] |a|}{box [] |b|}'],
    'self closing then enclosing' => ['[box/]x[box]y[/box]', '{box []}x{box [] |y|}'],
]);

it('reads the attribute forms', function (string $attrs, array $expected) {
    $seen = null;

    (new ShortcodeProcessor)->process(
        '[gallery '.$attrs.']',
        fn () => true,
        function (Shortcode $shortcode) use (&$seen) {
            $seen = $shortcode->attrs;

            return '';
        },
        fn () => null,
    );

    expect($seen)->toBe($expected);
})->with([
    'double quotes' => ['a="b c"', ['a' => 'b c']],
    'single quotes' => ["a='b c'", ['a' => 'b c']],
    'unquoted' => ['a=b c=d', ['a' => 'b', 'c' => 'd']],
    'mixed case names' => ['Size="x"', ['size' => 'x']],
    'spaces around equals' => ['a = "b"', ['a' => 'b']],
    'empty value' => ['a=""', ['a' => '']],
    'dashes and digits in names' => ['data-x_1="y"', ['data-x_1' => 'y']],
    'positional quoted' => ['"https://x.test/a b"', [0 => 'https://x.test/a b']],
    'positional bare' => ['https://x.test/a', [0 => 'https://x.test/a']],
    'positional and named' => ['one a="b" two', [0 => 'one', 'a' => 'b', 1 => 'two']],
    'closing bracket in a quoted value' => ['a="x]y"', ['a' => 'x]y']],
    'escaped quote' => ['a="b\"c"', ['a' => 'b"c']],
    'html in a value' => ['a="<b>x</b>"', ['a' => '<b>x</b>']],
]);

it('unescapes doubled brackets of known tags and leaves their content literal', function (string $html, string $expected) {
    expect(processShortcodes($html))->toBe([$expected, []]);
})->with([
    'self closing' => ['[[gallery]]', '[gallery]'],
    'attributes' => ['[[gallery ids="1"]]', '[gallery ids="1"]'],
    'enclosing' => ['[[box]x[/box]]', '[box]x[/box]'],
]);

it('leaves doubled brackets of unknown tags as they are', function () {
    expect(processShortcodes('[[foo]]'))->toBe(['[[foo]]', []]);
});

it('keeps unknown shortcodes in place and reports each opening tag', function () {
    [$html, $unknown] = processShortcodes('a [foo x="1"] b [bar]c[/bar] d [foo]');

    expect($html)->toBe('a [foo x="1"] b [bar]c[/bar] d [foo]')
        ->and($unknown)->toBe(['foo', 'bar', 'foo']);
});

it('still processes known shortcodes inside unknown ones', function () {
    [$html, $unknown] = processShortcodes('[vc_row][vc_column][gallery ids="1"][/vc_column][/vc_row]');

    expect($html)->toBe('[vc_row][vc_column]{gallery {"ids":"1"}}[/vc_column][/vc_row]')
        ->and($unknown)->toBe(['vc_row', 'vc_column']);
});

it('passes nested content on untouched for the handler to deal with', function () {
    expect(processShortcodes('[box][inner]x[/inner][/box]')[0])->toBe('{box [] |[inner]x[/inner]|}');
});

it('ignores brackets inside html tags', function () {
    [$html, $unknown] = processShortcodes('<a href="x[foo]y" title="[gallery]">[gallery]</a>');

    expect($html)->toBe('<a href="x[foo]y" title="[gallery]">{gallery []}</a>')
        ->and($unknown)->toBe([]);
});

it('does not take a closing tag from inside an html attribute', function () {
    expect(processShortcodes('[box]<a title="[/box]">x</a>[/box]')[0])->toBe('{box [] |<a title="[/box]">x</a>|}');
});

it('ignores shortcodes in code and preformatted text', function (string $html) {
    expect(processShortcodes($html))->toBe([$html, []]);
})->with([
    'code' => ['<code>[gallery] [foo]</code>'],
    'pre' => ['<pre>[gallery]</pre>'],
    'pre with attributes' => ['<pre class="x">[foo]</pre>'],
    'script' => ['<script>var a = [foo];</script>'],
    'style' => ['<style>a[foo] { }</style>'],
    'textarea' => ['<textarea>[foo]</textarea>'],
    'html comment' => ['<!-- [foo] -->'],
]);

it('still resumes outside of code', function () {
    [$html, $unknown] = processShortcodes('<code>[foo]</code> [gallery] <pre>[gallery]</pre> [bar]');

    expect($html)->toBe('<code>[foo]</code> {gallery []} <pre>[gallery]</pre> [bar]')
        ->and($unknown)->toBe(['bar']);
});

it('unescapes doubled brackets inside code', function () {
    expect(processShortcodes('<code>[[gallery]]</code>')[0])->toBe('<code>[gallery]</code>');
});

it('treats a known tag without its closer as self closing', function () {
    expect(processShortcodes('[box]a [gallery] b')[0])->toBe('{box []}a {gallery []} b');
});

it('never throws on garbage', function (string $html) {
    expect(processShortcodes($html)[0])->toBeString();
})->with([
    '[', ']', '[[', ']]', '[[]]', '[/', '[/]', '[gallery', '[gallery "', "[gallery '", '[gallery a=', '[gallery a="', "\xFF[gallery]\xFE", '<[gallery>', '<a href="[gallery]', '<code>[gallery]', '[[gallery', '[gallery]]', '[[box]x',
]);

it('stays fast on markup that is never closed', function (string $html) {
    $start = microtime(true);

    $result = processShortcodes($html)[0];

    expect(microtime(true) - $start)->toBeLessThan(5.0)
        ->and($result)->toBeString();
})->with([
    'comments' => [str_repeat('<!-- [box] ', 200000)],
    'closers inside one attribute' => ['[box]<a title="'.str_repeat('[/box]', 200000).'">'],
    'closers inside many attributes' => ['[box]'.str_repeat('<a title="[/box]">', 100000)],
]);

it('stays fast on many unmatched tags', function () {
    $start = microtime(true);

    $result = processShortcodes(str_repeat('[box] <code> < ', 150000))[0];

    expect(microtime(true) - $start)->toBeLessThan(5.0)
        ->and($result)->toContain('<code>');
});

it('can be used again from inside a handler', function () {
    $processor = new ShortcodeProcessor;
    $unknown = [];

    $result = $processor->process(
        '[box][inner][/box] then [inner]x[/inner]',
        fn (string $tag): bool => in_array($tag, ['box', 'inner'], true),
        function (Shortcode $shortcode) use (&$processor, &$unknown): string {
            if ($shortcode->tag === 'inner') {
                return '{inner|'.$shortcode->content.'|}';
            }

            return '{box|'.$processor->process(
                (string) $shortcode->content,
                fn (string $tag): bool => $tag === 'inner',
                fn (Shortcode $inner): string => '{self-closed '.$inner->tag.'}',
                fn () => null,
            ).'|}';
        },
        fn () => null,
    );

    expect($result)->toBe('{box|{self-closed inner}|} then {inner|x|}');
});
