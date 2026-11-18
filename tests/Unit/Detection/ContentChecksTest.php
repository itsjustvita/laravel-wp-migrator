<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Detection\Suspicious\ContentChecks;
use ItsJustVita\WpMigrator\Support\ExecutableFile;

function check(string $html): array
{
    return (new ContentChecks('old.example.com'))->find($html);
}

it('finds nothing in ordinary content', function () {
    expect(check('<p>Hello <a href="https://old.example.com/about" onboarding="x">about</a>. One=two, on = off.</p><img src="data:image/png;base64,AAAA" alt="">'))->toBe([]);
});

it('finds scripts with their source', function () {
    expect(check('<p>x</p><script src="https://evil.example/x.js"></script><SCRIPT>alert(1)</SCRIPT><script type="text/javascript" src="//cdn.example.net/a.js"></script>'))
        ->toBe(['scripts' => ['evil.example', 'inline', 'cdn.example.net']]);
});

it('finds event handlers in tags', function () {
    expect(check('<img src=x onerror="alert(1)"><div ONMOUSEOVER = \'x\'>a</div><p>onload=never outside a tag</p>'))
        ->toBe(['event-handlers' => ['onerror', 'onmouseover']]);
});

it('finds links that run script', function () {
    expect(check('<a href="javascript:alert(1)">a</a><a href = \' JaVaScRiPt:void(0)\'>b</a><iframe src="data:text/html;base64,xx"></iframe><form action="vbscript:x"></form>'))
        ->toMatchArray(['script-links' => ['javascript:', 'data:text/html', 'vbscript:']]);
});

it('finds frames from unknown sites, but not from known players or the site itself', function () {
    expect(check('<iframe src="https://www.youtube-nocookie.com/embed/x"></iframe><iframe src="https://player.vimeo.com/video/1"></iframe><iframe src="https://www.google.com/maps/embed?pb=1"></iframe><iframe src="/local"></iframe><iframe src="https://old.example.com/form"></iframe><iframe src="https://casino.example/win"></iframe><iframe width="1" src="//tracker.example/p"></iframe>'))
        ->toBe(['frames' => ['casino.example', 'tracker.example']]);
});

it('finds links inside hidden elements', function () {
    $spam = '<div style="display:none"><a href="https://pills.example/buy">cheap</a></div>'
        .'<span style="position:absolute; left:-9999px">see <a href="http://loans.example">loans</a></span>'
        .'<p style="font-size: 0px"><a href="https://pills.example/2">x</a></p>'
        .'<div style="display: none">no link here</div>'
        .'<div style="display:none"><a href="/internal">fine</a></div>'
        .'<div style="font-size: 0.9em"><a href="https://ok.example">fine</a></div>';

    expect(check($spam))->toBe(['hidden-links' => ['pills.example', 'loans.example']]);
});

it('finds obfuscated code, but not in code examples', function () {
    expect(check('<p>x</p><script>eval(atob("YWxlcnQ="))</script>'))
        ->toBe(['scripts' => ['inline'], 'obfuscation' => ['eval', 'atob']])
        ->and(check('<p>Never call <code>eval()</code> or base64_decode() in a post like this?</p>'))
        ->toBe(['obfuscation' => ['base64_decode']])
        ->and(check('<pre>eval(x); String.fromCharCode(1)</pre><!-- wp:code --><pre class="wp-block-code"><code>atob(y)</code></pre><!-- /wp:code -->'))
        ->toBe([])
        ->and(check('<p>document.write(unescape("%3C")) and String.fromCharCode(60)</p>'))
        ->toBe(['obfuscation' => ['document.write', 'unescape', 'fromCharCode']]);
});

it('keeps at most five details per finding', function () {
    $html = implode('', array_map(fn (int $i): string => '<script src="https://h'.$i.'.example/x.js"></script>', range(1, 8)));

    expect(check($html)['scripts'])->toBe(['h1.example', 'h2.example', 'h3.example', 'h4.example', 'h5.example']);
});

it('tells program files apart from media', function (string $name, bool $code, bool $codeOrConfig) {
    expect(ExecutableFile::isCode($name))->toBe($code)
        ->and(ExecutableFile::isCodeOrConfig($name))->toBe($codeOrConfig);
})->with([
    ['photo.jpg', false, false],
    ['shell.php', true, true],
    ['SHELL.PHP5', true, true],
    ['shell.php.jpg', true, true],
    ['2026/01/x.phtml', true, true],
    ['.htaccess', false, true],
    ['.user.ini', false, true],
    ['README', false, false],
    ['archive.tar.gz', false, false],
]);

it('avoids look-alikes of handlers, hiding and obfuscation', function () {
    expect(check('<div data-actiononload="x" data-xonload="1" class="buttononclick=">a</div>'))->toBe([])
        ->and(check('<script></script><div data-xonload="1" class="buttononclick=">a</div>'))->toBe(['scripts' => ['inline']])
        ->and(check('<span style="position:relative;left:-5px"><a href="https://ok.example">x</a></span>'))->toBe([])
        ->and(check('<p>Call retrieval() or interval(5) here.</p>'))->toBe([])
        ->and(check('<div style="visibility: hidden"><a href="https://spam.example">x</a></div>'))->toBe(['hidden-links' => ['spam.example']]);
});
