<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Models\SeoMeta;
use ItsJustVita\WpMigrator\Seo\SeoImporter;
use ItsJustVita\WpMigrator\Source\Readers\PostReader;

function syncSeo(int $sourceId, ?ImportContext $context = null): ?SeoMeta
{
    $source = app(PostReader::class)->byIds([$sourceId])[0];
    $post = Post::firstOrCreate(['slug' => 'target-'.$sourceId], ['type' => 'post', 'status' => 'publish', 'title' => $source->title]);

    app(SeoImporter::class)->sync($post, $source, $context ?? importContext('posts'));

    return SeoMeta::query()->whereMorphedTo('seoable', $post)->first();
}

beforeEach(function () {
    Storage::fake('public');
    $this->site = $this->source();
    $this->site->option('home', 'https://old.example.com');
    $this->site->option('permalink_structure', '/%postname%/');
    $this->site->option('blogname', 'Acme');
    $this->site->option('blogdescription', 'We make things');
    $this->post = $this->site->post(['post_title' => 'Hello World', 'post_name' => 'hello-world', 'post_excerpt' => 'Short summary.']);
});

it('imports the yoast fields', function () {
    $context = importContext('posts');
    $image = Media::create(['disk' => 'public', 'path' => 'og.jpg']);
    $context->map->record(SourceType::Post, 77, $image, 'x');
    $this->site->postMeta($this->post, '_yoast_wpseo_title', '%%title%% %%sep%% %%sitename%%');
    $this->site->postMeta($this->post, '_yoast_wpseo_metadesc', 'About %%title%%.');
    $this->site->postMeta($this->post, '_yoast_wpseo_canonical', 'https://old.example.com/hello-world/');
    $this->site->postMeta($this->post, '_yoast_wpseo_meta-robots-noindex', '1');
    $this->site->postMeta($this->post, '_yoast_wpseo_meta-robots-nofollow', '1');
    $this->site->postMeta($this->post, '_yoast_wpseo_meta-robots-adv', 'noarchive,nosnippet');
    $this->site->postMeta($this->post, '_yoast_wpseo_opengraph-title', 'Share %%title%%');
    $this->site->postMeta($this->post, '_yoast_wpseo_opengraph-description', 'Shared text');
    $this->site->postMeta($this->post, '_yoast_wpseo_opengraph-image', 'https://old.example.com/wp-content/uploads/og.jpg');
    $this->site->postMeta($this->post, '_yoast_wpseo_opengraph-image-id', '77');

    $seo = syncSeo($this->post, $context);

    expect($seo?->title)->toBe('Hello World - Acme')
        ->and($seo?->description)->toBe('About Hello World.')
        ->and($seo?->canonical)->toBe('/blog/hello-world')
        ->and($seo?->robots)->toBe('noindex, nofollow, noarchive, nosnippet')
        ->and($seo?->og_title)->toBe('Share Hello World')
        ->and($seo?->og_description)->toBe('Shared text')
        ->and($seo?->og_image_id)->toBe($image->id)
        ->and($context->report->unresolvedReferences())->toBe([]);
});

it('uses the yoast separator setting', function (string $setting, string $separator) {
    $this->site->option('wpseo_titles', serialize(['separator' => $setting]));
    $this->site->postMeta($this->post, '_yoast_wpseo_title', '%%title%% %%sep%% %%sitename%%');

    expect(syncSeo($this->post)?->title)->toBe('Hello World '.$separator.' Acme');
})->with([
    ['sc-dash', '-'],
    ['sc-ndash', '–'],
    ['sc-mdash', '—'],
    ['sc-pipe', '|'],
    ['sc-bull', '•'],
    ['sc-middot', '·'],
    ['sc-raquo', '»'],
]);

it('reads an explicit index setting from yoast', function () {
    $this->site->postMeta($this->post, '_yoast_wpseo_meta-robots-noindex', '2');

    expect(syncSeo($this->post)?->robots)->toBe('index');
});

it('imports the rank math fields', function () {
    $this->site->option('rank-math-options-titles', serialize(['title_separator' => '|']));
    $this->site->postMeta($this->post, 'rank_math_title', '%title% %sep% %sitename%');
    $this->site->postMeta($this->post, 'rank_math_description', '%excerpt%');
    $this->site->postMeta($this->post, 'rank_math_robots', serialize(['noindex', 'nofollow']));
    $this->site->postMeta($this->post, 'rank_math_canonical_url', 'https://example.net/elsewhere');
    $this->site->postMeta($this->post, 'rank_math_facebook_title', 'FB %title%');
    $this->site->postMeta($this->post, 'rank_math_facebook_description', 'FB text');
    $this->site->postMeta($this->post, 'rank_math_facebook_image', 'https://cdn.example.net/og.jpg');

    $seo = syncSeo($this->post);

    expect($seo?->title)->toBe('Hello World | Acme')
        ->and($seo?->description)->toBe('Short summary.')
        ->and($seo?->robots)->toBe('noindex, nofollow')
        ->and($seo?->canonical)->toBe('https://example.net/elsewhere')
        ->and($seo?->og_title)->toBe('FB Hello World')
        ->and($seo?->og_description)->toBe('FB text')
        ->and($seo?->og_image_url)->toBe('https://cdn.example.net/og.jpg')
        ->and($seo?->og_image_id)->toBeNull();
});

