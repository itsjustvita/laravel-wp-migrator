<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Support\SafeUrl;

it('accepts links that cannot run script', function (string $url) {
    expect(SafeUrl::isSafe($url))->toBeTrue();
})->with([
    'https' => ['https://example.net/a?b=1#c'],
    'http' => ['HTTP://example.net'],
    'protocol relative' => ['//example.net/a'],
    'root relative' => ['/blog/hello'],
    'relative' => ['page.html'],
    'parent' => ['../page'],
    'fragment' => ['#top'],
    'query' => ['?page=2'],
    'mail' => ['mailto:a@example.net'],
    'phone' => ['tel:+49123'],
    'colon later in the path' => ['/a:b'],
]);

it('rejects links that can run script or load other content', function (string $url) {
    expect(SafeUrl::isSafe($url))->toBeFalse();
})->with([
    'javascript' => ['javascript:alert(1)'],
    'uppercase' => ['JavaScript:alert(1)'],
    'leading space' => ['  javascript:alert(1)'],
    'tab inside' => ["java\tscript:alert(1)"],
    'newline inside' => ["java\nscript:alert(1)"],
    'null byte' => ["java\0script:alert(1)"],
    'data' => ['data:text/html,<script>alert(1)</script>'],
    'vbscript' => ['vbscript:msgbox(1)'],
    'file' => ['file:///etc/passwd'],
]);

it('limits the schemes on request', function () {
    expect(SafeUrl::isSafe('mailto:a@example.net', ['http', 'https']))->toBeFalse()
        ->and(SafeUrl::isSafe('https://example.net', ['http', 'https']))->toBeTrue()
        ->and(SafeUrl::isSafe('/relative', ['http', 'https']))->toBeFalse();
});
