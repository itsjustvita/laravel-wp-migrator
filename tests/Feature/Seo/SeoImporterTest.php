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

    expect(syncSeo($this->post)?->robots)->toBe('index, max-image-preview:large');
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

it('recognises the variables of Rank Math that take an argument', function () {
    $this->site->option('date_format', 'd.m.Y');
    $this->site->table('posts')->where('ID', $this->post)->update(['post_date' => '2026-03-04 10:00:00', 'post_date_gmt' => '2026-03-04 10:00:00']);
    $this->site->postMeta($this->post, 'rank_math_title', '%title% %customfield(price)% %sep% %date(F Y)% (50% off)');
    $context = importContext('posts');

    expect(syncSeo($this->post, $context)?->title)->toBe('Hello World - 04.03.2026 (50% off)')
        ->and(array_column($context->report->unresolvedReferences(), 'reference'))->toBe(['%customfield(price)%']);
});

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
    config()->set('wp-migrator.seo.site_defaults', false);
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

/**
 * @param  array<string, mixed>  $settings
 */
function rankMathSettings(mixed $site, array $settings, bool $active = true): void
{
    $site->option('rank-math-options-titles', serialize($settings));

    if ($active) {
        $site->option('active_plugins', serialize(['seo-by-rank-math/rank-math.php']));
    }
}

it('gives a post without a title of its own the title WP printed', function () {
    $seo = syncSeo($this->post);

    expect($seo?->title)->toBe('Hello World – Acme')
        ->and($seo?->description)->toBeNull()
        // WP lets search engines show large image previews on every page.
        ->and($seo?->robots)->toBe('max-image-preview:large')
        ->and($seo?->og_title)->toBeNull();
});

it('gives the page on the front page the site name and the tagline, as WP did', function (string $tagline, string $title) {
    $this->site->option('blogdescription', $tagline);
    $home = $this->site->page(['post_title' => 'Welcome']);
    $about = $this->site->page(['post_title' => 'About']);
    $this->site->option('show_on_front', 'page')->option('page_on_front', (string) $home);

    expect(syncSeo($home)?->title)->toBe($title)
        ->and(syncSeo($about)?->title)->toBe('About – Acme');
})->with([
    'with a tagline' => ['We make things', 'Acme – We make things'],
    'without one' => ['', 'Acme'],
]);

it('treats the page on the front page like any page while the front page shows the latest posts', function () {
    $home = $this->site->page(['post_title' => 'Welcome']);
    $this->site->option('show_on_front', 'posts')->option('page_on_front', (string) $home);

    expect(syncSeo($home)?->title)->toBe('Welcome – Acme');
});

it('writes the site name without the entities WP stores it with', function () {
    $this->site->option('blogname', 'Tom &amp; Jerry&#039;s');
    $this->site->postMeta($this->post, '_yoast_wpseo_metadesc', 'By %%sitename%%');

    $seo = syncSeo($this->post);

    expect($seo?->title)->toBe('Hello World – Tom & Jerry\'s')
        ->and($seo?->description)->toBe('By Tom & Jerry\'s');
});

it('applies the templates of Rank Math for the post type', function () {
    rankMathSettings($this->site, [
        'title_separator' => '|',
        'pt_post_title' => '%title% %sep% %sitename%',
        'pt_post_description' => '%excerpt%',
        'pt_page_title' => '%sitename%: %title% %page%',
        'pt_page_description' => 'About %title%',
    ]);
    $page = $this->site->page(['post_title' => 'Company']);
    $summed = $this->site->page(['post_title' => 'Summed up', 'post_excerpt' => 'In short.']);
    $event = $this->site->post(['post_title' => 'Gig', 'post_type' => 'event']);

    $post = syncSeo($this->post);

    expect($post?->title)->toBe('Hello World | Acme')
        ->and($post?->description)->toBe('Short summary.')
        ->and($post?->robots)->toBe('max-image-preview:large')
        ->and(syncSeo($page)?->title)->toBe('Acme: Company')
        ->and(syncSeo($page)?->description)->toBe('About Company')
        // The excerpt comes before the template, whatever the template says.
        ->and(syncSeo($summed)?->description)->toBe('In short.')
        // A post type the settings do not name gets the title Rank Math falls back to, and no description.
        ->and(syncSeo($event)?->title)->toBe('Gig | Acme')
        ->and(syncSeo($event)?->description)->toBeNull();
});

