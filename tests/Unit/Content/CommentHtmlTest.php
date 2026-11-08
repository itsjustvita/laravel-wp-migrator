<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Content\CommentHtml;

it('keeps the markup WP allows in comments', function (string $html) {
    expect(CommentHtml::sanitize($html))->toBe($html);
})->with([
    'plain text' => ['Great read!'],
    'emphasis' => ['<strong>Bold</strong>, <em>italic</em>, <b>b</b>, <i>i</i>, <code>code</code>'],
    'link' => ['<a href="https://example.net/" title="Example">link</a>'],
    'mail link' => ['<a href="mailto:a@example.net">mail</a>'],
    'quote' => ['<blockquote cite="https://example.net">Quote</blockquote>'],
    'abbreviation' => ['<abbr title="HyperText">HTML</abbr>'],
    'deleted text' => ['<del datetime="2026-01-01">old</del> <s>gone</s> <strike>struck</strike> <q>q</q> <cite>c</cite>'],
    'line breaks' => ["Line one\nLine two"],
    'entities' => ['Tom &amp; Jerry &lt;3'],
    'umlauts' => ['Grüße 👋'],
]);

it('removes what can run script', function (string $html, string $expected) {
    expect(CommentHtml::sanitize($html))->toBe($expected);
})->with([
    'script' => ['Hi<script>alert(1)</script>!', 'Hi!'],
    'style' => ['<style>body{display:none}</style>Text', 'Text'],
    'event handler' => ['<strong onclick="alert(1)">x</strong>', '<strong>x</strong>'],
    'javascript link' => ['<a href="javascript:alert(1)">click</a>', 'click'],
    'obfuscated javascript link' => ['<a href=" java&#x09;script:alert(1)">click</a>', 'click'],
    'data link' => ['<a href="data:text/html,x">x</a>', 'x'],
    'unsafe quote source' => ['<blockquote cite="javascript:x">q</blockquote>', '<blockquote>q</blockquote>'],
    'image' => ['<img src=x onerror=alert(1)>Text', 'Text'],
    'iframe' => ['<iframe src="https://evil.example"></iframe>Text', 'Text'],
    'svg' => ['<svg onload="alert(1)"><circle/></svg>Text', 'Text'],
    'unknown tags keep their text' => ['<div class="x"><span style="color:red">Text</span></div>', 'Text'],
    'html comments' => ['A<!-- secret -->B', 'AB'],
    'style attribute' => ['<em style="background:url(javascript:x)">x</em>', '<em>x</em>'],
    'unclosed markup' => ['<strong>open', '<strong>open</strong>'],
    'stray brackets' => ['1 < 2 > 0', '1 &lt; 2 &gt; 0'],
    'link target' => ['<a href="https://x.example" target="_blank" rel="opener">x</a>', '<a href="https://x.example">x</a>'],
]);

it('returns nothing for empty content', function () {
    expect(CommentHtml::sanitize(''))->toBe('');
});

it('never throws on broken input', function (string $html) {
    expect(CommentHtml::sanitize($html))->toBeString();
})->with(["\xFF\xFE<b>", '<', '<<<>>>', '</a></b>', '<a href="', str_repeat('<b>', 5000)]);
