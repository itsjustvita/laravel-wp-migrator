<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use ItsJustVita\WpMigrator\Content\Embeds;
use ItsJustVita\WpMigrator\Content\Shortcodes\Handlers\AudioHandler;
use ItsJustVita\WpMigrator\Content\Shortcodes\Handlers\CaptionHandler;
use ItsJustVita\WpMigrator\Content\Shortcodes\Handlers\EmbedHandler;
use ItsJustVita\WpMigrator\Content\Shortcodes\Handlers\GalleryHandler;
use ItsJustVita\WpMigrator\Content\Shortcodes\Handlers\VideoHandler;
use ItsJustVita\WpMigrator\Content\Shortcodes\Shortcode;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeContext;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeHandler;
use ItsJustVita\WpMigrator\Facades\WpMigrator;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Models\Media;

final class ShoutHandler implements ShortcodeHandler
{
    public function render(Shortcode $shortcode, ShortcodeContext $context): string
    {
        return strtoupper((string) $shortcode->content);
    }
}

function shortcodeContext(?ImportContext $import = null, int $postId = 50): ShortcodeContext
{
    return new ShortcodeContext($postId, $import ?? importContext('posts'), fn (string $content): string => $content);
}

function importedMedia(ImportContext $context, int $sourceId, string $file, array $attributes = []): Media
{
    $media = Media::create(array_merge(['disk' => 'public', 'path' => 'wp-uploads/'.$file], $attributes));
    $context->map->record(SourceType::Post, $sourceId, $media, 'x');

    return $media;
}

beforeEach(function () {
    Storage::fake('public');
});

it('renders a caption around an image', function () {
    $html = app(CaptionHandler::class)->render(new Shortcode('caption', [
        'id' => 'attachment_5', 'align' => 'alignleft', 'width' => '300',
    ], '<img src="a.jpg" alt="x" /> My <em>text</em>'), shortcodeContext());

    expect($html)->toBe('<figure id="attachment_5" class="wp-caption alignleft" style="width:300px"><img src="a.jpg" alt="x" /><figcaption class="wp-caption-text">My <em>text</em></figcaption></figure>');
});

it('keeps the link around a captioned image', function () {
    $html = app(CaptionHandler::class)->render(new Shortcode('caption', [], '<a href="b"><img src="a.jpg" /></a> Text'), shortcodeContext());

    expect($html)->toBe('<figure class="wp-caption"><a href="b"><img src="a.jpg" /></a><figcaption class="wp-caption-text">Text</figcaption></figure>');
});

it('reads the caption attribute of old posts', function () {
    $html = app(CaptionHandler::class)->render(new Shortcode('caption', ['caption' => 'Old <b>text</b>'], '<img src="a.jpg" />'), shortcodeContext());

    expect($html)->toBe('<figure class="wp-caption"><img src="a.jpg" /><figcaption class="wp-caption-text">Old <b>text</b></figcaption></figure>');
});

it('leaves out an empty caption', function () {
    $html = app(CaptionHandler::class)->render(new Shortcode('caption', [], '<img src="a.jpg" />'), shortcodeContext());

    expect($html)->toBe('<figure class="wp-caption"><img src="a.jpg" /></figure>');
});

it('processes shortcodes inside a caption', function () {
    $context = new ShortcodeContext(50, importContext('posts'), fn (string $content): string => str_replace('[x]', 'Y', $content));

    $html = app(CaptionHandler::class)->render(new Shortcode('caption', [], '<img src="a.jpg" /> Text [x]'), $context);

    expect($html)->toContain('Text Y');
});

it('escapes caption attributes', function () {
    $html = app(CaptionHandler::class)->render(new Shortcode('caption', [
        'id' => '"><script>x</script>', 'align' => 'a" onclick="x', 'width' => '1;color:red',
    ], '<img src="a.jpg" />'), shortcodeContext());

    expect($html)->not->toContain('<script>')
        ->not->toContain('" onclick')
        ->not->toContain('color:red');
});

it('renders a gallery from ids in the given order', function () {
    $import = importContext('posts');
    importedMedia($import, 1, '2026/a.jpg', ['alt' => 'Alt A', 'caption' => 'Cap A']);
    importedMedia($import, 2, '2026/b.jpg');

    $html = app(GalleryHandler::class)->render(new Shortcode('gallery', ['ids' => '2, 1', 'columns' => '2']), shortcodeContext($import));

    $b = '/storage/wp-uploads/2026/b.jpg';
    $a = '/storage/wp-uploads/2026/a.jpg';

    expect($html)->toBe(
        '<figure class="wp-block-gallery wp-gallery columns-2">'
        .'<figure class="wp-block-image"><img src="'.$b.'" alt="" loading="lazy" /></figure>'
        .'<figure class="wp-block-image"><img src="'.$a.'" alt="Alt A" loading="lazy" /><figcaption>Cap A</figcaption></figure>'
        .'</figure>'
    );
});

