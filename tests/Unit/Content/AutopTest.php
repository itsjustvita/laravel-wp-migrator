<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Content\Autop;

function autop(string $html): string
{
    return (new Autop)->format($html);
}

it('formats text into paragraphs and line breaks', function (string $input, string $expected) {
    expect(autop($input))->toBe($expected);
})->with([
    'empty' => ['', ''],
    'only whitespace' => [" \n\n \t\n", ''],
    'single line' => ['Hello', '<p>Hello</p>'],
    'surrounding whitespace' => ["\n\n  Hello  \n\n", '<p>Hello</p>'],
    'two paragraphs' => ["One\n\nTwo", "<p>One</p>\n<p>Two</p>"],
    'many blank lines' => ["One\n\n\n\n\nTwo", "<p>One</p>\n<p>Two</p>"],
    'blank line with spaces' => ["One\n  \t\nTwo", "<p>One</p>\n<p>Two</p>"],
    'windows line endings' => ["One\r\n\r\nTwo\r\nThree", "<p>One</p>\n<p>Two<br />\nThree</p>"],
    'old mac line endings' => ["One\r\rTwo", "<p>One</p>\n<p>Two</p>"],
    'single line break' => ["One\nTwo", "<p>One<br />\nTwo</p>"],
    'indented next line' => ["One\n    Two", "<p>One<br />\nTwo</p>"],
    'existing br' => ["One<br>\nTwo", "<p>One<br>\nTwo</p>"],
    'existing self closed br' => ["One<br />\nTwo", "<p>One<br />\nTwo</p>"],
    'non ascii' => ["Grüße\n\nÄpfel", "<p>Grüße</p>\n<p>Äpfel</p>"],
]);

it('wraps inline elements but not block elements', function (string $input, string $expected) {
    expect(autop($input))->toBe($expected);
})->with([
    'inline text' => ['<strong>Bold</strong> text', '<p><strong>Bold</strong> text</p>'],
    'image' => ['<img src="a.jpg" alt="x">', '<p><img src="a.jpg" alt="x"></p>'],
    'linked image' => ['<a href="b"><img src="a.jpg"></a>', '<p><a href="b"><img src="a.jpg"></a></p>'],
    'heading then text' => ["<h2>T</h2>\n\nText", "<h2>T</h2>\n<p>Text</p>"],
    'heading without blank line' => ["<h2>T</h2>\nText", "<h2>T</h2>\n<p>Text</p>"],
    'text then list then text' => ["Text\n<ul>\n<li>a</li>\n</ul>\nMore", "<p>Text</p>\n<ul>\n<li>a</li>\n</ul>\n<p>More</p>"],
    'horizontal rule' => ["A\n<hr>\nB", "<p>A</p>\n<hr>\n<p>B</p>"],
    'existing paragraph' => ["<p class=\"x\">Done</p>\n\nNew", "<p class=\"x\">Done</p>\n<p>New</p>"],
    'figure' => ['<figure class="x"><img src="a"><figcaption>T</figcaption></figure>', '<figure class="x"><img src="a"><figcaption>T</figcaption></figure>'],
    'text around figure' => ["A\n<figure><img src=\"a\"></figure>\nB", "<p>A</p>\n<figure><img src=\"a\"></figure>\n<p>B</p>"],
]);

it('leaves verbatim elements alone', function (string $html) {
    expect(autop($html))->toBe($html);
})->with([
    'pre' => ["<pre>a\n\nb\n  c</pre>"],
    'pre with attributes' => ["<pre class=\"x\">\n<b>a</b>\n\nb</pre>"],
    'script' => ["<script>\nvar a = 1;\n\nvar b = '<p>';\n</script>"],
    'style' => ["<style>\na { color: red; }\n\nb { }\n</style>"],
    'uppercase closing tag' => ["<PRE>a\n\nb</pre>"],
]);

