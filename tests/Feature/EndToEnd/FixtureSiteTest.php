<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\ImportReport;
use ItsJustVita\WpMigrator\Import\ImportRunner;
use ItsJustVita\WpMigrator\Inventory\SiteInventory;
use ItsJustVita\WpMigrator\Models\Comment;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Models\Menu;
use ItsJustVita\WpMigrator\Models\MenuItem;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Models\Redirect;
use ItsJustVita\WpMigrator\Tests\Support\User;
use ItsJustVita\WpMigrator\Tests\TestCase;

/*
 * The fixture site was built with a real WordPress installation by
 * tests/Fixtures/wordpress/build.sh. Its SQL dump needs MySQL or MariaDB.
 */

function importFixtureSite(): ImportReport
{
    return app(ImportRunner::class)->run(new ImportOptions);
}

function fixturePost(string $title): Post
{
    return Post::query()->where('title', $title)->sole();
}

/**
 * Fields keep the order of the meta rows; sorting the keys makes the
 * comparison independent of it.
 */
function sortKeys(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    if (! array_is_list($value)) {
        ksort($value);
    }

    return array_map(sortKeys(...), $value);
}

function fixtureMedia(string $file): Media
{
    return Media::query()->where('path', 'wp-uploads/2026/01/'.$file)->sole();
}

beforeEach(function () {
    if (! TestCase::usesServerDatabase()) {
        $this->markTestSkipped('The fixture site is a MySQL dump; set WP_MIGRATOR_TEST_DB_* to run it.');
    }

    DB::connection('wordpress')->unprepared((string) file_get_contents(__DIR__.'/../../Fixtures/wordpress/site.sql'));

    Storage::fake('public');
    config()->set('wp-migrator.source.uploads_path', __DIR__.'/../../Fixtures/wordpress/uploads');
    config()->set('wp-migrator.media.download_missing', false);
    config()->set('wp-migrator.comments.enabled', true);
    config()->set('wp-migrator.passwords.allow_md5', true);
});

it('imports the fixture site without errors or open references', function () {
    $report = importFixtureSite();

    expect($report->errors())->toBe([])
        ->and($report->unresolvedReferences())->toBe([])
        ->and($report->warnings())->toBe([])
        ->and(array_keys($report->unknownShortcodes()))->toBe(['contact-form-7'])
        ->and(array_keys($report->unknownBlocks()))->toBe(['acme/hero'])
        ->and(array_map(fn ($finding) => $finding->key, $report->findings()))->toBe(['elementor']);
});

it('imports classic and block content', function () {
    importFixtureSite();

    $classic = fixturePost('Classic Story');
    $block = fixturePost('Block Story');
    $html = $classic->blocks[0]['innerHTML'];

    expect($classic->blocks)->toHaveCount(1)
        ->and($classic->blocks[0]['name'])->toBe('core/freeform')
        ->and($html)->toStartWith("<p>Welcome to the classic story.<br />\nIt has a line break.</p>")
        ->toContain('<a href="/blog/block-story">block story</a>')
        ->toContain('<figure id="attachment_')
        ->toContain('src="/storage/wp-uploads/2026/01/hero-300x200.png"')
        ->toContain('class="wp-block-gallery wp-gallery columns-2"')
        ->toContain('player.vimeo.com/video/123456')
        ->toContain('[contact-form-7 id="42" title="Contact"]')
        ->toContain("<pre>Preformatted\n\n  text</pre>")
        ->and($classic->featured_media_id)->toBe(fixtureMedia('hero.png')->id)
        ->and($classic->terms->pluck('slug')->all())->toBe(['news'])
        ->and(array_column($block->blocks, 'name'))->toBe([
            'core/heading', 'core/paragraph', 'core/image', 'core/group', 'core/embed', 'core/code', 'core/paragraph', 'acme/hero',
        ])
        ->and($block->blocks[1]['innerHTML'])->toContain('href="/about"')
        ->and($block->blocks[2]['attrs']['id'])->toBe(fixtureMedia('hero.png')->id)
        ->and($block->blocks[2]['innerHTML'])->toContain('/storage/wp-uploads/2026/01/hero-300x200.png')
        ->and($block->blocks[5]['innerHTML'])->toContain('[gallery] stays literal')
        ->and($block->blocks[6]['innerHTML'])->toContain('Get in touch!')
        ->and($block->terms->pluck('slug')->sort()->values()->all())->toBe(['php', 'tech']);
});