it('describes a post without an excerpt like Rank Math: by its first paragraph, cut at a word', function () {
    rankMathSettings($this->site, ['pt_post_description' => '%excerpt%']);
    $words = implode(' ', array_map(fn (int $i): string => 'word'.$i, range(1, 60)));
    $long = $this->site->post(['post_title' => 'Long', 'post_excerpt' => '', 'post_content' => '<p>'.$words.'</p>']);
    $classic = $this->site->post(['post_title' => 'Classic', 'post_excerpt' => '', 'post_content' => "First paragraph, with [gallery] a shortcode &amp; an entity.\n\nSecond paragraph about llamas."]);
    $blocks = $this->site->post(['post_title' => 'Blocks', 'post_excerpt' => '', 'post_content' => '<!-- wp:heading --><h2>Heading</h2><!-- /wp:heading -->'
        ."\n\n".'<!-- wp:paragraph --><p>Intro <strong>text</strong> here.</p><!-- /wp:paragraph -->'
        ."\n\n".'<!-- wp:paragraph --><p>More about Llamas.</p><!-- /wp:paragraph -->']);
    $keyword = $this->site->post(['post_title' => 'Keyword', 'post_excerpt' => '', 'post_content' => "First paragraph.\n\nSecond paragraph about Llamas.\n\nThird, llamas again."]);
    $this->site->postMeta($keyword, 'rank_math_focus_keyword', 'llamas, alpacas');
    $excerpt = $this->site->post(['post_title' => 'Short', 'post_excerpt' => '<b>An excerpt</b> of its own.', 'post_content' => $words]);
    $empty = $this->site->post(['post_title' => 'Empty', 'post_excerpt' => '', 'post_content' => '']);

    // Rank Math cuts at 160 characters and then back to the last word: word24 ends at 158.
    expect(syncSeo($long)?->description)->toBe(implode(' ', array_map(fn (int $i): string => 'word'.$i, range(1, 24))))
        ->and(syncSeo($classic)?->description)->toBe('First paragraph, with a shortcode & an entity.')
        ->and(syncSeo($blocks)?->description)->toBe('Intro text here.')
        ->and(syncSeo($keyword)?->description)->toBe('Second paragraph about Llamas.')
        // An excerpt comes before the template.
        ->and(syncSeo($excerpt)?->description)->toBe('An excerpt of its own.')
        ->and(syncSeo($empty)?->description)->toBeNull();
});

it('applies the robots setting of Rank Math for the post type', function (mixed $custom, mixed $robots, ?string $expected) {
    rankMathSettings($this->site, ['pt_post_custom_robots' => $custom, 'pt_post_robots' => $robots]);

    expect(syncSeo($this->post)?->robots)->toBe($expected);
})->with([
    'switched on' => ['on', ['noindex', 'nofollow'], 'noindex, nofollow'],
    'switched off' => ['off', ['noindex'], 'max-image-preview:large'],
    'not set' => [null, ['noindex'], 'max-image-preview:large'],
    'plain index' => ['on', ['index'], 'max-image-preview:large'],
    'nothing chosen' => ['on', [], 'max-image-preview:large'],
]);

it('prefers the robots of the post over those of its post type', function () {
    rankMathSettings($this->site, ['pt_post_custom_robots' => 'on', 'pt_post_robots' => ['noindex']]);
    $this->site->postMeta($this->post, 'rank_math_robots', serialize(['index', 'noarchive']));

    expect(syncSeo($this->post)?->robots)->toBe('index, noarchive, max-image-preview:large');
});

