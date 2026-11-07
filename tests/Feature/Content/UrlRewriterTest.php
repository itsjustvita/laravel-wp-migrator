<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use ItsJustVita\WpMigrator\Content\Urls\PostReference;
use ItsJustVita\WpMigrator\Content\Urls\UrlResolver;
use ItsJustVita\WpMigrator\Content\Urls\UrlRewriter;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Models\Media;

final class OnlyPagesUrlResolver implements UrlResolver
{
    public function forPost(PostReference $post): ?string
    {
        return $post->type === 'page' ? '/seiten/'.$post->id : null;
    }
}

function rewrite(string $text, ?ImportContext $context = null): string
{
    return app(UrlRewriter::class)->rewrite($text, 100, $context ?? importContext('posts'));
}

beforeEach(function () {
    Storage::fake('public');
    config()->set('wp-migrator.media.disk', 'public');
    config()->set('wp-migrator.media.directory', 'wp-uploads');

    $this->site = $this->source();
    $this->site->option('home', 'https://old.example.com');
    $this->site->option('siteurl', 'https://old.example.com');
    $this->site->option('permalink_structure', '/%year%/%postname%/');

    Media::create([
        'disk' => 'public',
        'path' => 'wp-uploads/2026/01/hero.jpg',
        'variants' => [
            'medium' => ['path' => 'wp-uploads/2026/01/hero-300x200.jpg', 'width' => 300, 'height' => 200, 'mime_type' => 'image/jpeg'],
        ],
    ]);

    $this->hero = Storage::disk('public')->url('wp-uploads/2026/01/hero.jpg');
    $this->heroMedium = Storage::disk('public')->url('wp-uploads/2026/01/hero-300x200.jpg');
});

it('points upload urls at the new media files', function () {
    expect(rewrite('<img src="https://old.example.com/wp-content/uploads/2026/01/hero.jpg">'))
        ->toBe('<img src="/storage/wp-uploads/2026/01/hero.jpg">');
});

it('keeps urls absolute when the disk lives somewhere else', function () {
    config()->set('filesystems.disks.public.url', 'https://cdn.example.net/files');
    Storage::forgetDisk('public');

    expect(rewrite('<img src="https://old.example.com/wp-content/uploads/2026/01/hero.jpg">'))
        ->toBe('<img src="https://cdn.example.net/files/wp-uploads/2026/01/hero.jpg">');
});

it('makes urls on the application host relative so content survives a domain change', function () {
    config()->set('app.url', 'https://staging.example.net');
    config()->set('filesystems.disks.public.url', 'https://staging.example.net/storage');
    Storage::forgetDisk('public');

    expect(rewrite('<img src="https://old.example.com/wp-content/uploads/2026/01/hero.jpg">'))
        ->toBe('<img src="/storage/wp-uploads/2026/01/hero.jpg">');
});

it('points resized upload urls at the matching variant', function () {
    expect(rewrite('<img src="https://old.example.com/wp-content/uploads/2026/01/hero-300x200.jpg">'))
        ->toBe('<img src="/storage/wp-uploads/2026/01/hero-300x200.jpg">');
});

it('points an unknown size at the original', function () {
    expect(rewrite('<img src="https://old.example.com/wp-content/uploads/2026/01/hero-1024x768.jpg">'))
        ->toBe('<img src="/storage/wp-uploads/2026/01/hero.jpg">');
});

it('finds the scaled file behind a size of its original', function () {
    Media::create(['disk' => 'public', 'path' => 'wp-uploads/2026/01/big-scaled.jpg']);

    expect(rewrite('<img src="https://old.example.com/wp-content/uploads/2026/01/big-300x200.jpg">'))
        ->toBe('<img src="/storage/wp-uploads/2026/01/big-scaled.jpg">')
        ->and(rewrite('<img src="https://old.example.com/wp-content/uploads/2026/01/big.jpg">'))
        ->toBe('<img src="/storage/wp-uploads/2026/01/big-scaled.jpg">');
});

it('prefers a file that really has dimensions in its name', function () {
    Media::create(['disk' => 'public', 'path' => 'wp-uploads/2026/01/poster-800x600.jpg']);

    expect(rewrite('<img src="https://old.example.com/wp-content/uploads/2026/01/poster-800x600.jpg">'))
        ->toBe('<img src="/storage/wp-uploads/2026/01/poster-800x600.jpg">');
});