it('accepts include as a list of ids and defaults to three columns', function () {
    $import = importContext('posts');
    importedMedia($import, 1, '2026/a.jpg');

    $html = app(GalleryHandler::class)->render(new Shortcode('gallery', ['include' => '1']), shortcodeContext($import));

    expect($html)->toContain('columns-3')->toContain('2026/a.jpg');
});

it('uses the attachments of the post when a gallery lists no ids', function () {
    $site = $this->source();
    $post = $site->post();
    $second = $site->attachment(['post_parent' => $post, 'menu_order' => 2]);
    $first = $site->attachment(['post_parent' => $post, 'menu_order' => 1]);
    $site->attachment(['post_parent' => $post, 'post_mime_type' => 'application/pdf']);
    $site->attachment(['post_parent' => $post + 100]);
    $import = importContext('posts');
    importedMedia($import, $first, '2026/first.jpg');
    importedMedia($import, $second, '2026/second.jpg');

    $html = app(GalleryHandler::class)->render(new Shortcode('gallery'), shortcodeContext($import, $post));

    expect(strpos($html, 'first.jpg'))->toBeLessThan(strpos($html, 'second.jpg'))
        ->and(substr_count($html, '<img'))->toBe(2);
});

it('picks the requested gallery size when it exists', function () {
    $import = importContext('posts');
    importedMedia($import, 1, '2026/a.jpg', ['variants' => [
        'medium' => ['path' => 'wp-uploads/2026/a-300x200.jpg', 'width' => 300, 'height' => 200, 'mime_type' => 'image/jpeg'],
    ]]);

    $handler = app(GalleryHandler::class);

    expect($handler->render(new Shortcode('gallery', ['ids' => '1', 'size' => 'medium']), shortcodeContext($import)))->toContain('a-300x200.jpg')
        ->and($handler->render(new Shortcode('gallery', ['ids' => '1', 'size' => 'thumbnail']), shortcodeContext($import)))->not->toContain('a-300x200.jpg')->toContain('2026/a.jpg')
        ->and($handler->render(new Shortcode('gallery', ['ids' => '1']), shortcodeContext($import)))->toContain('2026/a.jpg');
});

it('links gallery images to their file on request', function () {
    $import = importContext('posts');
    importedMedia($import, 1, '2026/a.jpg');

    $html = app(GalleryHandler::class)->render(new Shortcode('gallery', ['ids' => '1', 'link' => 'file']), shortcodeContext($import));

    expect($html)->toMatch('#<a href="[^"]*2026/a\.jpg"><img#');
});

it('reports gallery images it cannot resolve', function () {
    $import = importContext('posts');
    importedMedia($import, 1, '2026/a.jpg');

    $html = app(GalleryHandler::class)->render(new Shortcode('gallery', ['ids' => '1,99']), shortcodeContext($import));

    expect(substr_count($html, '<img'))->toBe(1)
        ->and($import->report->unresolvedReferences())->toBe([['importer' => 'posts', 'source_id' => 50, 'kind' => 'gallery image', 'reference' => '99']])
        ->and($import->rowIncomplete())->toBeTrue();
});

it('renders nothing for an empty gallery', function () {
    $this->source();

    expect(app(GalleryHandler::class)->render(new Shortcode('gallery', ['ids' => 'x']), shortcodeContext()))->toBe('');
});

it('escapes gallery values', function () {
    $import = importContext('posts');
    importedMedia($import, 1, '2026/a.jpg', ['alt' => '"><script>']);

    $html = app(GalleryHandler::class)->render(new Shortcode('gallery', ['ids' => '1', 'columns' => '"><b>']), shortcodeContext($import));

    expect($html)->not->toContain('<script>')->not->toContain('<b>');
});

it('builds privacy friendly embeds for known video hosts', function (string $url, string $expected) {
    expect(Embeds::render($url))->toContain('<iframe src="'.$expected.'"');
})->with([
    'youtube watch' => ['https://www.youtube.com/watch?v=abc_DEF-123&t=5', 'https://www.youtube-nocookie.com/embed/abc_DEF-123'],
    'youtube short link' => ['https://youtu.be/abc_DEF-123', 'https://www.youtube-nocookie.com/embed/abc_DEF-123'],
    'youtube shorts' => ['https://youtube.com/shorts/abc_DEF-123', 'https://www.youtube-nocookie.com/embed/abc_DEF-123'],
    'youtube embed' => ['https://www.youtube.com/embed/abc_DEF-123', 'https://www.youtube-nocookie.com/embed/abc_DEF-123'],
    'vimeo' => ['https://vimeo.com/123456', 'https://player.vimeo.com/video/123456'],
]);

it('links everything else', function () {
    expect(Embeds::render('https://example.net/a?b=1&c=2'))
        ->toBe('<figure class="wp-block-embed"><a href="https://example.net/a?b=1&amp;c=2">https://example.net/a?b=1&amp;c=2</a></figure>');
});

it('adds a caption to an embed', function () {
    expect(Embeds::render('https://example.net/a', 'My <em>cap</em>'))->toContain('<figcaption>My <em>cap</em></figcaption>');
});

