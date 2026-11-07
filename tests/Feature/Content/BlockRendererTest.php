<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use ItsJustVita\WpMigrator\Content\Blocks\Block;
use ItsJustVita\WpMigrator\Content\Blocks\BlockRenderer;
use ItsJustVita\WpMigrator\Facades\WpMigrator;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\WpMigratorServiceProvider;

function renderer(): BlockRenderer
{
    return app(BlockRenderer::class);
}

function overrideViews(): string
{
    $dir = sys_get_temp_dir().'/wp-migrator-views-'.uniqid();
    File::ensureDirectoryExists($dir.'/blocks/acme');
    View::prependNamespace('wp-migrator', $dir);

    return $dir;
}

it('ships a renderer for the common core blocks', function (string $name) {
    $block = new Block('core/'.$name, innerHtml: '<div class="stored">x</div>');

    expect(renderer()->hasRenderer('core/'.$name))->toBeTrue()
        ->and(trim(renderer()->render($block)))->toBe('<div class="stored">x</div>');
})->with([
    'paragraph', 'heading', 'list', 'list-item', 'image', 'gallery', 'quote', 'pullquote', 'buttons', 'button',
    'columns', 'column', 'group', 'table', 'separator', 'spacer', 'html', 'code', 'preformatted', 'video', 'audio',
    'file', 'media-text', 'cover', 'freeform', 'shortcode', 'more', 'nextpage',
]);

it('renders unknown blocks from their stored html', function () {
    expect(renderer()->hasRenderer('acme/hero'))->toBeFalse()
        ->and(renderer()->render(new Block('acme/hero', innerHtml: '<div>Hi</div>')))->toBe('<div>Hi</div>')
        ->and(renderer()->render(new Block('core/latest-posts')))->toBe('');
});

it('puts inner blocks back where they belong', function () {
    $block = new Block('core/group', innerHtml: '<div><hr></div>', innerBlocks: [
        new Block('core/paragraph', innerHtml: '<p>A</p>'),
        new Block('acme/x', innerHtml: '<p>B</p>'),
    ], innerContent: ['<div>', null, '<hr>', null, '</div>']);

    expect(trim(renderer()->render($block)))->toBe('<div><p>A</p><hr><p>B</p></div>');
});

it('keeps inner blocks of unknown blocks', function () {
    $block = new Block('acme/wrap', innerHtml: '<section></section>', innerBlocks: [
        new Block('core/paragraph', innerHtml: '<p>A</p>'),
    ], innerContent: ['<section>', null, '</section>']);

    expect(renderer()->render($block))->toBe('<section><p>A</p></section>');
});

it('appends inner blocks when their position is unknown', function () {
    $block = new Block('core/group', innerHtml: '<div></div>', innerBlocks: [new Block('core/spacer', innerHtml: 'x')]);

    expect(trim(renderer()->render($block)))->toBe('<div></div>x');
});

it('renders a list of blocks in order', function () {
    expect(renderer()->renderMany([
        new Block('core/paragraph', innerHtml: '<p>A</p>'),
        ['name' => 'acme/x', 'innerHTML' => '<p>B</p>'],
    ]))->toBe('<p>A</p><p>B</p>');
});

it('turns embed blocks into a player or a link', function () {
    $youtube = new Block('core/embed', ['url' => 'https://youtu.be/abc'], '<figure><div>https://youtu.be/abc</div><figcaption>Cap <em>tion</em></figcaption></figure>');
    $other = new Block('core/embed', ['url' => 'https://example.net/a'], '<figure>x</figure>');
    $none = new Block('core/embed', [], '<figure>stored</figure>');

    expect(renderer()->render($youtube))->toContain('youtube-nocookie.com/embed/abc')->toContain('<figcaption>Cap <em>tion</em></figcaption>')
        ->and(renderer()->render($other))->toContain('<a href="https://example.net/a">')
        ->and(trim(renderer()->render($none)))->toBe('<figure>stored</figure>');
});

it('uses a published view instead of the shipped one', function () {
    $dir = overrideViews();
    file_put_contents($dir.'/blocks/heading.blade.php', '<h{{ $attrs[\'level\'] ?? 2 }} class="fancy">{!! $block->unwrapped() !!}</h{{ $attrs[\'level\'] ?? 2 }}>');

    $html = renderer()->render(new Block('core/heading', ['level' => 3], '<h3 class="wp-block-heading">Title <em>x</em></h3>'));

    expect($html)->toBe('<h3 class="fancy">Title <em>x</em></h3>');

    File::deleteDirectory($dir);
});

it('finds views for blocks of other namespaces', function () {
    $dir = overrideViews();
    file_put_contents($dir.'/blocks/acme/hero.blade.php', '<div class="hero">{{ $attrs[\'title\'] }}{!! $content !!}</div>');

    expect(renderer()->hasRenderer('acme/hero'))->toBeTrue()
        ->and(renderer()->render(new Block('acme/hero', ['title' => '<T>'], 'x')))->toBe('<div class="hero">&lt;T&gt;x</div>');

    File::deleteDirectory($dir);
});