it('applies the templates and the noindex setting of Yoast for the post type', function () {
    $this->site->option('wpseo_titles', serialize([
        'separator' => 'sc-pipe',
        'title-post' => '%%sitename%% %%sep%% %%title%% %%page%%',
        'metadesc-post' => 'Read %%title%%.',
        'noindex-post' => true,
        'title-page' => '',
        'metadesc-page' => '',
        'noindex-page' => false,
    ]));
    $page = $this->site->page(['post_title' => 'Company']);

    $post = syncSeo($this->post);

    expect($post?->title)->toBe('Acme | Hello World')
        ->and($post?->description)->toBe('Read Hello World.')
        ->and($post?->robots)->toBe('noindex')
        // An empty template is the one Yoast falls back to.
        ->and(syncSeo($page)?->title)->toBe('Company | Acme')
        ->and(syncSeo($page)?->description)->toBeNull()
        ->and(syncSeo($page)?->robots)->toBe('max-image-preview:large');
});

it('combines the noindex of the post type with what the post sets in Yoast', function (array $meta, ?string $expected) {
    $this->site->option('wpseo_titles', serialize(['noindex-post' => true]));

    foreach ($meta as $key => $value) {
        $this->site->postMeta($this->post, $key, $value);
    }

    expect(syncSeo($this->post)?->robots)->toBe($expected);
})->with([
    'nothing' => [[], 'noindex'],
    'the default' => [['_yoast_wpseo_meta-robots-noindex' => '0'], 'noindex'],
    'index' => [['_yoast_wpseo_meta-robots-noindex' => '2'], 'index, max-image-preview:large'],
    'nofollow only' => [['_yoast_wpseo_meta-robots-nofollow' => '1'], 'noindex, nofollow'],
]);

it('keeps what the post sets itself and fills the rest from the templates', function () {
    rankMathSettings($this->site, ['pt_post_title' => '%title% %sep% %sitename%', 'pt_post_description' => 'Template text']);
    $this->site->table('posts')->where('ID', $this->post)->update(['post_excerpt' => '']);
    $this->site->postMeta($this->post, 'rank_math_title', 'My own title');

    $seo = syncSeo($this->post);

    expect($seo?->title)->toBe('My own title')
        ->and($seo?->description)->toBe('Template text');
});

it('takes the templates of a plugin that is switched off when no other has any', function () {
    rankMathSettings($this->site, ['title_separator' => '~', 'pt_post_title' => '%title% %sep% %sitename%'], active: false);

    expect(syncSeo($this->post)?->title)->toBe('Hello World ~ Acme');
});

it('reports a variable it cannot resolve in a template of the site once', function () {
    rankMathSettings($this->site, ['pt_post_title' => '%title% %customfield(price)% %sep% %sitename%']);
    $other = $this->site->post(['post_title' => 'Second']);
    $own = $this->site->post(['post_title' => 'Own']);
    $this->site->postMeta($own, 'rank_math_title', 'Own %customfield(price)%');
    $context = importContext('posts');
    // One importer for the whole run, as in the import.
    $importer = app(SeoImporter::class);
    $read = fn (int $id) => app(PostReader::class)->byIds([$id])[0];

    expect($importer->resolve($read($own), $context)['title'] ?? null)->toBe('Own')
        ->and($importer->resolve($read($this->post), $context)['title'] ?? null)->toBe('Hello World - Acme')
        ->and($importer->resolve($read($other), $context)['title'] ?? null)->toBe('Second - Acme')
        // The template of a post is reported for that post, the template of the site once.
        ->and(array_column($context->report->unresolvedReferences(), 'source_id'))->toBe([$own, $this->post]);
});