it('recognises the old site in all its spellings', function (string $base) {
    expect(rewrite('<img src="'.$base.'/wp-content/uploads/2026/01/hero.jpg">'))
        ->toBe('<img src="/storage/wp-uploads/2026/01/hero.jpg">');
})->with([
    'http' => 'http://old.example.com',
    'https' => 'https://old.example.com',
    'www' => 'https://www.old.example.com',
    'protocol relative' => '//old.example.com',
    'uppercase' => 'HTTPS://OLD.EXAMPLE.COM',
    'port' => 'https://old.example.com:8080',
]);

it('rewrites relative upload urls in attributes, srcset and styles', function (string $input, string $expected) {
    expect(rewrite($input))->toBe($expected);
})->with([
    'src' => ['<img src="/wp-content/uploads/2026/01/hero.jpg">', '<img src="/storage/wp-uploads/2026/01/hero.jpg">'],
    'single quotes' => ["<img src='/wp-content/uploads/2026/01/hero.jpg'>", "<img src='/storage/wp-uploads/2026/01/hero.jpg'>"],
    'srcset' => [
        '<img srcset="/wp-content/uploads/2026/01/hero-300x200.jpg 300w, /wp-content/uploads/2026/01/hero.jpg 1200w">',
        '<img srcset="/storage/wp-uploads/2026/01/hero-300x200.jpg 300w, /storage/wp-uploads/2026/01/hero.jpg 1200w">',
    ],
    'absolute srcset' => [
        '<img srcset="https://old.example.com/wp-content/uploads/2026/01/hero-300x200.jpg 300w, https://old.example.com/wp-content/uploads/2026/01/hero.jpg 1200w">',
        '<img srcset="/storage/wp-uploads/2026/01/hero-300x200.jpg 300w, /storage/wp-uploads/2026/01/hero.jpg 1200w">',
    ],
    'style' => ['<div style="background:url(/wp-content/uploads/2026/01/hero.jpg)">', '<div style="background:url(/storage/wp-uploads/2026/01/hero.jpg)">'],
    'absolute style' => ['<div style="background:url(https://old.example.com/wp-content/uploads/2026/01/hero.jpg)">', '<div style="background:url(/storage/wp-uploads/2026/01/hero.jpg)">'],
    'quoted style' => ['<div style="background:url(\'/wp-content/uploads/2026/01/hero.jpg\')">', '<div style="background:url(\'/storage/wp-uploads/2026/01/hero.jpg\')">'],
]);

it('decodes percent-encoded file names', function () {
    Media::create(['disk' => 'public', 'path' => 'wp-uploads/2026/01/grüße bild.jpg']);

    expect(rewrite('<img src="/wp-content/uploads/2026/01/gr%C3%BC%C3%9Fe%20bild.jpg">'))
        ->toContain('/storage/wp-uploads/2026/01/');
});

it('points internal links at the new routes', function (string $input, string $expected) {
    $this->site->post(['post_name' => 'hello', 'post_date' => '2026-03-04 10:00:00']);

    expect(rewrite($input))->toBe($expected);
})->with([
    'absolute' => ['<a href="https://old.example.com/2026/hello/">x</a>', '<a href="/blog/hello">x</a>'],
    'relative' => ['<a href="/2026/hello/">x</a>', '<a href="/blog/hello">x</a>'],
    'no trailing slash' => ['<a href="/2026/hello">x</a>', '<a href="/blog/hello">x</a>'],
    'fragment' => ['<a href="/2026/hello/#part">x</a>', '<a href="/blog/hello#part">x</a>'],
    'query' => ['<a href="/2026/hello/?a=1&amp;b=2">x</a>', '<a href="/blog/hello?a=1&amp;b=2">x</a>'],
    'query and fragment' => ['<a href="https://old.example.com/2026/hello/?a=1#part">x</a>', '<a href="/blog/hello?a=1#part">x</a>'],
    'single quotes' => ["<a href='/2026/hello/'>x</a>", "<a href='/blog/hello'>x</a>"],
    'plain text' => ['See https://old.example.com/2026/hello/.', 'See /blog/hello.'],
    'text with a comma' => ['See https://old.example.com/2026/hello/, then', 'See /blog/hello, then'],
]);

