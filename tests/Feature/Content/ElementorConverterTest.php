<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Content\Blocks\Block;
use ItsJustVita\WpMigrator\Content\Elementor\ElementorConverter;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;

/**
 * @param  array<string, mixed>  $settings
 * @return array<string, mixed>
 */
function widget(string $type, array $settings = [], array $extra = []): array
{
    return ['id' => substr(md5($type.json_encode($settings)), 0, 7), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => [], ...$extra];
}

/**
 * @param  list<array<string, mixed>>  $elements
 * @param  array<string, mixed>  $settings
 * @return array<string, mixed>
 */
function container(array $elements, array $settings = []): array
{
    return ['id' => 'c'.count($elements), 'elType' => 'container', 'settings' => $settings, 'elements' => $elements, 'isInner' => false];
}

/**
 * @param  list<array<string, mixed>>  $elements
 * @param  list<string>|null  $warnings
 * @return list<Block>|null
 */
function convertElementor(array|string $elements, ?array &$warnings = []): ?array
{
    $warnings ??= [];
    $json = is_string($elements) ? $elements : json_encode($elements, JSON_UNESCAPED_SLASHES);

    return app(ElementorConverter::class)->convert((string) $json, function (string $message) use (&$warnings): void {
        $warnings[] = $message;
    });
}

/**
 * @param  list<Block>  $blocks
 * @return list<array<string, mixed>>
 */
function blockShapes(array $blocks): array
{
    return array_map(fn (Block $block): array => array_filter([
        'name' => $block->name,
        'attrs' => $block->attrs,
        'html' => $block->innerHtml,
        'inner' => blockShapes($block->innerBlocks),
    ], fn (mixed $value): bool => $value !== [] && $value !== ''), $blocks);
}

it('tells pages built with Elementor from others', function () {
    expect(ElementorConverter::builtWith(new MetaBag(['_elementor_edit_mode' => ['builder'], '_elementor_data' => ['[{"id":"a"}]']])))->toBeTrue()
        ->and(ElementorConverter::builtWith(new MetaBag(['_elementor_edit_mode' => [''], '_elementor_data' => ['[{"id":"a"}]']])))->toBeFalse()
        ->and(ElementorConverter::builtWith(new MetaBag(['_elementor_edit_mode' => ['builder'], '_elementor_data' => ['[]']])))->toBeFalse()
        ->and(ElementorConverter::builtWith(new MetaBag(['_elementor_edit_mode' => ['builder']])))->toBeFalse()
        ->and(ElementorConverter::builtWith(MetaBag::empty()))->toBeFalse();
});

it('converts headings, text and html', function () {
    $blocks = convertElementor([
        widget('heading', ['title' => 'Welcome']),
        widget('heading', ['title' => 'Small <em>print</em>', 'header_size' => 'h4', 'link' => ['url' => 'https://old.example.com/about', 'is_external' => 'on', 'nofollow' => 'on']]),
        widget('heading', ['title' => 'Kicker', 'header_size' => 'span']),
        widget('heading', ['title' => '']),
        widget('text-editor', ['editor' => "First\n\nSecond"]),
        widget('text-editor', ['editor' => '<p>Already a paragraph</p>']),
        widget('html', ['html' => '<div class="raw">Raw</div>']),
        widget('shortcode', ['shortcode' => '[gallery ids="1,2"]']),
    ]);

    expect(blockShapes($blocks))->toBe([
        ['name' => 'core/heading', 'attrs' => ['level' => 2], 'html' => '<h2 class="wp-block-heading">Welcome</h2>'],
        ['name' => 'core/heading', 'attrs' => ['level' => 4], 'html' => '<h4 class="wp-block-heading"><a href="https://old.example.com/about" target="_blank" rel="noopener nofollow">Small <em>print</em></a></h4>'],
        ['name' => 'core/paragraph', 'html' => '<p>Kicker</p>'],
        ['name' => 'core/freeform', 'html' => "<p>First</p>\n<p>Second</p>"],
        ['name' => 'core/freeform', 'html' => '<p>Already a paragraph</p>'],
        ['name' => 'core/html', 'html' => '<div class="raw">Raw</div>'],
        ['name' => 'core/shortcode', 'html' => '[gallery ids="1,2"]'],
    ]);
});