it('imports pages with their hierarchy and template, and drafts as drafts', function () {
    importFixtureSite();

    expect(fixturePost('Team')->parent?->title)->toBe('About us')
        ->and(fixturePost('About us')->template)->toBe('template-wide.php')
        ->and(fixturePost('Unfinished')->status)->toBe('draft')
        ->and(fixturePost('Unfinished')->published_at)->toBeNull()
        ->and(Post::query()->where('type', 'wp_block')->exists())->toBeFalse();
});

it('copies the media files and their sizes', function () {
    importFixtureSite();

    $hero = fixtureMedia('hero.png');

    expect($hero->width)->toBe(1200)
        ->and($hero->height)->toBe(800)
        ->and($hero->alt)->toBe('A red rectangle')
        ->and(array_keys($hero->variants ?? []))->toContain('thumbnail', 'medium', 'large');

    foreach (['hero.png', 'hero-300x200.png', 'second.png', 'second-150x150.png'] as $file) {
        Storage::disk('public')->assertExists('wp-uploads/2026/01/'.$file);
    }
});

it('converts every kind of custom field', function () {
    importFixtureSite();

    $event = fixturePost('Summer Concert');

    expect($event->type)->toBe('event')
        ->and(sortKeys($event->fields))->toBe(sortKeys([
            'subtitle' => 'An evening outdoors',
            'sold_out' => true,
            'date' => '2026-07-15',
            'doors' => '2026-07-15T19:00:00+02:00',
            'price' => 25.5,
            'poster' => fixtureMedia('hero.png')->id,
            'photos' => [fixtureMedia('second.png')->id, fixtureMedia('hero.png')->id],
            'related' => [fixturePost('Block Story')->id],
            'tickets' => ['title' => 'Buy tickets', 'url' => '/about', 'target' => '_blank'],
            'lineup' => [
                ['artist' => 'The Openers', 'headliner' => false],
                ['artist' => 'The Headliners', 'headliner' => true],
            ],
            'sections' => [
                ['layout' => 'text', 'body' => "<p>First paragraph.</p>\n<p>Second paragraph.</p>"],
                ['layout' => 'quote', 'quote' => 'Unforgettable', 'by' => 'A visitor'],
            ],
            'venue' => ['name' => 'Open Air Stage', 'capacity' => 800],
        ]));
});

it('imports the yoast and rank math settings', function () {
    importFixtureSite();

    $block = fixturePost('Block Story')->seo;
    $about = fixturePost('About us')->seo;

    expect($block?->title)->toBe('Block Story | Acme')
        ->and($block?->description)->toStartWith('A block post Read more about us.')
        ->and($block?->canonical)->toBe('/blog/block-story')
        ->and($block?->robots)->toBe('nofollow')
        ->and($block?->og_image_id)->toBe(fixtureMedia('hero.png')->id)
        ->and($about?->title)->toBe('About us – Acme')
        ->and($about?->description)->toBe('Who we are and what we do.');
});

it('imports the classic menu and the navigation menu', function () {
    importFixtureSite();

    $items = fn (string $name): array => Menu::query()->where('name', $name)->sole()->items()->get()
        ->map(fn (MenuItem $item) => [$item->title, $item->url, $item->parent?->title, $item->target])->all();

    expect(Menu::query()->where('name', 'Main Menu')->sole()->locations)->toBe(['primary'])
        ->and($items('Main Menu'))->toBe([
            ['Home', '/', null, null],
            ['About us', '/about', null, null],
            ['Team', '/about/team', 'About us', null],
            ['News', '/blog/category/news', null, null],
            ['Partner', 'https://example.net/', null, '_blank'],
        ])
        ->and($items('Header navigation'))->toBe([
            ['Start', '/', null, null],
            ['About', '/about', null, null],
            ['More', '#', null, null],
            ['News', '/blog/category/news', 'More', null],
        ]);
});

