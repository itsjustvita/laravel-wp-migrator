<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use ItsJustVita\WpMigrator\Content\Blocks\Block;
use ItsJustVita\WpMigrator\Content\Blocks\BlockRenderer;
use ItsJustVita\WpMigrator\Content\ContentTransformer;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;

/**
 * @param  list<array<string, mixed>>|string  $elements
 */
function elementorPage(array|string $elements, string $content = '', string $mode = 'builder', int $id = 100): SourcePost
{
    return new SourcePost(
        id: $id, type: 'page', status: 'publish', title: 'T', slug: 't', content: $content, excerpt: '',
        authorId: null, parentId: null, menuOrder: 0, mimeType: null, guid: null,
        publishedAt: CarbonImmutable::parse('2026-01-15'), modifiedAt: CarbonImmutable::parse('2026-01-15'),
        meta: new MetaBag([
            '_elementor_edit_mode' => [$mode],
            '_elementor_data' => [is_string($elements) ? $elements : (string) json_encode($elements)],
        ]),
    );
}

/**
 * @return list<Block>
 */
function transformElementor(SourcePost $post, ?ImportContext $context = null): array
{
    return app(ContentTransformer::class)->transform($post, $context ?? importContext('posts'));
}

beforeEach(function () {
    Storage::fake('public');
    $this->site = $this->source();
    $this->site->option('home', 'https://old.example.com');
    $this->site->option('permalink_structure', '/%postname%/');
});

it('imports an Elementor page from its data instead of its HTML copy', function () {
    $blocks = transformElementor(elementorPage(
        [['elType' => 'container', 'settings' => [], 'elements' => [['elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['title' => 'Hello']]]]],
        '<h2>Hello</h2> the HTML copy',
    ));

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->name)->toBe('core/group')
        ->and($blocks[0]->innerBlocks[0]->innerHtml)->toBe('<h2 class="wp-block-heading">Hello</h2>');
});

it('rewrites links, resolves media and renders shortcodes in converted blocks', function () {
    $about = $this->site->page(['post_title' => 'About', 'post_name' => 'about']);
    $context = importContext('posts');
    $media = Media::create(['disk' => 'public', 'path' => 'wp-uploads/2026/01/hero.png']);
    $context->map->record(SourceType::Post, 12, $media, 'x');

    $blocks = transformElementor(elementorPage([
        ['elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['title' => 'Us', 'link' => ['url' => 'https://old.example.com/about/']]],
        ['elType' => 'widget', 'widgetType' => 'image', 'settings' => ['image' => ['id' => 12, 'url' => 'https://old.example.com/wp-content/uploads/2026/01/hero.png']]],
        ['elType' => 'widget', 'widgetType' => 'shortcode', 'settings' => ['shortcode' => '[caption]Text[/caption]']],
        ['elType' => 'widget', 'widgetType' => 'acme-cta', 'settings' => ['cta_url' => ['url' => 'https://old.example.com/about/'], 'image' => ['id' => 12]]],
    ]), $context);

    expect($blocks[0]->innerHtml)->toBe('<h2 class="wp-block-heading"><a href="/about">Us</a></h2>')
        ->and($blocks[1]->attrs['id'])->toBe($media->id)
        ->and($blocks[1]->innerHtml)->toContain('src="/storage/wp-uploads/2026/01/hero.png"')
        ->and($blocks[2]->innerHtml)->not->toContain('[caption]')
        ->and($blocks[3]->name)->toBe('elementor/acme-cta')
        ->and($blocks[3]->attrs['cta_url']['url'])->toBe('/about')
        ->and($blocks[3]->attrs['image']['id'])->toBe(12)
        ->and($about)->toBeInt();
});

it('reports widgets without a view and passes on converter warnings', function () {
    $context = importContext('posts');

    transformElementor(elementorPage([
        ['elType' => 'widget', 'widgetType' => 'acme-cta', 'settings' => []],
        ['elType' => 'widget', 'widgetType' => 'global', 'templateID' => 999, 'settings' => []],
    ]), $context);

    expect(array_keys($context->report->unknownBlocks()))->toBe(['elementor/acme-cta'])
        ->and($context->report->warnings()[0]['message'])->toBe('Elementor template 999 was not found; its content is missing.');
});

it('falls back to the HTML copy when the data cannot be read', function () {
    $context = importContext('posts');

    $blocks = transformElementor(elementorPage('{broken', 'Plain copy'), $context);

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->innerHtml)->toBe('<p>Plain copy</p>')
        ->and($context->report->warnings()[0]['message'])->toBe('The Elementor data could not be read; the HTML copy in the content was imported instead.');
});

it('uses the content when the page was not edited with Elementor, or conversion is off', function () {
    $data = [['elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['title' => 'From data']]];

    expect(transformElementor(elementorPage($data, 'From content', mode: ''))[0]->innerHtml)->toBe('<p>From content</p>');

    config()->set('wp-migrator.content.elementor', false);

    expect(transformElementor(elementorPage($data, 'From content'))[0]->innerHtml)->toBe('<p>From content</p>');
});

it('uses the content when the data holds nothing', function () {
    $blocks = transformElementor(elementorPage([['elType' => 'container', 'settings' => [], 'elements' => []]], 'Copy'));

    expect($blocks[0]->innerHtml)->toBe('<p>Copy</p>');
});

it('converts a page whose HTML copy is empty', function () {
    $blocks = transformElementor(elementorPage([['elType' => 'widget', 'widgetType' => 'divider', 'settings' => []]]));

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->name)->toBe('core/separator');
});

it('renders converted blocks, including accordions', function () {
    $blocks = transformElementor(elementorPage([
        ['elType' => 'widget', 'widgetType' => 'accordion', 'settings' => ['tabs' => [['tab_title' => 'Why?', 'tab_content' => 'Because.']]]],
        ['elType' => 'widget', 'widgetType' => 'icon-list', 'settings' => ['icon_list' => [['text' => 'One']]]],
    ]));

    expect(app(BlockRenderer::class)->renderMany($blocks))
        ->toBe('<details class="wp-block-details"><summary>Why?</summary><p>Because.</p></details><ul class="wp-block-list"><li>One</li></ul>')
        ->and(app(BlockRenderer::class)->hasRenderer('core/details'))->toBeTrue();
});