it('converts images with their size, caption and link', function () {
    $blocks = convertElementor([
        widget('image', ['image' => ['url' => 'https://old.example.com/wp-content/uploads/2026/01/hero.png', 'id' => 12, 'alt' => 'A "hero"']]),
        widget('image', ['image' => ['url' => 'https://old.example.com/wp-content/uploads/a.png', 'id' => '13'], 'image_size' => 'medium', 'caption_source' => 'custom', 'caption' => 'Look', 'link_to' => 'file']),
        widget('image', ['image' => ['url' => 'https://old.example.com/wp-content/uploads/b.png', 'id' => 14], 'link_to' => 'custom', 'link' => ['url' => '/shop']]),
        widget('image', ['image' => ['url' => '', 'id' => '']]),
    ]);

    expect(blockShapes($blocks))->toBe([
        ['name' => 'core/image', 'attrs' => ['id' => 12, 'sizeSlug' => 'large'], 'html' => '<figure class="wp-block-image size-large"><img src="https://old.example.com/wp-content/uploads/2026/01/hero.png" alt="A &quot;hero&quot;" class="wp-image-12"/></figure>'],
        ['name' => 'core/image', 'attrs' => ['id' => 13, 'sizeSlug' => 'medium', 'linkDestination' => 'media'], 'html' => '<figure class="wp-block-image size-medium"><a href="https://old.example.com/wp-content/uploads/a.png"><img src="https://old.example.com/wp-content/uploads/a.png" alt="" class="wp-image-13"/></a><figcaption class="wp-element-caption">Look</figcaption></figure>'],
        ['name' => 'core/image', 'attrs' => ['id' => 14, 'sizeSlug' => 'large', 'linkDestination' => 'custom'], 'html' => '<figure class="wp-block-image size-large"><a href="/shop"><img src="https://old.example.com/wp-content/uploads/b.png" alt="" class="wp-image-14"/></a></figure>'],
    ]);
});

it('converts buttons, separators and spacers', function () {
    $blocks = convertElementor([
        widget('button', ['text' => 'Buy <now>', 'link' => ['url' => 'https://old.example.com/shop', 'is_external' => 'on']]),
        widget('button', ['text' => 'No link']),
        widget('button', ['text' => '']),
        widget('divider'),
        widget('spacer', ['space' => ['unit' => 'px', 'size' => 40]]),
        widget('spacer', ['space' => ['unit' => 'vh', 'size' => '5']]),
        widget('spacer'),
    ]);

    expect(blockShapes($blocks))->toBe([
        ['name' => 'core/buttons', 'html' => '<div class="wp-block-buttons"></div>', 'inner' => [
            ['name' => 'core/button', 'html' => '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://old.example.com/shop" target="_blank" rel="noopener">Buy &lt;now&gt;</a></div>'],
        ]],
        ['name' => 'core/buttons', 'html' => '<div class="wp-block-buttons"></div>', 'inner' => [
            ['name' => 'core/button', 'html' => '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">No link</a></div>'],
        ]],
        ['name' => 'core/separator', 'html' => '<hr class="wp-block-separator has-alpha-channel-opacity"/>'],
        ['name' => 'core/spacer', 'attrs' => ['height' => '40px'], 'html' => '<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>'],
        ['name' => 'core/spacer', 'attrs' => ['height' => '5vh'], 'html' => '<div style="height:5vh" aria-hidden="true" class="wp-block-spacer"></div>'],
        ['name' => 'core/spacer', 'attrs' => ['height' => '50px'], 'html' => '<div style="height:50px" aria-hidden="true" class="wp-block-spacer"></div>'],
    ]);
});