it('stops a url at the brackets of a shortcode', function () {
    $this->site->post(['post_name' => 'hello', 'post_date' => '2026-03-04 10:00:00']);

    expect(rewrite('[embed]https://old.example.com/2026/hello/[/embed]'))->toBe('[embed]/blog/hello[/embed]')
        ->and(rewrite('[video src=/wp-content/uploads/2026/01/hero.jpg]'))->toBe('[video src=/storage/wp-uploads/2026/01/hero.jpg]');
});

it('resolves query urls by id', function () {
    $post = $this->site->post(['post_name' => 'hello']);
    $page = $this->site->page(['post_name' => 'about']);

    expect(rewrite('<a href="https://old.example.com/?p='.$post.'">x</a>'))->toBe('<a href="/blog/hello">x</a>')
        ->and(rewrite('<a href="/?p='.$post.'">x</a>'))->toBe('<a href="/blog/hello">x</a>')
        ->and(rewrite('<a href="/?page_id='.$page.'">x</a>'))->toBe('<a href="/about">x</a>')
        ->and(rewrite('<a href="/?p='.$post.'#part">x</a>'))->toBe('<a href="/blog/hello#part">x</a>')
        ->and(rewrite('<a href="/?p='.$post.'&amp;utm=1">x</a>'))->toBe('<a href="/blog/hello?utm=1">x</a>')
        ->and(rewrite('<a href="/?utm=1&p='.$post.'">x</a>'))->toBe('<a href="/blog/hello?utm=1">x</a>');
});

it('makes links to the old front page relative', function () {
    expect(rewrite('<a href="https://old.example.com">x</a>'))->toBe('<a href="/">x</a>')
        ->and(rewrite('<a href="https://old.example.com/">x</a>'))->toBe('<a href="/">x</a>')
        ->and(rewrite('<a href="https://old.example.com/#top">x</a>'))->toBe('<a href="/#top">x</a>')
        ->and(rewrite('<a href="/">x</a>'))->toBe('<a href="/">x</a>');
});

it('leaves other urls alone', function (string $html) {
    $context = importContext('posts');

    expect(rewrite($html, $context))->toBe($html)
        ->and($context->report->unresolvedReferences())->toBe([]);
})->with([
    'other host' => ['<a href="https://other.example.com/2026/hello/">x</a>'],
    'look-alike host' => ['<a href="https://old.example.com.evil.net/wp-content/uploads/a.jpg">x</a>'],
    'host with a prefix' => ['<a href="https://notold.example.com/a">x</a>'],
    'old url in a query parameter' => ['<a href="https://share.example.net/?url=https://old.example.com/2026/hello/">x</a>'],
    'anchor' => ['<a href="#part">x</a>'],
    'mail' => ['<a href="mailto:a@old.example.com">x</a>'],
    'phone' => ['<a href="tel:+49123">x</a>'],
    'relative without slash' => ['<a href="page.html">x</a>'],
    'no urls' => ['<p>Just text with a / slash // and more</p>'],
    'empty' => [''],
]);

it('reports internal links it cannot resolve and leaves them in place', function () {
    $context = importContext('posts');
    $html = '<a href="https://old.example.com/shop/item/">x</a><a href="/other/">y</a>';

    expect(rewrite($html, $context))->toBe($html)
        ->and($context->report->unresolvedReferences())->toBe([
            ['importer' => 'posts', 'source_id' => 100, 'kind' => 'link', 'reference' => 'https://old.example.com/shop/item/'],
            ['importer' => 'posts', 'source_id' => 100, 'kind' => 'link', 'reference' => '/other/'],
        ])
        ->and($context->rowIncomplete())->toBeFalse();
});

it('reports missing media and maps the row again next time', function () {
    $context = importContext('posts');
    $html = '<img src="https://old.example.com/wp-content/uploads/2020/01/gone.jpg">';

    expect(rewrite($html, $context))->toBe($html)
        ->and($context->report->unresolvedReferences()[0]['kind'])->toBe('media url')
        ->and($context->rowIncomplete())->toBeTrue();
});

