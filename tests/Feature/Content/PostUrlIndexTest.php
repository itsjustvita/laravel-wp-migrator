<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Content\Urls\PostUrlIndex;

function urlIndex(): PostUrlIndex
{
    return app(PostUrlIndex::class);
}

it('finds posts by their computed permalink', function () {
    $site = $this->source();
    $site->option('permalink_structure', '/%year%/%monthnum%/%postname%/');
    $id = $site->post(['post_name' => 'hello', 'post_date' => '2026-03-04 10:00:00']);

    $index = urlIndex();

    expect($index->find('/2026/03/hello')?->id)->toBe($id)
        ->and($index->find('/2026/03/hello/')?->id)->toBe($id)
        ->and($index->find('2026/03/hello')?->id)->toBe($id)
        ->and($index->find('/2026/03/HELLO')?->id)->toBe($id)
        ->and($index->find('/2026/04/hello/more'))->toBeNull()
        ->and($index->find('/nothing/here'))->toBeNull();
});

it('reads post dates in the local post_date column', function () {
    $site = $this->source();
    $site->option('permalink_structure', '/%year%/%postname%/');
    $id = $site->post(['post_name' => 'late', 'post_date' => '2026-01-01 00:30:00', 'post_date_gmt' => '2025-12-31 23:30:00']);

    expect(urlIndex()->find('/2026/late')?->id)->toBe($id);
});

it('finds pages through their hierarchy', function () {
    $site = $this->source();
    $site->option('permalink_structure', '/%postname%/');
    $parent = $site->page(['post_name' => 'company']);
    $child = $site->page(['post_name' => 'team', 'post_parent' => $parent]);
    $other = $site->page(['post_name' => 'team', 'post_parent' => 0]);

    $reference = urlIndex()->find('/company/team');

    expect($reference?->id)->toBe($child)
        ->and($reference?->path)->toBe('company/team')
        ->and($reference?->slug)->toBe('team')
        ->and(urlIndex()->find('/team')?->id)->toBe($other);
});

it('survives parent cycles', function () {
    $site = $this->source();
    $site->option('permalink_structure', '/%postname%/');
    $a = $site->page(['post_name' => 'a']);
    $b = $site->page(['post_name' => 'b', 'post_parent' => $a]);
    $site->table('posts')->where('ID', $a)->update(['post_parent' => $b]);

    expect(urlIndex()->findById($a)?->path)->toBe('b/a')
        ->and(urlIndex()->findById($b)?->path)->toBe('a/b');
});

it('finds non-ascii slugs, stored encoded, by encoded and decoded paths', function () {
    $site = $this->source();
    $site->option('permalink_structure', '/%postname%/');
    $id = $site->post(['post_name' => 'gr%c3%bc%c3%9fe']);

    expect(urlIndex()->find('/gr%C3%BC%C3%9Fe/')?->id)->toBe($id)
        ->and(urlIndex()->find('/grüße')?->id)->toBe($id);
});

it('detects custom post type slugs from the rewrite rules', function () {
    $site = $this->source();
    $site->option('permalink_structure', '/%postname%/');
    $site->option('rewrite_rules', serialize([
        'veranstaltungen/[^/]+/attachment/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
        'veranstaltungen/([^/]+)(?:/([0-9]+))?/?$' => 'index.php?event=$matches[1]&page=$matches[2]',
    ]));
    $id = $site->post(['post_type' => 'event', 'post_name' => 'concert']);

    expect(urlIndex()->find('/veranstaltungen/concert')?->id)->toBe($id);
});

it('prefers configured post type slugs over the rewrite rules', function () {
    config()->set('wp-migrator.urls.post_type_slugs', ['event' => 'termine']);
    $site = $this->source();
    $site->option('permalink_structure', '/%postname%/');
    $site->option('rewrite_rules', serialize(['veranstaltungen/([^/]+)/?$' => 'index.php?event=$matches[1]']));
    $id = $site->post(['post_type' => 'event', 'post_name' => 'concert']);

    expect(urlIndex()->find('/termine/concert')?->id)->toBe($id);
});