it('converts videos into embeds or video blocks', function () {
    $blocks = convertElementor([
        widget('video', ['youtube_url' => 'https://www.youtube.com/watch?v=abc123']),
        widget('video', ['video_type' => 'vimeo', 'vimeo_url' => 'https://vimeo.com/123']),
        widget('video', ['video_type' => 'hosted', 'hosted_url' => ['url' => 'https://old.example.com/wp-content/uploads/clip.mp4', 'id' => 30]]),
        widget('video', ['video_type' => 'hosted', 'insert_url' => 'yes', 'external_url' => ['url' => 'https://cdn.example.net/clip.mp4']]),
        widget('video', ['video_type' => 'dailymotion', 'dailymotion_url' => '']),
    ]);

    expect(blockShapes($blocks))->toBe([
        ['name' => 'core/embed', 'attrs' => ['url' => 'https://www.youtube.com/watch?v=abc123', 'type' => 'video', 'providerNameSlug' => 'youtube'], 'html' => "<figure class=\"wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube\"><div class=\"wp-block-embed__wrapper\">\nhttps://www.youtube.com/watch?v=abc123\n</div></figure>"],
        ['name' => 'core/embed', 'attrs' => ['url' => 'https://vimeo.com/123', 'type' => 'video', 'providerNameSlug' => 'vimeo'], 'html' => "<figure class=\"wp-block-embed is-type-video is-provider-vimeo wp-block-embed-vimeo\"><div class=\"wp-block-embed__wrapper\">\nhttps://vimeo.com/123\n</div></figure>"],
        ['name' => 'core/video', 'attrs' => ['id' => 30], 'html' => '<figure class="wp-block-video"><video controls src="https://old.example.com/wp-content/uploads/clip.mp4"></video></figure>'],
        ['name' => 'core/video', 'html' => '<figure class="wp-block-video"><video controls src="https://cdn.example.net/clip.mp4"></video></figure>'],
    ]);
});

it('converts lists, galleries, boxes, testimonials and accordions', function () {
    $blocks = convertElementor([
        widget('icon-list', ['icon_list' => [['text' => 'One'], ['text' => 'Two', 'link' => ['url' => '/two']], ['text' => '']]]),
        widget('image-gallery', ['wp_gallery' => [['id' => 5, 'url' => 'https://old.example.com/wp-content/uploads/5.jpg'], ['id' => 6, 'url' => 'https://old.example.com/wp-content/uploads/6.jpg']]]),
        widget('icon-box', ['title_text' => 'Fast', 'description_text' => 'Very fast.', 'link' => ['url' => '/speed']]),
        widget('image-box', ['image' => ['url' => 'https://old.example.com/wp-content/uploads/box.png', 'id' => 7], 'title_text' => 'Box', 'title_size' => 'h4', 'description_text' => '']),
        widget('testimonial', ['testimonial_content' => 'Great!', 'testimonial_name' => 'Ada', 'testimonial_job' => 'CTO']),
        widget('accordion', ['tabs' => [['tab_title' => 'Why?', 'tab_content' => 'Because.'], ['tab_title' => '', 'tab_content' => '']]]),
    ]);

    expect(blockShapes($blocks))->toBe([
        ['name' => 'core/list', 'html' => '<ul class="wp-block-list"></ul>', 'inner' => [
            ['name' => 'core/list-item', 'html' => '<li>One</li>'],
            ['name' => 'core/list-item', 'html' => '<li><a href="/two">Two</a></li>'],
        ]],
        ['name' => 'core/gallery', 'attrs' => ['linkTo' => 'none'], 'html' => '<figure class="wp-block-gallery has-nested-images columns-default"></figure>', 'inner' => [
            ['name' => 'core/image', 'attrs' => ['id' => 5, 'sizeSlug' => 'large'], 'html' => '<figure class="wp-block-image size-large"><img src="https://old.example.com/wp-content/uploads/5.jpg" alt="" class="wp-image-5"/></figure>'],
            ['name' => 'core/image', 'attrs' => ['id' => 6, 'sizeSlug' => 'large'], 'html' => '<figure class="wp-block-image size-large"><img src="https://old.example.com/wp-content/uploads/6.jpg" alt="" class="wp-image-6"/></figure>'],
        ]],
        ['name' => 'core/group', 'attrs' => ['className' => 'elementor-icon-box'], 'html' => '<div class="wp-block-group elementor-icon-box"></div>', 'inner' => [
            ['name' => 'core/heading', 'attrs' => ['level' => 3], 'html' => '<h3 class="wp-block-heading"><a href="/speed">Fast</a></h3>'],
            ['name' => 'core/paragraph', 'html' => '<p>Very fast.</p>'],
        ]],
        ['name' => 'core/group', 'attrs' => ['className' => 'elementor-image-box'], 'html' => '<div class="wp-block-group elementor-image-box"></div>', 'inner' => [
            ['name' => 'core/image', 'attrs' => ['id' => 7, 'sizeSlug' => 'large'], 'html' => '<figure class="wp-block-image size-large"><img src="https://old.example.com/wp-content/uploads/box.png" alt="" class="wp-image-7"/></figure>'],
            ['name' => 'core/heading', 'attrs' => ['level' => 4], 'html' => '<h4 class="wp-block-heading">Box</h4>'],
        ]],
        ['name' => 'core/quote', 'html' => '<blockquote class="wp-block-quote"><p>Great!</p><cite>Ada, CTO</cite></blockquote>'],
        ['name' => 'core/details', 'html' => '<details class="wp-block-details"><summary>Why?</summary></details>', 'inner' => [
            ['name' => 'core/freeform', 'html' => '<p>Because.</p>'],
        ]],
    ]);
});