it('handles an install in a subdirectory', function () {
    $this->site->option('home', 'https://old.example.com/site');
    $this->site->option('siteurl', 'https://old.example.com/site');
    $this->site->post(['post_name' => 'hello', 'post_date' => '2026-03-04 10:00:00']);

    expect(rewrite('<a href="https://old.example.com/site/2026/hello/">x</a>'))->toBe('<a href="/blog/hello">x</a>')
        ->and(rewrite('<a href="/site/2026/hello/">x</a>'))->toBe('<a href="/blog/hello">x</a>')
        ->and(rewrite('<a href="https://old.example.com/site/">x</a>'))->toBe('<a href="/">x</a>')
        ->and(rewrite('<img src="/site/wp-content/uploads/2026/01/hero.jpg">'))->toBe('<img src="/storage/wp-uploads/2026/01/hero.jpg">');
});

it('leaves links outside of a subdirectory install alone', function () {
    $this->site->option('home', 'https://old.example.com/site');
    $context = importContext('posts');
    $html = '<a href="https://old.example.com/elsewhere/">x</a><a href="/elsewhere/">y</a>';

    expect(rewrite($html, $context))->toBe($html)
        ->and($context->report->unresolvedReferences())->toBe([]);
});

it('recognises an uploads host and extra hosts from the configuration', function () {
    config()->set('wp-migrator.source.uploads_url', 'https://cdn.old.example.com/files');
    config()->set('wp-migrator.urls.old_hosts', ['legacy.example.org']);
    $this->site->post(['post_name' => 'hello', 'post_date' => '2026-03-04 10:00:00']);

    expect(rewrite('<img src="https://cdn.old.example.com/files/2026/01/hero.jpg">'))->toBe('<img src="/storage/wp-uploads/2026/01/hero.jpg">')
        ->and(rewrite('<a href="https://legacy.example.org/2026/hello/">x</a>'))->toBe('<a href="/blog/hello">x</a>');
});

it('uses a custom url resolver and reports what it declines', function () {
    config()->set('wp-migrator.urls.resolver', OnlyPagesUrlResolver::class);
    $post = $this->site->post(['post_name' => 'hello']);
    $page = $this->site->page(['post_name' => 'about']);
    $context = importContext('posts');

    expect(rewrite('<a href="/?page_id='.$page.'">x</a>', $context))->toBe('<a href="/seiten/'.$page.'">x</a>')
        ->and(rewrite('<a href="/?p='.$post.'">x</a>', $context))->toBe('<a href="/?p='.$post.'">x</a>')
        ->and($context->report->unresolvedReferences()[0]['kind'])->toBe('link');
});

it('applies the configured url patterns', function () {
    config()->set('wp-migrator.urls.patterns', [
        'post' => '/news/{year}/{month}/{day}/{slug}',
        'page' => '/p/{path}',
        '*' => '/x/{type}/{id}',
    ]);
    $post = $this->site->post(['post_name' => 'hello', 'post_date' => '2026-03-04 10:00:00']);
    $parent = $this->site->page(['post_name' => 'company']);
    $child = $this->site->page(['post_name' => 'grüße', 'post_parent' => $parent]);
    $event = $this->site->post(['post_type' => 'event', 'post_name' => 'gig']);

    expect(rewrite('<a href="/?p='.$post.'">x</a>'))->toBe('<a href="/news/2026/03/04/hello">x</a>')
        ->and(rewrite('<a href="/?page_id='.$child.'">x</a>'))->toBe('<a href="/p/company/gr%C3%BC%C3%9Fe">x</a>')
        ->and(rewrite('<a href="/?p='.$event.'">x</a>'))->toBe('<a href="/x/event/'.$event.'">x</a>');
});

it('does not rewrite a replacement a second time', function () {
    Media::query()->where('path', 'wp-uploads/2026/01/hero.jpg')->update(['path' => 'wp-content/uploads/2026/01/hero.jpg']);
    config()->set('wp-migrator.media.directory', 'wp-content/uploads');

    expect(rewrite('<img src="/wp-content/uploads/2026/01/hero.jpg">'))
        ->toBe('<img src="/storage/wp-content/uploads/2026/01/hero.jpg">');
});

it('looks up each media file only once per run', function () {
    $rewriter = app(UrlRewriter::class);
    $context = importContext('posts');
    $queries = 0;

    $rewriter->rewrite('<img src="/wp-content/uploads/2026/01/hero.jpg">', 100, $context);

    Media::getConnectionResolver()->connection()->listen(function () use (&$queries): void {
        $queries++;
    });

    $rewriter->rewrite('<img src="/wp-content/uploads/2026/01/hero.jpg">', 101, $context);

    expect($queries)->toBe(0);
});