it('leaves the templates of the site out when told to', function () {
    config()->set('wp-migrator.seo.site_defaults', false);
    rankMathSettings($this->site, ['pt_post_title' => '%title% %sep% %sitename%', 'pt_post_description' => '%excerpt%', 'pt_post_custom_robots' => 'on', 'pt_post_robots' => ['noindex']]);

    expect(syncSeo($this->post))->toBeNull();

    $this->site->option('active_plugins', serialize([]));
    $this->site->option('rank-math-options-titles', serialize([]));
    $this->site->option('wpseo_titles', serialize(['title-post' => '%%title%%', 'metadesc-post' => 'Text', 'noindex-post' => true]));
    app()->forgetInstance(SeoImporter::class);

    expect(syncSeo($this->post))->toBeNull();

    $this->site->postMeta($this->post, 'rank_math_description', 'Own');

    expect(syncSeo($this->post)?->getAttributes())->toMatchArray(['title' => null, 'description' => 'Own', 'robots' => null]);
});

it('applies the templates of the site with a configuration from before the setting existed', function () {
    $seo = config('wp-migrator.seo');
    unset($seo['site_defaults']);
    config()->set('wp-migrator.seo', $seo);

    expect(syncSeo($this->post)?->title)->toBe('Hello World – Acme');
});

it('fills the excerpt variable of Yoast from the whole content, not from one paragraph', function () {
    $post = $this->site->post(['post_title' => 'Two paragraphs', 'post_excerpt' => '', 'post_content' => "First paragraph.\n\nSecond paragraph."]);
    $this->site->postMeta($post, '_yoast_wpseo_metadesc', '%%excerpt%%');

    expect(syncSeo($post)?->description)->toBe('First paragraph. Second paragraph.');
});

// The expectations below are what real sites printed, without the directives
// that change nothing: index, follow, max-snippet:-1 and max-video-preview:-1.

it('stores the previews Yoast allowed, by what the post sets', function (array $meta, string $expected) {
    $this->site->option('wpseo_titles', serialize(['separator' => 'sc-dash']));

    foreach ($meta as $key => $value) {
        $this->site->postMeta($this->post, $key, $value);
    }

    expect(syncSeo($this->post)?->robots)->toBe($expected);
})->with([
    'nothing' => [[], 'max-image-preview:large'],
    'nofollow' => [['_yoast_wpseo_meta-robots-nofollow' => '1'], 'nofollow, max-image-preview:large'],
    'noindex' => [['_yoast_wpseo_meta-robots-noindex' => '1'], 'noindex'],
    'no image index' => [['_yoast_wpseo_meta-robots-adv' => 'noimageindex'], 'noimageindex, max-image-preview:none'],
    'no snippet' => [['_yoast_wpseo_meta-robots-adv' => 'nosnippet'], 'nosnippet, max-image-preview:large'],
    'no archive' => [['_yoast_wpseo_meta-robots-adv' => 'noarchive'], 'noarchive, max-image-preview:large'],
]);

it('stores the previews Rank Math allowed, by what the post sets', function (array $meta, string $expected) {
    rankMathSettings($this->site, ['title_separator' => '-']);

    foreach ($meta as $key => $value) {
        $this->site->postMeta($this->post, $key, serialize($value));
    }

    expect(syncSeo($this->post)?->robots)->toBe($expected);
})->with([
    'nothing' => [[], 'max-image-preview:large'],
    'nofollow' => [['rank_math_robots' => ['nofollow']], 'nofollow, max-image-preview:large'],
    'noindex' => [['rank_math_robots' => ['noindex']], 'noindex'],
    // Rank Math prints no previews next to nosnippet, and keeps them next to noimageindex.
    'no snippet' => [['rank_math_robots' => ['index', 'nosnippet']], 'index, nosnippet'],
    'no image index' => [['rank_math_robots' => ['index', 'noimageindex']], 'index, noimageindex, max-image-preview:large'],
    'its own previews' => [['rank_math_advanced_robots' => ['max-snippet' => '50', 'max-video-preview' => '-1', 'max-image-preview' => 'standard']], 'max-snippet:50, max-image-preview:standard'],
    'previews it cannot have' => [['rank_math_advanced_robots' => ['max-snippet' => 'lots', 'max-video-preview' => '0', 'max-image-preview' => 'huge"><script>', 'something-else' => 'x']], 'max-image-preview:large'],
]);