it('keeps the inner content of wrappers in place', function () {
    $blocks = convertElementor([widget('icon-list', ['icon_list' => [['text' => 'One'], ['text' => 'Two']]])]);

    expect($blocks[0]->innerContent)->toBe(['<ul class="wp-block-list">', null, null, '</ul>']);
});

it('turns containers into groups and sections into columns', function () {
    $blocks = convertElementor([
        container([widget('heading', ['title' => 'A']), container([widget('heading', ['title' => 'B'])], ['flex_direction' => 'row'])]),
        container([]),
        ['id' => 's1', 'elType' => 'section', 'settings' => [], 'elements' => [
            ['id' => 'k1', 'elType' => 'column', 'settings' => ['_column_size' => 50], 'elements' => [widget('heading', ['title' => 'Left'])]],
            ['id' => 'k2', 'elType' => 'column', 'settings' => ['_column_size' => 50, '_inline_size' => 33.333], 'elements' => [widget('heading', ['title' => 'Right'])]],
        ]],
        ['id' => 's2', 'elType' => 'section', 'settings' => [], 'elements' => [
            ['id' => 'k3', 'elType' => 'column', 'settings' => ['_column_size' => 100], 'elements' => [widget('heading', ['title' => 'Alone'])]],
        ]],
    ]);

    expect(blockShapes($blocks))->toBe([
        ['name' => 'core/group', 'attrs' => ['layout' => ['type' => 'constrained']], 'html' => '<div class="wp-block-group"></div>', 'inner' => [
            ['name' => 'core/heading', 'attrs' => ['level' => 2], 'html' => '<h2 class="wp-block-heading">A</h2>'],
            ['name' => 'core/group', 'attrs' => ['layout' => ['type' => 'flex', 'flexWrap' => 'nowrap']], 'html' => '<div class="wp-block-group is-layout-flex"></div>', 'inner' => [
                ['name' => 'core/heading', 'attrs' => ['level' => 2], 'html' => '<h2 class="wp-block-heading">B</h2>'],
            ]],
        ]],
        ['name' => 'core/columns', 'html' => '<div class="wp-block-columns"></div>', 'inner' => [
            ['name' => 'core/column', 'attrs' => ['width' => '50%'], 'html' => '<div class="wp-block-column" style="flex-basis:50%"></div>', 'inner' => [
                ['name' => 'core/heading', 'attrs' => ['level' => 2], 'html' => '<h2 class="wp-block-heading">Left</h2>'],
            ]],
            ['name' => 'core/column', 'attrs' => ['width' => '33.333%'], 'html' => '<div class="wp-block-column" style="flex-basis:33.333%"></div>', 'inner' => [
                ['name' => 'core/heading', 'attrs' => ['level' => 2], 'html' => '<h2 class="wp-block-heading">Right</h2>'],
            ]],
        ]],
        ['name' => 'core/group', 'attrs' => ['layout' => ['type' => 'constrained']], 'html' => '<div class="wp-block-group"></div>', 'inner' => [
            ['name' => 'core/heading', 'attrs' => ['level' => 2], 'html' => '<h2 class="wp-block-heading">Alone</h2>'],
        ]],
    ]);
});