it('imports approved and pending comments with their threads', function () {
    importFixtureSite();

    $reply = Comment::query()->where('content', 'Thank you!')->sole();

    expect(Comment::query()->orderBy('content')->pluck('status', 'content')->all())->toBe([
        'Great read!' => 'approved',
        'Thank you!' => 'approved',
        'Waiting for approval' => 'pending',
    ])
        ->and($reply->parent?->content)->toBe('Great read!')
        ->and($reply->author_id)->toBe(User::query()->where('email', 'editor@example.com')->value('id'))
        ->and($reply->commentable?->is(fixturePost('Classic Story')))->toBeTrue();
});

it('keeps every password working, whatever hash WP stored', function (string $email, string $password) {
    config()->set('hashing.driver', 'wordpress');
    Hash::forgetDrivers();

    importFixtureSite();

    $hash = (string) User::query()->where('email', $email)->value('password');

    expect(Hash::check($password, $hash))->toBeTrue()
        ->and(Hash::check($password.'x', $hash))->toBeFalse();
})->with([
    'bcrypt with WP prefix' => ['editor@example.com', 'bcrypt-wp-password'],
    'phpass' => ['author@example.com', 'phpass-password'],
    'md5' => ['reader@example.com', 'md5-password'],
    'plain bcrypt' => ['member@example.com', 'plain-bcrypt-password'],
    'administrator' => ['admin@example.com', 'admin-password'],
]);

it('writes redirects for the old urls', function () {
    importFixtureSite();

    $redirects = Redirect::query()->pluck('to_path', 'from_path')->all();

    expect($redirects)->toMatchArray([
        '/2026/01/classic-story' => '/blog/classic-story',
        '/2026/02/block-story' => '/blog/block-story',
        '/veranstaltungen/summer-concert' => '/event/summer-concert',
        '/thema/news/tech' => '/blog/category/news/tech',
        '/tag/php' => '/blog/tag/php',
        '/2026/01/classic-story/hero' => '/storage/wp-uploads/2026/01/hero.png',
        '/?p='.DB::connection('wordpress')->table('wp_posts')->where('post_name', 'classic-story')->value('ID') => '/blog/classic-story',
    ])->and($redirects)->not->toHaveKey('/about');
});

it('changes nothing when the site is imported a second time', function () {
    importFixtureSite();
    $counts = fn (): array => [Post::count(), Media::count(), User::count(), Menu::count(), MenuItem::count(), Comment::count(), Redirect::count()];
    $before = $counts();

    $second = importFixtureSite();

    foreach ($second->counts() as $importer => $outcomes) {
        expect($outcomes['created'] + $outcomes['updated'] + $outcomes['failed'])->toBe(0, "{$importer} changed on the second run");
    }

    expect($counts())->toBe($before);
});

it('reads the facts for the migration plan', function () {
    $site = app(SiteInventory::class)->read();

    expect($site->reachable)->toBeTrue()
        ->and($site->stylesheet)->toBe('twentytwentyone')
        ->and($site->plugins)->toContain('secure-custom-fields/secure-custom-fields.php')
        ->and(array_keys($site->shortcodes))->toBe(['contact-form-7'])
        ->and(array_keys($site->blocks))->toBe(['acme/hero'])
        ->and($site->menus)->toBe(['primary' => ['menu' => 'Main Menu', 'items' => 5]])
        ->and($site->pageTemplates)->toBe(['template-wide.php' => [['title' => 'About us', 'type' => 'page']]])
        ->and($site->taxonomies)->toHaveKeys(['category', 'post_tag'])
        ->and(array_map(fn ($finding) => $finding->key, $site->findings))->toBe(['elementor']);
});
