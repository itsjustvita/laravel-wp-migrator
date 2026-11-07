<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use ItsJustVita\WpMigrator\Content\Blocks\Block;
use ItsJustVita\WpMigrator\Content\Blocks\BlockRenderer;
use ItsJustVita\WpMigrator\Content\ContentTransformer;
use ItsJustVita\WpMigrator\Content\Shortcodes\Shortcode;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeContext;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeHandler;
use ItsJustVita\WpMigrator\Facades\WpMigrator;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;

final class BoxHandler implements ShortcodeHandler
{
    public function render(Shortcode $shortcode, ShortcodeContext $context): string
    {
        return '<div class="box">'.$context->process((string) $shortcode->content).'</div>';
    }
}

function contentPost(string $content, int $id = 100): SourcePost
{
    return new SourcePost(
        id: $id, type: 'post', status: 'publish', title: 'T', slug: 't', content: $content, excerpt: '',
        authorId: null, parentId: null, menuOrder: 0, mimeType: null, guid: null,
        publishedAt: CarbonImmutable::parse('2026-01-15'), modifiedAt: CarbonImmutable::parse('2026-01-15'), meta: MetaBag::empty(),
    );
}

/**
 * @return list<Block>
 */
function transformContent(string $content, ?ImportContext $context = null, int $id = 100): array
{
    return app(ContentTransformer::class)->transform(contentPost($content, $id), $context ?? importContext('posts'));
}

function importMedia(ImportContext $context, int $sourceId, string $file): Media
{
    $media = Media::create(['disk' => 'public', 'path' => 'wp-uploads/'.$file]);
    $context->map->record(SourceType::Post, $sourceId, $media, 'x');

    return $media;
}

beforeEach(function () {
    Storage::fake('public');
    $this->site = $this->source();
    $this->site->option('home', 'https://old.example.com');
    $this->site->option('permalink_structure', '/%postname%/');
});

it('returns nothing for empty content', function () {
    expect(transformContent(''))->toBe([])
        ->and(transformContent("  \n "))->toBe([]);
});

it('formats classic content into one freeform block', function () {
    $blocks = transformContent("First\n\nSecond\nline");

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->isFreeform())->toBeTrue()
        ->and($blocks[0]->innerHtml)->toBe("<p>First</p>\n<p>Second<br />\nline</p>");
});

it('treats content with only broken markers as classic content', function () {
    $blocks = transformContent("<!-- wp:paragraph {bad} -->\nOne\n\nTwo");

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->isFreeform())->toBeTrue()
        ->and($blocks[0]->innerHtml)->toContain('<p>Two</p>');
});

it('does not reformat the html of block content', function () {
    $blocks = transformContent("<!-- wp:html -->\n<div>a\n\nb</div>\n<!-- /wp:html -->\n\nLoose\n\ntext");

    expect($blocks[0]->name)->toBe('core/html')
        ->and($blocks[0]->innerHtml)->toBe("\n<div>a\n\nb</div>\n")
        ->and($blocks[1]->isFreeform())->toBeTrue()
        ->and($blocks[1]->innerHtml)->toBe("Loose\n\ntext");
});

it('renders known shortcodes in classic content without wrapping them in paragraphs', function () {
    $import = importContext('posts');
    importMedia($import, 9, '2026/a.jpg');

    $blocks = transformContent("Before\n\n[gallery ids=\"9\"]\n\nAfter", $import);

    expect($blocks[0]->innerHtml)
        ->toStartWith("<p>Before</p>\n<figure class=\"wp-block-gallery")
        ->toEndWith("</figure>\n<p>After</p>")
        ->not->toContain('<p><figure');
});

it('renders caption shortcodes in classic content', function () {
    $blocks = transformContent('[caption id="attachment_1" width="300"]<img src="a.jpg" /> Text[/caption]');

    expect($blocks[0]->innerHtml)->toBe('<figure id="attachment_1" class="wp-caption" style="width:300px"><img src="a.jpg" /><figcaption class="wp-caption-text">Text</figcaption></figure>');
});

it('passes content of nested shortcodes through the processor', function () {
    WpMigrator::mapShortcode('box', BoxHandler::class);

    $blocks = transformContent('[box][embed]https://vimeo.com/1[/embed][/box]');

    expect($blocks[0]->innerHtml)->toContain('player.vimeo.com/video/1')->toStartWith('<div class="box">');
});