it('keeps unknown widgets with all their settings', function () {
    $settings = ['hero_title' => 'Hello', 'cards' => [['title' => 'One', 'url' => ['url' => '/one']]], 'cta_url' => ['url' => 'https://old.example.com/go']];

    $blocks = convertElementor([widget('acme-hero_Section', $settings), widget('form', ['form_name' => 'Contact'])]);

    expect(blockShapes($blocks))->toBe([
        ['name' => 'elementor/acme-hero_section', 'attrs' => $settings],
        ['name' => 'elementor/form', 'attrs' => ['form_name' => 'Contact']],
    ]);
});

it('gives widgets with an unusable name a safe block name', function () {
    $blocks = convertElementor([widget('9 Weird.Name!'), widget(''), widget('---')]);

    expect(array_map(fn (Block $block): string => $block->name, $blocks))->toBe(['elementor/w-9-weird-name']);
});

it('replaces global widgets and templates with their content', function () {
    $template = $this->source()->post(['post_type' => 'elementor_library', 'post_title' => 'Global CTA']);
    $this->source()->postMeta($template, '_elementor_data', json_encode([widget('heading', ['title' => 'From the library'])]));
    $section = $this->source()->post(['post_type' => 'elementor_library', 'post_title' => 'Footer section']);
    $this->source()->postMeta($section, '_elementor_data', json_encode([container([widget('heading', ['title' => 'Shared'])])]));

    $blocks = convertElementor([
        widget('global', [], ['templateID' => $template]),
        widget('template', ['template_id' => (string) $section]),
    ]);

    expect(blockShapes($blocks))->toBe([
        ['name' => 'core/heading', 'attrs' => ['level' => 2], 'html' => '<h2 class="wp-block-heading">From the library</h2>'],
        ['name' => 'core/group', 'attrs' => ['layout' => ['type' => 'constrained']], 'html' => '<div class="wp-block-group"></div>', 'inner' => [
            ['name' => 'core/heading', 'attrs' => ['level' => 2], 'html' => '<h2 class="wp-block-heading">Shared</h2>'],
        ]],
    ]);
});

it('warns about missing and looping templates', function () {
    $loop = $this->source()->post(['post_type' => 'elementor_library']);
    $this->source()->postMeta($loop, '_elementor_data', json_encode([widget('template', ['template_id' => $loop])]));

    $blocks = convertElementor([widget('global', [], ['templateID' => 999]), widget('template', ['template_id' => $loop])], $warnings);

    expect($blocks)->toBe([])
        ->and($warnings)->toBe([
            'Elementor template 999 was not found; its content is missing.',
            sprintf('Elementor template %d includes itself; the repetition was left out.', $loop),
        ]);
});

it('returns nothing for data it cannot read', function () {
    expect(convertElementor('not json', $warnings))->toBeNull()
        ->and(convertElementor('{"id":"a"}'))->toBeNull()
        ->and(convertElementor('[]'))->toBe([]);
});

it('skips elements of the wrong shape', function () {
    $blocks = convertElementor([
        'text',
        ['elType' => 'widget'],
        ['elType' => 'widget', 'widgetType' => 'heading', 'settings' => 'broken'],
        ['elType' => 'container', 'elements' => 'broken'],
        ['elType' => 'mystery', 'elements' => [widget('heading', ['title' => 'Kept'])]],
        widget('heading', ['title' => ['not', 'a', 'string']]),
    ]);

    expect(blockShapes($blocks))->toBe([
        ['name' => 'core/heading', 'attrs' => ['level' => 2], 'html' => '<h2 class="wp-block-heading">Kept</h2>'],
    ]);
});

it('handles the edge cases of headings, images and spacers', function () {
    $blocks = convertElementor([
        widget('heading', ['title' => 2026, 'header_size' => 'h7']),
        widget('heading', ['title' => '  Spaced  ']),
        widget('image', ['image' => ['url' => 'https://old.example.com/wp-content/uploads/x.png', 'id' => 0], 'caption_source' => 'attachment', 'caption' => 'Ignored']),
        widget('spacer', ['space' => ['unit' => 'banana', 'size' => '40.0']]),
    ]);

    expect(blockShapes($blocks))->toBe([
        ['name' => 'core/paragraph', 'html' => '<p>2026</p>'],
        ['name' => 'core/heading', 'attrs' => ['level' => 2], 'html' => '<h2 class="wp-block-heading">Spaced</h2>'],
        ['name' => 'core/image', 'attrs' => ['sizeSlug' => 'large'], 'html' => '<figure class="wp-block-image size-large"><img src="https://old.example.com/wp-content/uploads/x.png" alt=""/></figure>'],
        ['name' => 'core/spacer', 'attrs' => ['height' => '40px'], 'html' => '<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>'],
    ]);
});

