<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\ImportReport;
use ItsJustVita\WpMigrator\Import\ImportRunner;
use ItsJustVita\WpMigrator\Models\Redirect;

function importRedirects(ImportOptions $options = new ImportOptions): ImportReport
{
    return app(ImportRunner::class)->run($options);
}

/**
 * @return array<string, string>
 */
function redirectMap(): array
{
    return Redirect::query()->orderBy('from_path')->pluck('to_path', 'from_path')->all();
}

beforeEach(function () {
    Storage::fake('public');
    $this->uploads = sys_get_temp_dir().'/wp-migrator-uploads-'.uniqid();
    File::ensureDirectoryExists($this->uploads.'/2026/01');
    config()->set('wp-migrator.source.uploads_path', $this->uploads);
    config()->set('wp-migrator.media.download_missing', false);

    $site = $this->site = $this->source();
    $site->option('home', 'https://old.example.com');
    $site->option('permalink_structure', '/%year%/%postname%/');
    $site->option('category_base', 'thema');
    $site->option('rewrite_rules', serialize(['veranstaltungen/([^/]+)/?$' => 'index.php?event=$matches[1]']));

    $this->alice = $site->user(['user_login' => 'alice', 'user_nicename' => 'alice']);
    $site->user(['user_login' => 'reader', 'user_nicename' => 'reader']);
    $this->hello = $site->post(['post_name' => 'hello', 'post_author' => $this->alice, 'post_date' => '2026-03-04 10:00:00']);
    $this->company = $site->page(['post_name' => 'company', 'post_author' => $this->alice]);
    $this->team = $site->page(['post_name' => 'team', 'post_parent' => $this->company, 'post_author' => $this->alice]);
    $this->concert = $site->post(['post_type' => 'event', 'post_name' => 'concert', 'post_author' => $this->alice]);
    $this->news = $site->term('category', 'News');
    $this->tech = $site->term('category', 'Tech', ['parent' => $this->news]);
    $this->php = $site->term('post_tag', 'PHP', ['slug' => 'php']);
    $site->attachTerm($this->hello, $this->tech);

    $this->photo = $site->attachment(['post_name' => 'photo', 'post_parent' => $this->hello]);
    $site->postMeta($this->photo, '_wp_attached_file', '2026/01/photo.jpg');
    file_put_contents($this->uploads.'/2026/01/photo.jpg', 'jpg');
    $this->doc = $site->attachment(['post_name' => 'doc', 'post_mime_type' => 'application/pdf']);
    $site->postMeta($this->doc, '_wp_attached_file', '2026/01/doc.pdf');
    file_put_contents($this->uploads.'/2026/01/doc.pdf', 'pdf');
});

afterEach(function () {
    File::deleteDirectory($this->uploads);
});

it('redirects the old urls of posts, pages and custom post types', function () {
    importRedirects();

    expect(redirectMap())->toMatchArray([
        '/2026/hello' => '/blog/hello',
        '/?p='.$this->hello => '/blog/hello',
        '/?p='.$this->team => '/company/team',
        '/?page_id='.$this->team => '/company/team',
        '/?page_id='.$this->company => '/company',
        '/veranstaltungen/concert' => '/event/concert',
        '/?p='.$this->concert => '/event/concert',
    ])->and(redirectMap())->not->toHaveKey('/company/team')
        ->and(redirectMap())->not->toHaveKey('/company')
        ->and(redirectMap())->not->toHaveKey('/?page_id='.$this->hello);
});

it('redirects category, tag and author archives', function () {
    importRedirects();

    expect(redirectMap())->toMatchArray([
        '/thema/news' => '/blog/category/news',
        '/thema/news/tech' => '/blog/category/news/tech',
        '/?cat='.$this->tech => '/blog/category/news/tech',
        '/tag/php' => '/blog/tag/php',
        '/?tag=php' => '/blog/tag/php',
        '/author/alice' => '/blog/author/alice',
        '/?author='.$this->alice => '/blog/author/alice',
    ])->and(redirectMap())->not->toHaveKey('/author/reader');
});

it('redirects attachment pages to the file', function () {
    importRedirects();

    expect(redirectMap())->toMatchArray([
        '/2026/hello/photo' => '/storage/wp-uploads/2026/01/photo.jpg',
        '/?attachment_id='.$this->photo => '/storage/wp-uploads/2026/01/photo.jpg',
        '/doc' => '/storage/wp-uploads/2026/01/doc.pdf',
        '/?attachment_id='.$this->doc => '/storage/wp-uploads/2026/01/doc.pdf',
    ]);
});

it('only writes query urls for a site with plain permalinks', function () {
    $this->site->option('permalink_structure', '');

    importRedirects();

    $froms = array_keys(redirectMap());

    expect($froms)->each->toStartWith('/?')
        ->and($froms)->toContain('/?p='.$this->hello, '/?cat='.$this->news, '/?author='.$this->alice);
});

it('leaves out archives without a pattern and reports them', function () {
    config()->set('wp-migrator.urls.term_patterns', ['category' => null]);
    config()->set('wp-migrator.urls.author_pattern', null);

    $report = importRedirects(new ImportOptions(only: ['redirects']));

    expect(redirectMap())->not->toHaveKey('/thema/news')
        ->and(redirectMap())->not->toHaveKey('/tag/php')
        ->and(redirectMap())->not->toHaveKey('/author/alice')
        ->and(array_column($report->skippedItems(), 'reason'))->toContain('No new URL for category news', 'No new URL for author alice');
});

it('is idempotent and keeps the hit counts', function () {
    $first = importRedirects();
    Redirect::query()->where('from_path', '/2026/hello')->update(['hits' => 5]);

    $second = importRedirects();

    expect($second->counts()['redirects']['created'])->toBe(0)
        ->and($second->counts()['redirects']['unchanged'])->toBe($first->counts()['redirects']['created'])
        ->and(Redirect::query()->where('from_path', '/2026/hello')->value('hits'))->toBe(5);
});

it('updates a redirect whose target changed', function () {
    importRedirects();
    Redirect::query()->where('from_path', '/2026/hello')->update(['hits' => 5]);
    config()->set('wp-migrator.urls.patterns.post', '/news/{slug}');

    $report = importRedirects(new ImportOptions(only: ['redirects']));

    expect(Redirect::query()->where('from_path', '/2026/hello')->value('to_path'))->toBe('/news/hello')
        ->and(Redirect::query()->where('from_path', '/2026/hello')->value('hits'))->toBe(5)
        ->and($report->counts()['redirects']['updated'])->toBeGreaterThan(0);
});

it('stores old paths lowercase and decoded', function () {
    $this->site->post(['post_name' => 'gr%c3%bc%c3%9fe', 'post_date' => '2026-03-04 10:00:00']);

    importRedirects(new ImportOptions(only: ['redirects']));

    expect(redirectMap())->toHaveKey('/2026/grüße');
});

it('writes nothing in a dry run', function () {
    importRedirects(new ImportOptions(dryRun: true));

    expect(Redirect::count())->toBe(0);
});