it('resolves the common variables', function (string $template, string $expected) {
    $this->site->option('date_format', 'd.m.Y');
    $author = $this->site->user(['display_name' => 'Ada Lovelace']);
    $parent = $this->site->page(['post_title' => 'Company']);
    $news = $this->site->term('category', 'News');
    $tech = $this->site->term('category', 'Tech');
    $post = $this->site->post([
        'post_title' => 'Hello World',
        'post_excerpt' => '',
        'post_content' => '<p>First <strong>words</strong> of the content.</p> [gallery]',
        'post_author' => $author,
        'post_parent' => $parent,
        'post_date' => '2026-03-04 10:00:00',
        'post_date_gmt' => '2026-03-04 10:00:00',
    ]);
    $this->site->attachTerm($post, $news);
    $this->site->attachTerm($post, $tech);
    $this->site->postMeta($post, '_yoast_wpseo_primary_category', (string) $tech);
    $this->site->postMeta($post, '_yoast_wpseo_title', $template);

    expect(syncSeo($post)?->title)->toBe($expected);
})->with([
    'excerpt from content' => ['%%excerpt%%', 'First words of the content.'],
    'excerpt only' => ['[%%excerpt_only%%]', '[]'],
    'category' => ['%%category%%', 'News'],
    'primary category' => ['%%primary_category%%', 'Tech'],
    'date' => ['%%date%%', '04.03.2026'],
    'author' => ['%%name%%', 'Ada Lovelace'],
    'parent' => ['%%parent_title%%', 'Company'],
    'site description' => ['%%sitedesc%%', 'We make things'],
    'paging' => ['%%title%% %%page%% %%sep%% %%sitename%%', 'Hello World - Acme'],
]);

it('removes variables it cannot resolve and reports them', function () {
    $context = importContext('posts');
    $this->site->postMeta($this->post, '_yoast_wpseo_title', '%%title%% %%currentyear%% %%cf_custom%%');

    expect(syncSeo($this->post, $context)?->title)->toBe('Hello World')
        ->and(array_column($context->report->unresolvedReferences(), 'reference'))->toBe(['%%currentyear%%', '%%cf_custom%%'])
        ->and(array_column($context->report->unresolvedReferences(), 'kind'))->toBe(['seo variable', 'seo variable'])
        ->and($context->rowIncomplete())->toBeFalse();
});

it('removes separators that are left over around removed variables', function (string $template, string $expected) {
    $this->site->postMeta($this->post, '_yoast_wpseo_title', $template);

    expect(syncSeo($this->post)?->title)->toBe($expected);
})->with([
    'at the end' => ['%%title%% %%sep%% %%currentyear%%', 'Hello World'],
    'at the start' => ['%%currentyear%% %%sep%% %%title%%', 'Hello World'],
    'twice in a row' => ['%%title%% %%sep%% %%currentyear%% %%sep%% %%sitename%%', 'Hello World - Acme'],
    'kept between values' => ['%%title%% %%sep%% %%sitename%%', 'Hello World - Acme'],
    'written by hand' => ['%%title%% - %%currentyear%%', 'Hello World'],
]);

it('prefers the plugin that is active', function () {
    $this->site->postMeta($this->post, '_yoast_wpseo_title', 'Yoast title');
    $this->site->postMeta($this->post, 'rank_math_title', 'Rank Math title');
    $this->site->postMeta($this->post, 'rank_math_description', 'Rank Math description');

    expect(syncSeo($this->post)?->title)->toBe('Yoast title')
        ->and(SeoMeta::query()->sole()->description)->toBe('Rank Math description');

    $this->site->option('active_plugins', serialize(['seo-by-rank-math/rank-math.php']));
    app()->forgetInstance(SeoImporter::class);

    expect(syncSeo($this->post)?->title)->toBe('Rank Math title');
});

it('stores nothing for a post without seo data and removes old data', function () {
    $this->site->postMeta($this->post, '_yoast_wpseo_title', 'Old');
    syncSeo($this->post);
    $this->site->table('postmeta')->where('meta_key', '_yoast_wpseo_title')->delete();

    expect(syncSeo($this->post))->toBeNull()
        ->and(SeoMeta::count())->toBe(0);
});

it('updates the existing row instead of adding one', function () {
    $this->site->postMeta($this->post, '_yoast_wpseo_title', 'First');
    syncSeo($this->post);
    $this->site->table('postmeta')->where('meta_key', '_yoast_wpseo_title')->update(['meta_value' => 'Second']);

    expect(syncSeo($this->post)?->title)->toBe('Second')
        ->and(SeoMeta::count())->toBe(1);
});

it('reports an open graph image it cannot resolve and keeps its url', function () {
    $context = importContext('posts');
    Media::create(['disk' => 'public', 'path' => 'wp-uploads/og.jpg']);
    $this->site->postMeta($this->post, '_yoast_wpseo_opengraph-image', 'https://old.example.com/wp-content/uploads/og.jpg');
    $this->site->postMeta($this->post, '_yoast_wpseo_opengraph-image-id', '99');

    $seo = syncSeo($this->post, $context);

    expect($seo?->og_image_id)->toBeNull()
        ->and($seo?->og_image_url)->toBe('/storage/wp-uploads/og.jpg')
        ->and($context->report->unresolvedReferences()[0]['kind'])->toBe('seo image')
        ->and($context->rowIncomplete())->toBeTrue();
});

it('can be turned off', function () {
    config()->set('wp-migrator.seo.enabled', false);
    $this->site->postMeta($this->post, '_yoast_wpseo_title', 'Title');

    expect(syncSeo($this->post))->toBeNull();
});
