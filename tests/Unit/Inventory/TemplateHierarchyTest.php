<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Inventory\Theme\Template;
use ItsJustVita\WpMigrator\Inventory\Theme\TemplateHierarchy;

function classify(string $path, string $contents = '', array $customTemplates = []): ?Template
{
    return (new TemplateHierarchy(['post', 'page', 'event', 'press-release'], ['category', 'post_tag', 'genre']))->classify($path, $contents, $customTemplates);
}

it('places theme files in the template hierarchy', function (string $path, string $kind, ?string $subject, ?string $detail = null) {
    $template = classify($path);

    expect($template?->kind)->toBe($kind)
        ->and($template?->subject)->toBe($subject)
        ->and($template?->detail)->toBe($detail)
        ->and($template?->path)->toBe($path);
})->with([
    ['front-page.php', 'front-page', null],
    ['home.php', 'home', null],
    ['index.php', 'index', null],
    ['singular.php', 'singular', null],
    ['single.php', 'single', 'post'],
    ['single-event.php', 'single', 'event'],
    ['single-press-release.php', 'single', 'press-release'],
    ['single-event-summer-concert.php', 'single', 'event', 'summer-concert'],
    ['single-product.php', 'single', 'product'],
    ['page.php', 'page', null],
    ['page-contact.php', 'page', null, 'contact'],
    ['page-12.php', 'page', null, '12'],
    ['archive.php', 'archive', null],
    ['archive-event.php', 'archive', 'event'],
    ['category.php', 'term', 'category'],
    ['category-news.php', 'term', 'category', 'news'],
    ['tag.php', 'term', 'post_tag'],
    ['tag-php.php', 'term', 'post_tag', 'php'],
    ['taxonomy.php', 'term', null],
    ['taxonomy-genre.php', 'term', 'genre'],
    ['taxonomy-genre-rock.php', 'term', 'genre', 'rock'],
    ['author.php', 'author', null],
    ['author-alice.php', 'author', null, 'alice'],
    ['date.php', 'date', null],
    ['search.php', 'search', null],
    ['404.php', 'not-found', null],
    ['attachment.php', 'attachment', null],
    ['image.php', 'attachment', null, 'image'],
    ['header.php', 'partial', 'header'],
    ['header-shop.php', 'partial', 'header', 'shop'],
    ['footer.php', 'partial', 'footer'],
    ['sidebar.php', 'partial', 'sidebar'],
    ['comments.php', 'partial', 'comments'],
    ['searchform.php', 'partial', 'searchform'],
    ['template-parts/content-event.php', 'part', 'template-parts/content-event'],
    ['templates/single-event.html', 'single', 'event'],
    ['templates/index.html', 'index', null],
    ['parts/header.html', 'part', 'header'],
]);

it('recognises page templates by their header', function () {
    $template = classify('template-wide.php', "<?php\n/*\n * Template Name: Wide Layout\n * Template Post Type: page, post\n */\n");

    expect($template?->kind)->toBe('custom')
        ->and($template?->subject)->toBe('Wide Layout')
        ->and($template?->postTypes)->toBe(['page', 'post']);
});

it('recognises custom templates of block themes', function () {
    $template = classify('templates/page-wide.html', '', ['page-wide' => ['title' => 'Wide page', 'postTypes' => ['page']]]);

    expect($template?->kind)->toBe('custom')
        ->and($template?->subject)->toBe('Wide page')
        ->and($template?->postTypes)->toBe(['page']);
});

it('ignores files that are not templates', function (string $path) {
    expect(classify($path))->toBeNull();
})->with(['functions.php', 'inc/setup.php', 'style.css', 'theme.json', 'blocks/hero/render.php', 'templates/readme.txt', 'patterns/hero.php']);

it('describes a template for people', function (string $path, string $description) {
    expect(classify($path)?->description())->toBe($description);
})->with([
    ['single-event.php', 'Single event'],
    ['archive-event.php', 'Archive of event'],
    ['page-contact.php', 'Page "contact"'],
    ['taxonomy-genre-rock.php', 'Archive of the genre "rock"'],
    ['category.php', 'Archive of a category'],
    ['404.php', 'Page not found'],
    ['header-shop.php', 'Partial header "shop"'],
]);