it('keeps inline verbatim elements intact inside a paragraph', function (string $html) {
    expect(autop($html))->toBe('<p>'.$html.'</p>');
})->with([
    'textarea' => ["<textarea>a\n\nb</textarea>"],
    'select' => ["<select>\n<option>a</option>\n\n<option>b</option>\n</select>"],
    'svg' => ["<svg>\n<path d=\"M0\"/>\n\n</svg>"],
    'iframe' => ["<iframe src=\"x\">\n\n</iframe>"],
    'video' => ["<video controls>\n<source src=\"a\">\n\n</video>"],
    'audio' => ["<audio>\n\n</audio>"],
    'object' => ["<object>\n<param name=\"a\">\n\n</object>"],
]);

it('handles comments', function (string $input, string $expected) {
    expect(autop($input))->toBe($expected);
})->with([
    'more marker on its own' => ["One\n\n<!--more-->\n\nTwo", "<p>One</p>\n<!--more-->\n<p>Two</p>"],
    'comment inside text' => ['One <!-- note --> two', '<p>One <!-- note --> two</p>'],
    'comment with blank lines' => ["<!-- a\n\nb -->", "<!-- a\n\nb -->"],
    'unterminated comment' => ['<!-- a', '<p><!-- a</p>'],
]);

it('does not touch line breaks inside tags', function () {
    expect(autop("<a\nhref=\"x\"\n\ntitle=\"y\">Link</a>"))->toBe("<p><a\nhref=\"x\"\n\ntitle=\"y\">Link</a></p>");
});

it('reads tags with greater-than signs in attributes', function () {
    expect(autop('<a title="a>b" href="x">y</a>'))->toBe('<p><a title="a>b" href="x">y</a></p>');
});

it('leaves stray angle brackets as text', function () {
    expect(autop('1 < 2 and 3 > 2'))->toBe('<p>1 < 2 and 3 > 2</p>');
});

it('formats the content of container elements', function (string $input, string $expected) {
    expect(autop($input))->toBe($expected);
})->with([
    'blockquote wraps text' => ['<blockquote>Quote</blockquote>', '<blockquote><p>Quote</p></blockquote>'],
    'blockquote with two paragraphs' => ["<blockquote>One\n\nTwo</blockquote>", "<blockquote>\n<p>One</p>\n<p>Two</p>\n</blockquote>"],
    'div with one line stays' => ['<div class="x">Text</div>', '<div class="x">Text</div>'],
    'div with line break' => ["<div>a\nb</div>", "<div>a<br />\nb</div>"],
    'div with two paragraphs' => ["<div>a\n\nb</div>", "<div>\n<p>a</p>\n<p>b</p>\n</div>"],
    'loose text next to a block in a div' => ['<div><h2>T</h2>Loose</div>', "<div>\n<h2>T</h2>\n<p>Loose</p>\n</div>"],
    'nested divs' => ["<div><div>a\n\nb</div></div>", "<div><div>\n<p>a</p>\n<p>b</p>\n</div></div>"],
    'paragraph with line break' => ["<p>a\nb</p>", "<p>a<br />\nb</p>"],
    'paragraph with blank line' => ["<p class=\"x\">a\n\nb</p>", "<p class=\"x\">a</p>\n<p>b</p>"],
    'heading with line break' => ["<h2>a\nb</h2>", "<h2>a<br />\nb</h2>"],
]);

it('keeps structural elements free of paragraphs', function (string $input, string $expected) {
    expect(autop($input))->toBe($expected);
})->with([
    'list item line break' => ["<ul><li>a\nb</li></ul>", "<ul><li>a<br />\nb</li></ul>"],
    'list item paragraphs' => ["<ul><li>a\n\nb</li></ul>", "<ul><li>\n<p>a</p>\n<p>b</p>\n</li></ul>"],
    'nested list' => ["<ul>\n<li>a\n<ul>\n<li>b</li>\n</ul>\n</li>\n</ul>", "<ul>\n<li>a\n<ul>\n<li>b</li>\n</ul>\n</li>\n</ul>"],
    'table' => ["<table>\n<tr>\n<td>a</td>\n<td>b\nc</td>\n</tr>\n</table>", "<table>\n<tr>\n<td>a</td>\n<td>b<br />\nc</td>\n</tr>\n</table>"],
    'definition list' => ["<dl>\n<dt>a</dt>\n<dd>b</dd>\n</dl>", "<dl>\n<dt>a</dt>\n<dd>b</dd>\n</dl>"],
]);