it('ignores malformed rewrite rules', function () {
    $site = $this->source();
    $site->option('permalink_structure', '/%postname%/');
    $site->option('rewrite_rules', 'a:1:{broken');
    $id = $site->post(['post_type' => 'event', 'post_name' => 'concert']);

    expect(urlIndex()->find('/event/concert')?->id)->toBe($id);
});

it('fills the category tag with the lowest category id and its parents', function () {
    $site = $this->source();
    $site->option('permalink_structure', '/%category%/%postname%/');
    $news = $site->term('category', 'News');
    $tech = $site->term('category', 'Tech', ['parent' => $news]);
    $zeta = $site->term('category', 'Zeta');
    $id = $site->post(['post_name' => 'hello']);
    $site->attachTerm($id, $zeta);
    $site->attachTerm($id, $tech);

    expect(urlIndex()->find('/news/tech/hello')?->id)->toBe($id)
        ->and(urlIndex()->findById($id)?->category)->toBe('news/tech');
});

it('falls back to the default category for the category tag', function () {
    $site = $this->source();
    $site->option('permalink_structure', '/%category%/%postname%/');
    $default = $site->term('category', 'Allgemein');
    $site->option('default_category', (string) $default);
    $id = $site->post(['post_name' => 'hello']);

    expect(urlIndex()->find('/allgemein/hello')?->id)->toBe($id);
});

it('fills the author tag with the nicename', function () {
    $site = $this->source();
    $site->option('permalink_structure', '/%author%/%postname%/');
    $alice = $site->user(['user_login' => 'alice', 'user_nicename' => 'alice-e']);
    $id = $site->post(['post_name' => 'hello', 'post_author' => $alice]);

    expect(urlIndex()->find('/alice-e/hello')?->id)->toBe($id);
});

it('finds posts by id', function () {
    $site = $this->source();
    $id = $site->post(['post_name' => 'hello']);

    expect(urlIndex()->findById($id)?->slug)->toBe('hello')
        ->and(urlIndex()->findById(9999))->toBeNull();
});

it('leaves out what is not content', function () {
    $site = $this->source();
    $site->option('permalink_structure', '/%postname%/');
    $attachment = $site->attachment(['post_name' => 'photo']);
    $revision = $site->post(['post_type' => 'revision', 'post_name' => 'old']);
    $trashed = $site->post(['post_status' => 'trash', 'post_name' => 'gone']);

    expect(urlIndex()->findById($attachment))->toBeNull()
        ->and(urlIndex()->findById($revision))->toBeNull()
        ->and(urlIndex()->findById($trashed))->toBeNull()
        ->and(urlIndex()->find('/gone'))->toBeNull();
});

it('falls back to a unique slug for urls from an older permalink structure', function () {
    $site = $this->source();
    $site->option('permalink_structure', '/%postname%/');
    $id = $site->post(['post_name' => 'hello']);

    expect(urlIndex()->find('/2019/05/hello')?->id)->toBe($id);
});

it('does not guess when several posts share the slug', function () {
    $site = $this->source();
    $site->option('permalink_structure', '/%postname%/');
    $site->post(['post_name' => 'same']);
    $site->post(['post_type' => 'event', 'post_name' => 'same']);

    expect(urlIndex()->find('/2019/05/same'))->toBeNull();
});

it('does not mistake archives and system paths for a post with that slug', function (string $path) {
    $site = $this->source();
    $site->option('permalink_structure', '/%postname%/');
    $site->option('category_base', 'thema');
    $site->post(['post_name' => 'news']);

    expect(urlIndex()->find($path))->toBeNull();
})->with([
    '/category/news',
    '/thema/news',
    '/tag/news',
    '/author/news',
    '/wp-content/news',
    '/feed/news',
]);

it('finds posts by slug when the site uses plain permalinks', function () {
    $site = $this->source();
    $id = $site->post(['post_name' => 'hello']);

    expect(urlIndex()->find('/hello')?->id)->toBe($id);
});