it('gives columns without a size no width', function () {
    $blocks = convertElementor([['id' => 's', 'elType' => 'section', 'settings' => [], 'elements' => [
        ['id' => 'a', 'elType' => 'column', 'settings' => ['_inline_size' => null], 'elements' => [widget('heading', ['title' => 'A'])]],
        ['id' => 'b', 'elType' => 'column', 'settings' => ['_inline_size' => 0, '_column_size' => 0], 'elements' => []],
        ['id' => 'c', 'elType' => 'column', 'settings' => ['_inline_size' => 33.3333333], 'elements' => []],
    ]]]);

    expect(array_map(fn (Block $column): array => [$column->attrs, $column->innerHtml], $blocks[0]->innerBlocks))->toBe([
        [[], '<div class="wp-block-column"></div>'],
        [[], '<div class="wp-block-column"></div>'],
        [['width' => '33.333%'], '<div class="wp-block-column" style="flex-basis:33.333%"></div>'],
    ]);
});

it('converts other videos and keeps external files without an ID', function () {
    $blocks = convertElementor([
        widget('video', ['video_type' => 'videopress', 'videopress_url' => 'https://videopress.com/v/x']),
        widget('video', ['video_type' => 'hosted', 'insert_url' => 'yes', 'external_url' => ['url' => 'https://cdn.example.net/a.mp4', 'id' => 9]]),
        widget('video', ['video_type' => 'hosted', 'hosted_url' => ['url' => '']]),
    ]);

    expect(blockShapes($blocks))->toBe([
        ['name' => 'elementor/video', 'attrs' => ['video_type' => 'videopress', 'videopress_url' => 'https://videopress.com/v/x']],
        ['name' => 'core/video', 'html' => '<figure class="wp-block-video"><video controls src="https://cdn.example.net/a.mp4"></video></figure>'],
    ]);
});

it('leaves out lists, galleries, boxes, testimonials and tabs without content', function () {
    $blocks = convertElementor([
        widget('icon-list', ['icon_list' => ['oops', ['text' => '']]]),
        widget('image-gallery', ['wp_gallery' => [['id' => 5, 'url' => ''], 'oops']]),
        widget('icon-box', ['title_text' => '', 'description_text' => '']),
        widget('testimonial', ['testimonial_content' => '', 'testimonial_name' => '']),
        widget('toggle', ['tabs' => [['tab_title' => 'Only a title', 'tab_content' => '']]]),
        widget('testimonial', ['testimonial_content' => 'No name']),
    ]);

    expect(blockShapes($blocks))->toBe([
        ['name' => 'core/details', 'html' => '<details class="wp-block-details"><summary>Only a title</summary></details>'],
        ['name' => 'core/quote', 'html' => '<blockquote class="wp-block-quote"><p>No name</p></blockquote>'],
    ]);
});

it('expands a template each time it is used, but only from the library', function () {
    $template = $this->source()->post(['post_type' => 'elementor_library']);
    $this->source()->postMeta($template, '_elementor_data', json_encode([widget('heading', ['title' => 'Again'])]));
    $page = $this->source()->page();
    $this->source()->postMeta($page, '_elementor_data', json_encode([widget('heading', ['title' => 'A page'])]));

    $blocks = convertElementor([
        widget('global', [], ['templateID' => $template]),
        widget('global', [], ['templateID' => $template]),
        widget('template', ['template_id' => $page]),
    ], $warnings);

    expect(array_map(fn (Block $block): string => $block->innerHtml, $blocks))->toBe([
        '<h2 class="wp-block-heading">Again</h2>',
        '<h2 class="wp-block-heading">Again</h2>',
    ])->and($warnings)->toBe([sprintf('Elementor template %d was not found; its content is missing.', $page)]);
});