it('keeps unknown shortcodes and reports them with counts and example posts', function () {
    $import = importContext('posts');

    $blocks = transformContent('[foo a="1"] and [foo] and [bar]x[/bar]', $import, 100);
    transformContent('[foo]', $import, 101);

    expect($blocks[0]->innerHtml)->toBe('<p>[foo a="1"] and [foo] and [bar]x[/bar]</p>')
        ->and($import->report->unknownShortcodes())->toBe([
            'foo' => ['count' => 3, 'post_ids' => [100, 101]],
            'bar' => ['count' => 1, 'post_ids' => [100]],
        ]);
});

it('rewrites urls in classic content', function () {
    Media::create(['disk' => 'public', 'path' => 'wp-uploads/2026/a.jpg']);
    $this->site->post(['post_name' => 'hello']);

    $blocks = transformContent('<a href="https://old.example.com/hello/">x</a> <img src="/wp-content/uploads/2026/a.jpg">');

    expect($blocks[0]->innerHtml)->toBe('<p><a href="/blog/hello">x</a> <img src="/storage/wp-uploads/2026/a.jpg"></p>');
});

it('does not mistake the new urls a handler produces for old links', function () {
    $import = importContext('posts');
    importMedia($import, 9, '2026/a.jpg');

    $blocks = transformContent('[gallery ids="9" link="file"]', $import);

    expect($blocks[0]->innerHtml)->toContain('src="/storage/wp-uploads/2026/a.jpg"')
        ->and($import->report->unresolvedReferences())->toBe([]);
});

it('does not mistake the new urls a handler produces inside a block for old links', function () {
    $import = importContext('posts');
    importMedia($import, 9, '2026/a.jpg');

    $blocks = transformContent('<!-- wp:shortcode -->[gallery ids="9"]<!-- /wp:shortcode -->', $import);

    expect($blocks[0]->innerHtml)->toContain('src="/storage/wp-uploads/2026/a.jpg"')
        ->and($import->report->unresolvedReferences())->toBe([]);
});

it('does not rewrite what a handler produces a second time', function () {
    WpMigrator::mapShortcode('box', BoxHandler::class);
    config()->set('wp-migrator.media.directory', 'wp-content/uploads');
    $import = importContext('posts');
    importMedia($import, 9, '2026/a.jpg');
    Media::query()->update(['path' => 'wp-content/uploads/2026/a.jpg']);

    $blocks = transformContent('[gallery ids="9"]', $import);

    expect($blocks[0]->innerHtml)->toContain('src="/storage/wp-content/uploads/2026/a.jpg"')
        ->and($import->report->unresolvedReferences())->toBe([]);
});

it('rewrites old urls in shortcode attributes and content', function () {
    Media::create(['disk' => 'public', 'path' => 'wp-uploads/2026/a.mp4']);
    $this->site->post(['post_name' => 'hello']);

    $video = transformContent('[video src="https://old.example.com/wp-content/uploads/2026/a.mp4"]');
    $bare = transformContent('[video src=https://old.example.com/wp-content/uploads/2026/a.mp4]');
    $embed = transformContent('[embed]https://old.example.com/hello/[/embed]');

    expect($video[0]->innerHtml)->toContain('src="/storage/wp-uploads/2026/a.mp4"')
        ->and($bare[0]->innerHtml)->toContain('src="/storage/wp-uploads/2026/a.mp4"')
        ->and($embed[0]->innerHtml)->toBe('<figure class="wp-block-embed"><a href="/blog/hello">/blog/hello</a></figure>');
});

it('keeps an embed block that links to the site itself', function () {
    $this->site->post(['post_name' => 'hello']);

    $blocks = transformContent('<!-- wp:embed {"url":"https://old.example.com/hello/"} --><figure>https://old.example.com/hello/</figure><!-- /wp:embed -->');

    expect($blocks[0]->attrs['url'])->toBe('/blog/hello')
        ->and(app(BlockRenderer::class)->render($blocks[0]))->toContain('<a href="/blog/hello">');
});

it('rewrites urls in the html a shortcode produced', function () {
    Media::create(['disk' => 'public', 'path' => 'wp-uploads/2026/a.jpg']);

    $blocks = transformContent('[caption]<img src="https://old.example.com/wp-content/uploads/2026/a.jpg" /> Text[/caption]');

    expect($blocks[0]->innerHtml)->toContain('<img src="/storage/wp-uploads/2026/a.jpg" />');
});