it('leaves well formed block markup unchanged', function (string $html) {
    expect(autop($html))->toBe($html);
})->with([
    "<div class=\"a\">\n<h2>T</h2>\n<p>x</p>\n</div>",
    "<section>\n  <article>\n    <h1>T</h1>\n  </article>\n</section>",
    "<ul>\n  <li>a</li>\n  <li>b</li>\n</ul>",
    '<table><tbody><tr><td>a</td></tr></tbody></table>',
]);

it('does not break inline elements apart at a blank line', function () {
    expect(autop("<strong>a\n\nb</strong>"))->toBe("<p><strong>a<br />\n<br />\nb</strong></p>");
});

it('continues the paragraph split after an inline element closes', function () {
    expect(autop("<em>a</em>\n\nb"))->toBe("<p><em>a</em></p>\n<p>b</p>");
});

it('copes with unbalanced markup', function (string $input, string $expected) {
    expect(autop($input))->toBe($expected);
})->with([
    'unclosed div' => ["<div>a\n\nb", "<div>\n<p>a</p>\n<p>b</p>"],
    'stray closing div' => ["a\n\n</div>\n\nb", "<p>a</p>\n</div>\n<p>b</p>"],
    'crossing blocks' => ['<div><section>a</div></section>', "<div>\n<section>\n<p>a</p>\n</div>\n</section>"],
    'unclosed list items' => ['<ul><li>a<li>b</li></ul>', '<ul><li>a<li>b</li></ul>'],
]);

it('never throws on garbage', function (string $input) {
    expect(autop($input))->toBeString();
})->with([
    '<', '<<>>', '</', '<div', '<a href=\'', '<a href="', '<!--', '<!-->', '-->', '</>', "<\n>", "\xFF\xFE\n\n\x00", '<pre>', '</pre>', '<script>', '<>',
]);

it('stays fast on many unclosed tags', function () {
    $start = microtime(true);

    $result = autop(str_repeat("<div>a\n\n", 5000));

    expect(microtime(true) - $start)->toBeLessThan(5.0)
        ->and(substr_count($result, '<div>'))->toBe(5000);
});

it('stays fast on markup that is never closed', function (string $html) {
    $start = microtime(true);

    $result = autop($html);

    expect(microtime(true) - $start)->toBeLessThan(5.0)
        ->and($result)->toBeString();
})->with([
    'comments' => [str_repeat('<!-- a ', 200000)],
    'preformatted' => [str_repeat('<pre>', 100000)],
    'scripts' => [str_repeat('<script>x', 100000)],
]);

it('copes with very deep nesting', function () {
    $depth = 2000;

    $result = autop(str_repeat('<div>', $depth)."a\n\nb".str_repeat('</div>', $depth));

    expect(substr_count($result, '<div>'))->toBe($depth)
        ->and($result)->toContain('a')->toContain('b');
});

it('produces the same result when run twice', function (string $input) {
    expect(autop(autop($input)))->toBe(autop($input));
})->with([
    "One\n\nTwo\nThree",
    "<h2>T</h2>\nText\n\n<ul>\n<li>a\nb</li>\n</ul>",
    "<blockquote>One\n\nTwo</blockquote>",
    "<div>a\n\nb</div>",
    "<strong>a\n\nb</strong>",
    "One\n\n<!--more-->\n\nTwo",
    "<table>\n<tr>\n<td>b\nc</td>\n</tr>\n</table>",
    "<pre>a\n\nb</pre>\n\nText",
]);