it('links root-relative urls', function () {
    expect(Embeds::render('/blog/hello'))->toBe('<figure class="wp-block-embed"><a href="/blog/hello">/blog/hello</a></figure>')
        ->and(Embeds::render('//evil.example.net/x'))->not->toContain('href="//');
});

it('refuses urls that are not web addresses', function (string $url) {
    expect(Embeds::render($url))->toBe('');
})->with(['javascript:alert(1)', 'data:text/html,x', '', 'not a url', 'ftp://example.net/a']);

it('does not trust look-alike video hosts', function () {
    expect(Embeds::render('https://youtube.com.evil.net/watch?v=abc'))->not->toContain('iframe')
        ->and(Embeds::render('https://notvimeo.com/123'))->not->toContain('iframe');
});

it('renders the embed shortcode from its content or attribute', function () {
    $handler = app(EmbedHandler::class);

    expect($handler->render(new Shortcode('embed', ['width' => '400'], ' https://vimeo.com/1 '), shortcodeContext()))->toContain('player.vimeo.com/video/1')
        ->and($handler->render(new Shortcode('embed', ['src' => 'https://vimeo.com/2']), shortcodeContext()))->toContain('player.vimeo.com/video/2')
        ->and($handler->render(new Shortcode('embed', ['https://vimeo.com/3']), shortcodeContext()))->toContain('player.vimeo.com/video/3')
        ->and($handler->render(new Shortcode('embed'), shortcodeContext()))->toBe('');
});

it('renders the video shortcode', function () {
    $html = app(VideoHandler::class)->render(new Shortcode('video', [
        'src' => 'https://x.test/a.mp4', 'poster' => 'https://x.test/p.jpg', 'width' => '640', 'height' => '360', 'loop' => 'on', 'preload' => 'none',
    ]), shortcodeContext());

    expect($html)->toBe('<figure class="wp-block-video"><video controls src="https://x.test/a.mp4" poster="https://x.test/p.jpg" width="640" height="360" preload="none" loop></video></figure>');
});

it('renders the sources of a video', function () {
    $html = app(VideoHandler::class)->render(new Shortcode('video', ['mp4' => 'a.mp4', 'webm' => 'a.webm']), shortcodeContext());

    expect($html)->toBe('<figure class="wp-block-video"><video controls><source src="a.mp4" type="video/mp4" /><source src="a.webm" type="video/webm" /></video></figure>');
});

it('keeps the content of an enclosing video', function () {
    $html = app(VideoHandler::class)->render(new Shortcode('video', ['src' => 'a.mp4'], '<track src="a.vtt" />'), shortcodeContext());

    expect($html)->toContain('<track src="a.vtt" /></video>');
});

it('renders the audio shortcode', function () {
    $handler = app(AudioHandler::class);

    expect($handler->render(new Shortcode('audio', ['src' => 'a.mp3', 'loop' => 'on']), shortcodeContext()))
        ->toBe('<figure class="wp-block-audio"><audio controls src="a.mp3" loop></audio></figure>')
        ->and($handler->render(new Shortcode('audio', ['mp3' => 'a.mp3', 'ogg' => 'a.ogg']), shortcodeContext()))
        ->toBe('<figure class="wp-block-audio"><audio controls><source src="a.mp3" type="audio/mpeg" /><source src="a.ogg" type="audio/ogg" /></audio></figure>');
});

it('renders nothing for media without a source', function () {
    expect(app(VideoHandler::class)->render(new Shortcode('video'), shortcodeContext()))->toBe('')
        ->and(app(AudioHandler::class)->render(new Shortcode('audio'), shortcodeContext()))->toBe('');
});

it('escapes media attributes and refuses script urls', function () {
    $html = app(VideoHandler::class)->render(new Shortcode('video', ['src' => '"><script>', 'poster' => 'javascript:x', 'width' => '1"']), shortcodeContext());

    expect($html)->not->toContain('<script>')->not->toContain('javascript:')->not->toContain('width="1"');
});

it('knows the built-in shortcodes and accepts custom ones', function () {
    WpMigrator::mapShortcode('shout', ShoutHandler::class);

    expect(WpMigrator::hasShortcode('gallery'))->toBeTrue()
        ->and(WpMigrator::hasShortcode('wp_caption'))->toBeTrue()
        ->and(WpMigrator::hasShortcode('video'))->toBeTrue()
        ->and(WpMigrator::hasShortcode('audio'))->toBeTrue()
        ->and(WpMigrator::hasShortcode('embed'))->toBeTrue()
        ->and(WpMigrator::hasShortcode('caption'))->toBeTrue()
        ->and(WpMigrator::hasShortcode('nope'))->toBeFalse()
        ->and(WpMigrator::shortcodeHandler('shout'))->toBeInstanceOf(ShoutHandler::class)
        ->and(WpMigrator::shortcodeHandler('caption'))->toBeInstanceOf(CaptionHandler::class);
});

it('rejects a shortcode handler that does not implement the contract', function () {
    WpMigrator::mapShortcode('x', stdClass::class);
})->throws(InvalidArgumentException::class);