it('processes the html of blocks at every level', function () {
    Media::create(['disk' => 'public', 'path' => 'wp-uploads/2026/a.jpg']);
    $this->site->post(['post_name' => 'hello']);

    $blocks = transformContent(
        '<!-- wp:group --><div data-x="https://old.example.com/hello/">'
        .'<!-- wp:image --><figure><img src="https://old.example.com/wp-content/uploads/2026/a.jpg"/></figure><!-- /wp:image -->'
        .'</div><!-- /wp:group -->'
    );

    expect($blocks[0]->innerHtml)->toBe('<div data-x="/blog/hello"></div>')
        ->and($blocks[0]->innerContent)->toBe(['<div data-x="/blog/hello">', null, '</div>'])
        ->and($blocks[0]->innerBlocks[0]->innerHtml)->toBe('<figure><img src="/storage/wp-uploads/2026/a.jpg"/></figure>');
});

it('rewrites urls in block attributes, also nested ones', function () {
    Media::create(['disk' => 'public', 'path' => 'wp-uploads/2026/a.jpg']);

    $blocks = transformContent('<!-- wp:cover {"url":"https://old.example.com/wp-content/uploads/2026/a.jpg","style":{"bg":"url(/wp-content/uploads/2026/a.jpg)"},"n":5} --><div></div><!-- /wp:cover -->');

    expect($blocks[0]->attrs)->toBe([
        'url' => '/storage/wp-uploads/2026/a.jpg',
        'style' => ['bg' => 'url(/storage/wp-uploads/2026/a.jpg)'],
        'n' => 5,
    ]);
});

it('maps the media ids in block attributes', function (string $block, array $source, array $expected) {
    $import = importContext('posts');
    $media = importMedia($import, 9, '2026/a.jpg');
    $expected = array_map(fn (mixed $value) => $value === 'NEW' ? $media->id : $value, $expected);

    $blocks = transformContent('<!-- wp:'.$block.' '.json_encode($source).' /-->', $import);

    expect($blocks[0]->attrs)->toBe($expected);
})->with([
    'image' => ['image', ['id' => 9, 'sizeSlug' => 'large'], ['id' => 'NEW', 'sizeSlug' => 'large']],
    'cover' => ['cover', ['id' => 9], ['id' => 'NEW']],
    'video' => ['video', ['id' => 9], ['id' => 'NEW']],
    'audio' => ['audio', ['id' => 9], ['id' => 'NEW']],
    'file' => ['file', ['id' => 9], ['id' => 'NEW']],
    'media text' => ['media-text', ['mediaId' => 9], ['mediaId' => 'NEW']],
]);

it('maps the id lists of galleries', function () {
    $import = importContext('posts');
    $first = importMedia($import, 9, '2026/a.jpg');
    $second = importMedia($import, 8, '2026/b.jpg');

    $blocks = transformContent('<!-- wp:gallery {"ids":[9,8]} /-->', $import);

    expect($blocks[0]->attrs)->toBe(['ids' => [$first->id, $second->id]]);
});

it('removes media ids it cannot resolve and reports them', function () {
    $import = importContext('posts');

    $blocks = transformContent('<!-- wp:image {"id":77,"sizeSlug":"large"} /--><!-- wp:gallery {"ids":[78]} /-->', $import);

    expect($blocks[0]->attrs)->toBe(['sizeSlug' => 'large'])
        ->and($blocks[1]->attrs)->toBe(['ids' => []])
        ->and(array_column($import->report->unresolvedReferences(), 'reference'))->toBe(['77', '78'])
        ->and(array_column($import->report->unresolvedReferences(), 'kind'))->toBe(['block media', 'block media'])
        ->and($import->rowIncomplete())->toBeTrue();
});

it('leaves ids of other blocks alone', function () {
    $blocks = transformContent('<!-- wp:acme/thing {"id":9} /-->');

    expect($blocks[0]->attrs)->toBe(['id' => 9]);
});

it('renders shortcodes inside blocks', function () {
    $blocks = transformContent('<!-- wp:shortcode -->[embed]https://vimeo.com/1[/embed]<!-- /wp:shortcode -->');

    expect($blocks[0]->name)->toBe('core/shortcode')
        ->and($blocks[0]->innerHtml)->toContain('player.vimeo.com/video/1');
});

it('does not touch shortcodes in code blocks', function () {
    $import = importContext('posts');
    $html = '<pre class="wp-block-code"><code>[gallery ids="1"] [foo]</code></pre>';

    $blocks = transformContent('<!-- wp:code -->'.$html.'<!-- /wp:code -->', $import);

    expect($blocks[0]->innerHtml)->toBe($html)
        ->and($import->report->unknownShortcodes())->toBe([]);
});