it('falls back to the previews of the site when those of a post cannot be read', function () {
    rankMathSettings($this->site, ['advanced_robots_global' => ['max-snippet' => '100', 'max-video-preview' => '-1', 'max-image-preview' => 'standard']]);
    $this->site->postMeta($this->post, 'rank_math_advanced_robots', 'a:3:{s:11:"max-snippet";');
    $context = importContext('posts');

    expect(syncSeo($this->post, $context)?->robots)->toBe('max-snippet:100, max-image-preview:standard')
        ->and($context->report->errors())->toBe([]);
});

it('stores the previews Rank Math allowed for the site and for a post type', function () {
    rankMathSettings($this->site, [
        'advanced_robots_global' => ['max-snippet' => '100', 'max-video-preview' => '-1', 'max-image-preview' => 'standard'],
        'pt_page_custom_robots' => 'on',
        'pt_page_robots' => ['index'],
        'pt_page_advanced_robots' => ['max-snippet' => '-1', 'max-video-preview' => '30', 'max-image-preview' => 'none'],
        'pt_event_custom_robots' => 'on',
        'pt_event_robots' => ['index'],
        'pt_book_custom_robots' => 'off',
        'pt_book_advanced_robots' => ['max-snippet' => '5', 'max-video-preview' => '5', 'max-image-preview' => 'none'],
    ]);
    $page = $this->site->page(['post_title' => 'Company']);
    $event = $this->site->post(['post_title' => 'Gig', 'post_type' => 'event']);
    $book = $this->site->post(['post_title' => 'Novel', 'post_type' => 'book']);
    $own = $this->site->page(['post_title' => 'Own']);
    $this->site->postMeta($own, 'rank_math_advanced_robots', serialize(['max-snippet' => '-1', 'max-video-preview' => '-1', 'max-image-preview' => 'large']));

    expect(syncSeo($this->post)?->robots)->toBe('max-snippet:100, max-image-preview:standard')
        ->and(syncSeo($page)?->robots)->toBe('max-video-preview:30, max-image-preview:none')
        // Switched on for the type, but without previews of its own: those of the site.
        ->and(syncSeo($event)?->robots)->toBe('max-snippet:100, max-image-preview:standard')
        // Not switched on for the type: its previews do not count.
        ->and(syncSeo($book)?->robots)->toBe('max-snippet:100, max-image-preview:standard')
        ->and(syncSeo($own)?->robots)->toBe('max-image-preview:large');
});

it('applies the robots Rank Math sets for the whole site', function () {
    rankMathSettings($this->site, ['robots_global' => ['noindex', 'nofollow'], 'pt_page_custom_robots' => 'on', 'pt_page_robots' => ['index', 'noarchive']]);
    $page = $this->site->page(['post_title' => 'Company']);

    expect(syncSeo($this->post)?->robots)->toBe('noindex, nofollow')
        ->and(syncSeo($page)?->robots)->toBe('index, noarchive, max-image-preview:large');
});

it('stores only the previews a post sets itself when the templates of the site are left out', function () {
    config()->set('wp-migrator.seo.site_defaults', false);
    rankMathSettings($this->site, ['advanced_robots_global' => ['max-snippet' => '100', 'max-video-preview' => '-1', 'max-image-preview' => 'standard'], 'robots_global' => ['noindex']]);
    $other = $this->site->post(['post_title' => 'Other']);
    $this->site->postMeta($this->post, 'rank_math_robots', serialize(['nofollow']));
    $this->site->postMeta($this->post, 'rank_math_advanced_robots', serialize(['max-snippet' => '50', 'max-video-preview' => '-1', 'max-image-preview' => 'standard']));
    $this->site->postMeta($other, 'rank_math_robots', serialize(['nofollow']));

    expect(syncSeo($this->post)?->robots)->toBe('nofollow, max-snippet:50, max-image-preview:standard')
        ->and(syncSeo($other)?->robots)->toBe('nofollow');
});