it('uses a view registered for a block name', function () {
    $dir = sys_get_temp_dir().'/wp-migrator-views-'.uniqid();
    File::ensureDirectoryExists($dir);
    View::addLocation($dir);
    file_put_contents($dir.'/my-hero.blade.php', '<b>{!! $content !!}</b>');

    WpMigrator::renderBlockUsing('acme/hero', 'my-hero');
    WpMigrator::renderBlockUsing('core/paragraph', 'my-hero');

    expect(renderer()->hasRenderer('acme/hero'))->toBeTrue()
        ->and(renderer()->render(new Block('acme/hero', innerHtml: 'x')))->toBe('<b>x</b>')
        ->and(renderer()->render(new Block('core/paragraph', innerHtml: 'y')))->toBe('<b>y</b>');

    File::deleteDirectory($dir);
});

it('publishes the views under their own tag', function () {
    $paths = ServiceProvider::pathsToPublish(WpMigratorServiceProvider::class, 'wp-migrator-views');

    expect($paths)->toHaveCount(1)
        ->and(array_values($paths)[0])->toBe(resource_path('views/vendor/wp-migrator'));
});

it('renders the blade component from stored blocks', function () {
    $post = Post::create(['type' => 'post', 'status' => 'publish', 'title' => 'T', 'slug' => 't', 'blocks' => [
        (new Block('core/paragraph', innerHtml: '<p>A</p>'))->toArray(),
        (new Block('core/group', innerHtml: '<div></div>', innerBlocks: [new Block('acme/x', innerHtml: '<i>B</i>')], innerContent: ['<div>', null, '</div>']))->toArray(),
    ]]);

    $html = Blade::render('<x-wp-blocks :blocks="$post->blocks" />', ['post' => $post->fresh()]);

    expect(trim($html))->toBe('<p>A</p><div><i>B</i></div>');
});

it('accepts block objects and empty values in the component', function () {
    expect(trim(Blade::render('<x-wp-blocks :blocks="$blocks" />', ['blocks' => [new Block('acme/x', innerHtml: 'x')]])))->toBe('x')
        ->and(trim(Blade::render('<x-wp-blocks :blocks="null" />')))->toBe('')
        ->and(trim(Blade::render('<x-wp-blocks :blocks="[]" />')))->toBe('')
        ->and(trim(Blade::render('<x-wp-blocks />')))->toBe('');
});

it('never compiles stored content as a template', function (string $html) {
    $rendered = Blade::render('<x-wp-blocks :blocks="$blocks" />', ['blocks' => [
        ['name' => 'core/paragraph', 'innerHTML' => $html],
        ['name' => 'acme/unknown', 'innerHTML' => $html],
    ]]);

    expect($rendered)->toBe($html.$html);
})->with([
    'echo' => ['{{ 1 + 1 }}'],
    'raw echo' => ['{!! 1 + 1 !!}'],
    'php directive' => ['@php echo "pwned"; @endphp'],
    'php tag' => ['<?php echo "pwned"; ?>'],
    'component' => ['<x-wp-blocks />'],
]);

it('reads damaged stored data without failing', function () {
    expect(renderer()->renderMany(['nope', 5, ['name' => 5], ['innerHTML' => 'kept'], null]))->toBe('kept');
});

it('does not treat a crafted block name as a view path', function (string $name) {
    expect(renderer()->hasRenderer($name))->toBeFalse()
        ->and(renderer()->render(new Block($name, innerHtml: 'stored')))->toBe('stored');
})->with([
    '../../secret',
    'core/../../secret',
    'a.b',
    'core/a.b',
    'Core/Paragraph',
    'a/b/c',
    'a::b',
    'wp-migrator::blocks.paragraph',
    '',
]);

it('maps block names to views one to one', function () {
    $dir = overrideViews();
    File::ensureDirectoryExists($dir.'/blocks/nested');
    file_put_contents($dir.'/blocks/nested/view.blade.php', 'from the view');

    expect(renderer()->hasRenderer('core/nested.view'))->toBeFalse()
        ->and(renderer()->render(new Block('core/nested.view', innerHtml: 'stored')))->toBe('stored');

    File::deleteDirectory($dir);
});

it('unwraps the outer element of a block', function (string $html, string $expected) {
    expect((new Block('x', innerHtml: $html))->unwrapped())->toBe($expected);
})->with([
    'simple' => ['<h2>Title</h2>', 'Title'],
    'whitespace' => ["\n<h2 class=\"a\">Title</h2>\n", 'Title'],
    'nested markup' => ['<p>a <em>b</em></p>', 'a <em>b</em>'],
    'attribute with a bracket' => ['<p title="a>b">x</p>', 'x'],
    'no single element' => ['<p>a</p><p>b</p>', '<p>a</p><p>b</p>'],
    'plain text' => ['text', 'text'],
]);