it('does not touch urls in code blocks', function () {
    $this->site->post(['post_name' => 'hello']);
    $import = importContext('posts');
    $html = '<pre class="wp-block-code"><code>curl https://old.example.com/hello/ /wp-content/uploads/a.jpg</code></pre>';

    $blocks = transformContent('<!-- wp:code -->'.$html.'<!-- /wp:code -->', $import);

    expect($blocks[0]->innerHtml)->toBe($html)
        ->and($import->report->unresolvedReferences())->toBe([]);
});

it('replaces references to reusable blocks with their content', function () {
    $reusable = $this->site->post(['post_type' => 'wp_block', 'post_content' => '<!-- wp:paragraph --><p>Shared</p><!-- /wp:paragraph --><!-- wp:spacer /-->']);

    $blocks = transformContent('<!-- wp:paragraph --><p>Own</p><!-- /wp:paragraph --><!-- wp:block {"ref":'.$reusable.'} /-->');

    expect(array_map(fn (Block $b) => $b->name, $blocks))->toBe(['core/paragraph', 'core/paragraph', 'core/spacer'])
        ->and($blocks[1]->innerHtml)->toBe('<p>Shared</p>');
});

it('replaces references to reusable blocks inside other blocks', function () {
    $reusable = $this->site->post(['post_type' => 'wp_block', 'post_content' => '<!-- wp:spacer /--><!-- wp:separator /-->']);

    $blocks = transformContent('<!-- wp:group --><div><!-- wp:block {"ref":'.$reusable.'} /--></div><!-- /wp:group -->');

    expect($blocks[0]->innerContent)->toBe(['<div>', null, null, '</div>'])
        ->and(array_map(fn (Block $b) => $b->name, $blocks[0]->innerBlocks))->toBe(['core/spacer', 'core/separator']);
});

it('survives reusable blocks that refer to themselves', function () {
    $a = $this->site->post(['post_type' => 'wp_block']);
    $b = $this->site->post(['post_type' => 'wp_block', 'post_content' => '<!-- wp:block {"ref":'.$a.'} /--><!-- wp:spacer /-->']);
    $this->site->table('posts')->where('ID', $a)->update(['post_content' => '<!-- wp:block {"ref":'.$b.'} /-->']);

    $blocks = transformContent('<!-- wp:block {"ref":'.$a.'} /-->');

    expect(array_map(fn (Block $block) => $block->name, $blocks))->toContain('core/spacer');
});

it('reports reusable blocks that no longer exist', function () {
    $import = importContext('posts');

    $blocks = transformContent('<!-- wp:paragraph --><p>Own</p><!-- /wp:paragraph --><!-- wp:block {"ref":999} /-->', $import);

    expect($blocks)->toHaveCount(1)
        ->and($import->report->unresolvedReferences()[0])->toMatchArray(['kind' => 'reusable block', 'reference' => '999']);
});

it('reports blocks without a renderer and keeps their html', function () {
    $import = importContext('posts');

    $blocks = transformContent('<!-- wp:acme/hero --><div>Hi</div><!-- /wp:acme/hero --><!-- wp:acme/hero /--><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->', $import, 7);
    transformContent('<!-- wp:group --><div><!-- wp:latest-posts /--></div><!-- /wp:group -->', $import, 8);

    expect($blocks[0]->innerHtml)->toBe('<div>Hi</div>')
        ->and($import->report->unknownBlocks())->toBe([
            'acme/hero' => ['count' => 2, 'post_ids' => [7]],
            'core/latest-posts' => ['count' => 1, 'post_ids' => [8]],
        ]);
});

it('limits the example posts per unknown item but keeps counting', function () {
    $import = importContext('posts');

    foreach (range(1, 8) as $id) {
        transformContent('[foo]', $import, $id);
    }

    expect($import->report->unknownShortcodes()['foo']['count'])->toBe(8)
        ->and($import->report->unknownShortcodes()['foo']['post_ids'])->toBe([1, 2, 3, 4, 5]);
});

it('produces stored blocks that survive json encoding', function () {
    $blocks = transformContent('<!-- wp:group {"a":1} --><div><!-- wp:spacer /--></div><!-- /wp:group -->');

    $stored = json_decode(json_encode(array_map(fn (Block $b) => $b->toArray(), $blocks)), true);

    expect(Block::fromArray($stored[0])->innerBlocks[0]->name)->toBe('core/spacer');
});
