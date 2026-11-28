<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ItsJustVita\WpMigrator\Database\Tables;
use ItsJustVita\WpMigrator\Facades\WpMigrator;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\ImportReport;
use ItsJustVita\WpMigrator\Import\ImportRunner;
use ItsJustVita\WpMigrator\Import\NullProgress;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Mapping\PostMapper;
use ItsJustVita\WpMigrator\Models\Comment;
use ItsJustVita\WpMigrator\Models\ImportMapEntry;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Models\Menu;
use ItsJustVita\WpMigrator\Models\MenuItem;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Models\Redirect;
use ItsJustVita\WpMigrator\Models\SeoMeta;
use ItsJustVita\WpMigrator\Models\Term;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;
use ItsJustVita\WpMigrator\Tests\Support\Event;
use ItsJustVita\WpMigrator\Tests\Support\SourceSite;
use ItsJustVita\WpMigrator\Tests\Support\User;

final class PrunedEventMapper implements PostMapper
{
    public function map(SourcePost $post, ImportContext $context): ?Model
    {
        $event = $context->existing(SourceType::Post, $post->id, Event::class) ?? new Event;
        $event->fill(['title' => $post->title])->save();

        return $event;
    }
}

/** Maps every event onto one record, as a mapper may that merges rows. */
final class MergingEventMapper implements PostMapper
{
    public function map(SourcePost $post, ImportContext $context): ?Model
    {
        return Event::query()->firstOrCreate(['title' => 'All events']);
    }
}

function importWithoutPruning(): ImportReport
{
    return app(ImportRunner::class)->run(new ImportOptions);
}

function importAndPrune(?Closure $confirm = null, bool $dryRun = false, ?CarbonImmutable $since = null): ImportReport
{
    return app(ImportRunner::class)->run(new ImportOptions(since: $since, dryRun: $dryRun, prune: true), new NullProgress, $confirm);
}

function deleteSourcePost(SourceSite $site, int $id): void
{
    $site->table('posts')->where('ID', $id)->delete();
    $site->table('postmeta')->where('post_id', $id)->delete();
    $site->table('term_relationships')->where('object_id', $id)->delete();
}

function deleteSourceTerm(SourceSite $site, int $id): void
{
    $taxonomyIds = $site->table('term_taxonomy')->where('term_id', $id)->pluck('term_taxonomy_id')->all();

    $site->table('term_relationships')->whereIn('term_taxonomy_id', $taxonomyIds)->delete();
    $site->table('term_taxonomy')->where('term_id', $id)->delete();
    $site->table('terms')->where('term_id', $id)->delete();
}

/**
 * @return list<int>
 */
function mappedIds(SourceType $type): array
{
    return ImportMapEntry::query()->where('source_type', $type->value)->orderBy('source_id')->pluck('source_id')->all();
}

function sourceImage(SourceSite $site, string $uploads, string $name = 'hero'): int
{
    $image = $site->attachment(['post_title' => ucfirst($name), 'post_name' => $name, 'post_author' => 0]);
    $site->postMeta($image, '_wp_attached_file', '2026/01/'.$name.'.jpg');
    $site->postMeta($image, '_wp_attachment_metadata', serialize([
        'width' => 800,
        'height' => 600,
        'file' => '2026/01/'.$name.'.jpg',
        'sizes' => ['thumbnail' => ['file' => $name.'-150x150.jpg', 'width' => 150, 'height' => 150, 'mime-type' => 'image/jpeg']],
    ]));

    File::ensureDirectoryExists($uploads.'/2026/01');
    file_put_contents($uploads.'/2026/01/'.$name.'.jpg', 'jpg');
    file_put_contents($uploads.'/2026/01/'.$name.'-150x150.jpg', 'small');

    return $image;
}

beforeEach(function () {
    Storage::fake('public');
    $this->uploads = sys_get_temp_dir().'/wp-migrator-uploads-'.uniqid();
    config()->set('wp-migrator.source.uploads_path', $this->uploads);
    config()->set('wp-migrator.media.download_missing', false);
    $this->site = $this->source();
});

afterEach(function () {
    File::deleteDirectory($this->uploads);
});

it('removes posts that were deleted or trashed on the old site', function () {
    $deleted = $this->site->post(['post_title' => 'Deleted', 'post_author' => 0]);
    $trashed = $this->site->page(['post_title' => 'Trashed', 'post_author' => 0]);
    $kept = $this->site->post(['post_title' => 'Kept', 'post_author' => 0]);
    importWithoutPruning();
    deleteSourcePost($this->site, $deleted);
    $this->site->table('posts')->where('ID', $trashed)->update(['post_status' => 'trash']);

    $report = importAndPrune();

    expect(Post::query()->pluck('title')->all())->toBe(['Kept'])
        ->and(mappedIds(SourceType::Post))->toBe([$kept])
        ->and($report->removedItems())->toBe([
            ['kind' => 'posts', 'source_id' => $deleted, 'what' => 'post "Deleted"'],
            ['kind' => 'posts', 'source_id' => $trashed, 'what' => 'page "Trashed"'],
        ])
        ->and($report->errors())->toBe([])
        ->and($report->toMarkdown())->toContain("## Removed (2)\n\n- posts #".$deleted.': post "Deleted"');

    $again = importAndPrune();

    expect($again->removedItems())->toBe([])
        ->and($again->toMarkdown())->not->toContain('Removed')
        ->and(Post::count())->toBe(1);
});

it('removes what the configuration leaves out since the last import', function () {
    $this->site->post(['post_title' => 'Concert', 'post_type' => 'event', 'post_author' => 0]);
    $this->site->post(['post_title' => 'Draft', 'post_status' => 'draft', 'post_author' => 0]);
    $this->site->post(['post_title' => 'Kept', 'post_author' => 0]);
    $this->site->term('genre', 'Jazz');
    $this->site->term('category', 'News');
    importWithoutPruning();
    config()->set('wp-migrator.import.excluded_post_types', [...config('wp-migrator.import.excluded_post_types'), 'event']);
    config()->set('wp-migrator.import.post_statuses', ['publish']);
    config()->set('wp-migrator.import.excluded_taxonomies', [...config('wp-migrator.import.excluded_taxonomies'), 'genre']);

    importAndPrune();

    expect(Post::query()->pluck('title')->all())->toBe(['Kept'])
        ->and(Term::query()->pluck('name')->all())->toBe(['News']);
});

it('removes nothing without the option', function () {
    $gone = $this->site->post(['post_title' => 'Gone', 'post_author' => 0]);
    importWithoutPruning();
    deleteSourcePost($this->site, $gone);

    $report = importWithoutPruning();

    expect(Post::query()->pluck('title')->all())->toBe(['Gone'])
        ->and(mappedIds(SourceType::Post))->toBe([$gone])
        ->and($report->removedItems())->toBe([]);
});

it('does not take rows for deleted that a since date leaves out', function () {
    $this->site->post(['post_title' => 'Old', 'post_author' => 0, 'post_modified_gmt' => '2025-01-01 00:00:00']);
    $gone = $this->site->post(['post_title' => 'Gone', 'post_author' => 0]);
    sourceImage($this->site, $this->uploads);
    importWithoutPruning();
    deleteSourcePost($this->site, $gone);

    importAndPrune(since: CarbonImmutable::parse('2030-01-01'));

    expect(Post::query()->pluck('title')->all())->toBe(['Old'])
        ->and(Media::count())->toBe(1);
});

it('removes what belonged to a removed post and keeps the pages below it', function () {
    config()->set('wp-migrator.comments.enabled', true);
    $this->site->option('blogname', 'Acme');
    $parent = $this->site->page(['post_title' => 'Parent', 'post_author' => 0]);
    $child = $this->site->page(['post_title' => 'Child', 'post_parent' => $parent, 'post_author' => 0]);
    $this->site->postMeta($parent, '_yoast_wpseo_title', 'Parent title');
    $this->site->postMeta($child, '_yoast_wpseo_title', 'Child title');
    $news = $this->site->term('category', 'News');
    $this->site->attachTerm($parent, $news);
    $this->site->attachTerm($child, $news);
    $first = $this->site->comment($parent, ['comment_content' => 'On the parent']);
    $this->site->comment($parent, ['comment_content' => 'A reply', 'comment_parent' => $first]);
    $onChild = $this->site->comment($child, ['comment_content' => 'On the child']);
    importWithoutPruning();
    // WP keeps the comments of a trashed post; with comments turned off, nothing else would remove them.
    config()->set('wp-migrator.comments.enabled', false);
    $this->site->table('posts')->where('ID', $parent)->update(['post_status' => 'trash']);

    importAndPrune();

    $kept = Post::query()->sole();

    expect($kept->title)->toBe('Child')
        ->and($kept->parent_id)->toBeNull()
        ->and(SeoMeta::query()->pluck('title')->all())->toBe(['Child title'])
        ->and(Term::query()->pluck('name')->all())->toBe(['News'])
        ->and(DB::table(Tables::prefixed('termables'))->pluck('termable_id')->all())->toBe([$kept->id])
        ->and(Comment::query()->pluck('content')->all())->toBe(['On the child'])
        ->and(mappedIds(SourceType::Comment))->toBe([$onChild]);
});

it('removes media with its files', function () {
    $gone = sourceImage($this->site, $this->uploads, 'hero');
    sourceImage($this->site, $this->uploads, 'logo');
    $post = $this->site->post(['post_title' => 'Hello', 'post_author' => 0]);
    $this->site->postMeta($post, '_thumbnail_id', (string) $gone);
    importWithoutPruning();
    Storage::disk('public')->assertExists(['wp-uploads/2026/01/hero.jpg', 'wp-uploads/2026/01/hero-150x150.jpg']);
    deleteSourcePost($this->site, $gone);
    $this->site->table('postmeta')->where('meta_key', '_thumbnail_id')->delete();

    $report = importAndPrune();

    expect(Media::query()->pluck('path')->all())->toBe(['wp-uploads/2026/01/logo.jpg'])
        ->and(Post::query()->sole()->featured_media_id)->toBeNull()
        ->and($report->removedItems())->toBe([['kind' => 'media', 'source_id' => $gone, 'what' => 'wp-uploads/2026/01/hero.jpg']])
        ->and($report->warnings())->toBe([]);

    Storage::disk('public')->assertMissing(['wp-uploads/2026/01/hero.jpg', 'wp-uploads/2026/01/hero-150x150.jpg']);
    Storage::disk('public')->assertExists(['wp-uploads/2026/01/logo.jpg', 'wp-uploads/2026/01/logo-150x150.jpg']);
});

it('keeps the files another media row still uses', function () {
    $gone = sourceImage($this->site, $this->uploads, 'hero');
    $twin = $this->site->attachment(['post_title' => 'Twin', 'post_author' => 0]);
    $this->site->postMeta($twin, '_wp_attached_file', '2026/01/hero.jpg');
    importWithoutPruning();
    deleteSourcePost($this->site, $gone);

    importAndPrune();

    expect(Media::query()->pluck('title')->all())->toBe(['Twin']);
    Storage::disk('public')->assertExists(['wp-uploads/2026/01/hero.jpg', 'wp-uploads/2026/01/hero-150x150.jpg']);
});

it('removes terms and leaves their posts', function () {
    $news = $this->site->term('category', 'News');
    $old = $this->site->term('post_tag', 'Old');
    $post = $this->site->post(['post_title' => 'Hello', 'post_author' => 0]);
    $this->site->attachTerm($post, $news);
    $this->site->attachTerm($post, $old);
    importWithoutPruning();
    deleteSourceTerm($this->site, $old);

    $report = importAndPrune();

    expect(Term::query()->pluck('name')->all())->toBe(['News'])
        ->and(Post::query()->sole()->terms->pluck('name')->all())->toBe(['News'])
        ->and(mappedIds(SourceType::Term))->toBe([$news])
        ->and($report->removedItems())->toBe([['kind' => 'terms', 'source_id' => $old, 'what' => 'post_tag "Old"']]);
});

it('removes menus and menu items', function () {
    $main = $this->site->menu('Main');
    $footer = $this->site->menu('Footer');
    $home = $this->site->menuItem($main, ['title' => 'Home', 'url' => 'https://example.com/', 'order' => 1]);
    $shop = $this->site->menuItem($main, ['title' => 'Shop', 'url' => 'https://example.com/shop', 'order' => 2]);
    $imprint = $this->site->menuItem($footer, ['title' => 'Imprint', 'url' => 'https://example.com/imprint']);
    importWithoutPruning();
    deleteSourcePost($this->site, $shop);
    deleteSourcePost($this->site, $imprint);
    deleteSourceTerm($this->site, $footer);

    $report = importAndPrune();

    expect(Menu::query()->pluck('name')->all())->toBe(['Main'])
        ->and(MenuItem::query()->pluck('title')->all())->toBe(['Home'])
        ->and(mappedIds(SourceType::Post))->toBe([$home])
        ->and(mappedIds(SourceType::Term))->toBe([$main])
        ->and($report->removedItems())->toBe([
            ['kind' => 'menu items', 'source_id' => $shop, 'what' => 'menu item "Shop"'],
            ['kind' => 'menu items', 'source_id' => $imprint, 'what' => 'menu item "Imprint"'],
            ['kind' => 'menus', 'source_id' => $footer, 'what' => 'menu "Footer"'],
        ])
        ->and($report->removedRedirects())->toBe(0)
        ->and($report->toMarkdown())->not->toContain('redirects:');
});

it('removes the navigation menus of block themes', function () {
    $header = $this->site->post(['post_type' => 'wp_navigation', 'post_title' => 'Header', 'post_author' => 0, 'post_content' => '<!-- wp:navigation-link {"label":"Home","url":"/"} /-->']);
    $footer = $this->site->post(['post_type' => 'wp_navigation', 'post_title' => 'Footer', 'post_author' => 0, 'post_content' => '<!-- wp:navigation-link {"label":"Imprint","url":"/imprint"} /-->']);
    importWithoutPruning();
    expect(MenuItem::count())->toBe(2);
    deleteSourcePost($this->site, $footer);

    $report = importAndPrune();

    expect(Menu::query()->pluck('name')->all())->toBe(['Header'])
        ->and(MenuItem::query()->pluck('title')->all())->toBe(['Home'])
        ->and(mappedIds(SourceType::Post))->toBe([$header])
        ->and($report->removedItems())->toBe([['kind' => 'menus', 'source_id' => $footer, 'what' => 'menu "Footer"']]);
});

it('moves the entries below a removed menu item up', function () {
    $menu = $this->site->menu('Main');
    $top = $this->site->menuItem($menu, ['title' => 'Top', 'url' => 'https://example.com/top']);
    $middle = $this->site->menuItem($menu, ['title' => 'Middle', 'url' => 'https://example.com/middle', 'parent' => $top]);
    $this->site->menuItem($menu, ['title' => 'Bottom', 'url' => 'https://example.com/bottom', 'parent' => $middle]);
    importWithoutPruning();
    // A menu item that is no longer published is not read; the one below still names it as its parent.
    $this->site->table('posts')->where('ID', $middle)->update(['post_status' => 'draft']);

    importAndPrune();

    expect(MenuItem::query()->orderBy('id')->pluck('title')->all())->toBe(['Top', 'Bottom'])
        ->and(MenuItem::query()->where('title', 'Bottom')->sole()->parent?->title)->toBe('Top');
});

it('removes the menu items that link to removed content', function () {
    $this->site->option('permalink_structure', '/%postname%/');
    $menu = $this->site->menu('Main');
    $about = $this->site->page(['post_title' => 'About', 'post_author' => 0]);
    $news = $this->site->term('category', 'News');
    $kept = $this->site->term('category', 'Kept');
    $aboutItem = $this->site->menuItem($menu, ['title' => 'About', 'type' => 'post_type', 'object' => 'page', 'object_id' => $about]);
    $this->site->menuItem($menu, ['title' => 'Team', 'url' => 'https://example.com/team', 'parent' => $aboutItem]);
    $this->site->menuItem($menu, ['title' => 'News', 'type' => 'taxonomy', 'object' => 'category', 'object_id' => $news]);
    $this->site->menuItem($menu, ['title' => 'Kept', 'type' => 'taxonomy', 'object' => 'category', 'object_id' => $kept]);
    importWithoutPruning();
    expect(MenuItem::count())->toBe(4);
    // WP keeps the menu item of a trashed page and hides it.
    $this->site->table('posts')->where('ID', $about)->update(['post_status' => 'trash']);
    deleteSourceTerm($this->site, $news);
    $this->site->table('posts')->where('post_title', 'News')->where('post_type', 'nav_menu_item')->delete();

    importAndPrune();

    expect(MenuItem::query()->orderBy('id')->pluck('title')->all())->toBe(['Kept', 'Team'])
        ->and(MenuItem::query()->where('title', 'Team')->sole()->parent_id)->toBeNull()
        ->and(mappedIds(SourceType::Post))->not->toContain($aboutItem)
        ->and(mappedIds(SourceType::Term))->toBe([$menu, $kept]);

    importWithoutPruning();

    expect(MenuItem::query()->orderBy('id')->pluck('title')->all())->toBe(['Kept', 'Team']);

    $forced = app(ImportRunner::class)->run(new ImportOptions(force: true));

    expect($forced->errors())->toBe([])
        ->and(MenuItem::query()->orderBy('id')->pluck('title')->all())->toBe(['Kept', 'Team'])
        ->and(MenuItem::query()->where('title', 'Team')->sole()->parent_id)->toBeNull();
});

it('removes comments and moves the replies to a removed comment up', function () {
    config()->set('wp-migrator.comments.enabled', true);
    $post = $this->site->post(['post_title' => 'Hello', 'post_author' => 0]);
    $first = $this->site->comment($post, ['comment_content' => 'First', 'comment_author' => 'Ann']);
    $spam = $this->site->comment($post, ['comment_content' => 'Buy now', 'comment_author' => 'Bot', 'comment_parent' => $first]);
    $reply = $this->site->comment($post, ['comment_content' => 'Reply', 'comment_parent' => $spam]);
    $deleted = $this->site->comment($post, ['comment_content' => 'Deleted', 'comment_author' => 'Cem']);
    importWithoutPruning();
    $this->site->table('comments')->where('comment_ID', $spam)->update(['comment_approved' => 'spam']);
    $this->site->table('comments')->where('comment_ID', $deleted)->delete();

    $report = importAndPrune();

    expect(Comment::query()->orderBy('id')->pluck('content')->all())->toBe(['First', 'Reply'])
        ->and(Comment::query()->where('content', 'Reply')->sole()->parent?->content)->toBe('First')
        ->and(mappedIds(SourceType::Comment))->toBe([$first, $reply])
        ->and($report->removedItems())->toBe([
            ['kind' => 'comments', 'source_id' => $spam, 'what' => 'comment by Bot'],
            ['kind' => 'comments', 'source_id' => $deleted, 'what' => 'comment by Cem'],
        ]);
});

it('leaves comments alone while comments are turned off', function () {
    config()->set('wp-migrator.comments.enabled', true);
    $post = $this->site->post(['post_title' => 'Hello', 'post_author' => 0]);
    $comment = $this->site->comment($post);
    importWithoutPruning();
    config()->set('wp-migrator.comments.enabled', false);
    $this->site->table('comments')->where('comment_ID', $comment)->delete();

    $report = importAndPrune();

    expect(Comment::count())->toBe(1)
        ->and(mappedIds(SourceType::Comment))->toBe([$comment])
        ->and($report->removedItems())->toBe([]);
});

it('keeps the users that are gone from the old site and warns about them', function () {
    $this->site->user(['user_login' => 'alice', 'user_email' => 'alice@example.com']);
    $bob = $this->site->user(['user_login' => 'bob', 'user_email' => 'bob@example.com']);
    importWithoutPruning();
    $account = User::query()->where('email', 'bob@example.com')->sole();
    $this->site->table('users')->where('ID', $bob)->delete();
    $asked = false;

    $report = importAndPrune(function () use (&$asked): bool {
        $asked = true;

        return true;
    });

    expect(User::query()->orderBy('id')->pluck('email')->all())->toBe(['alice@example.com', 'bob@example.com'])
        ->and($asked)->toBeFalse()
        ->and($report->removedItems())->toBe([])
        ->and($report->warnings())->toBe([[
            'importer' => 'users',
            'source_id' => $bob,
            'message' => sprintf('No longer exists on the old site, but is still user #%d here. Delete the account in the application, or it can still log in.', $account->id),
        ]])
        ->and(mappedIds(SourceType::User))->toContain($bob);

    $account->delete();
    $report = importAndPrune();

    expect($report->warnings())->toBe([])
        ->and(mappedIds(SourceType::User))->not->toContain($bob);
});

it('forgets a linked user without a warning and without touching the account', function () {
    User::create(['name' => 'App Admin', 'email' => 'alice@example.com', 'password' => 'secret']);
    $alice = $this->site->user(['user_login' => 'alice', 'user_email' => 'alice@example.com']);
    importWithoutPruning();
    expect(ImportMapEntry::query()->where('source_type', 'user')->sole()->linked)->toBeTrue();
    $this->site->table('users')->where('ID', $alice)->delete();

    $report = importAndPrune();

    expect(User::query()->pluck('name')->all())->toBe(['App Admin'])
        ->and($report->warnings())->toBe([])
        ->and($report->removedItems())->toBe([])
        ->and(mappedIds(SourceType::User))->toBe([]);
});

it('deletes the models of custom mappers through Eloquent', function () {
    Schema::create('events', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('starts_at')->nullable();
        $table->timestamps();
    });
    WpMigrator::mapPostType('event', PrunedEventMapper::class);
    $this->site->option('blogname', 'Acme');
    $gone = $this->site->post(['post_title' => 'Concert', 'post_type' => 'event', 'post_author' => 0]);
    $this->site->postMeta($gone, '_yoast_wpseo_title', 'Concert title');
    $this->site->post(['post_title' => 'Festival', 'post_type' => 'event', 'post_author' => 0]);
    importWithoutPruning();
    $event = Event::query()->where('title', 'Concert')->sole();
    deleteSourcePost($this->site, $gone);
    $deleting = [];
    Event::deleting(function (Event $event) use (&$deleting): void {
        $deleting[] = $event->title;
    });

    $report = importAndPrune();

    expect(Event::query()->pluck('title')->all())->toBe(['Festival'])
        ->and($deleting)->toBe(['Concert'])
        ->and(SeoMeta::query()->pluck('title')->all())->toBe(['Festival – Acme'])
        ->and($report->removedItems())->toBe([['kind' => 'posts', 'source_id' => $gone, 'what' => 'Event #'.$event->id]]);
});

it('keeps a model as long as another row of the old site maps to it', function () {
    Schema::create('events', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('starts_at')->nullable();
        $table->timestamps();
    });
    WpMigrator::mapPostType('event', MergingEventMapper::class);
    $first = $this->site->post(['post_title' => 'First', 'post_type' => 'event', 'post_author' => 0]);
    $second = $this->site->post(['post_title' => 'Second', 'post_type' => 'event', 'post_author' => 0]);
    $third = $this->site->post(['post_title' => 'Third', 'post_type' => 'event', 'post_author' => 0]);
    importWithoutPruning();
    deleteSourcePost($this->site, $first);
    $asked = [];
    $confirm = function (array $counts) use (&$asked): bool {
        $asked[] = $counts;

        return true;
    };

    $report = importAndPrune($confirm);

    expect(Event::count())->toBe(1)
        ->and(mappedIds(SourceType::Post))->toBe([$second, $third])
        ->and($asked)->toBe([])
        ->and($report->removedItems())->toBe([]);

    deleteSourcePost($this->site, $second);
    deleteSourcePost($this->site, $third);

    $report = importAndPrune($confirm);

    expect(Event::count())->toBe(0)
        ->and(mappedIds(SourceType::Post))->toBe([])
        ->and($asked)->toBe([['posts' => 1]])
        ->and($report->removedItems())->toHaveCount(1)
        ->and($report->errors())->toBe([]);
});

it('forgets rows whose model or model class is already gone', function () {
    $gone = $this->site->post(['post_title' => 'Gone', 'post_author' => 0]);
    importWithoutPruning();
    Post::query()->delete();
    deleteSourcePost($this->site, $gone);
    ImportMapEntry::query()->create([
        'source_type' => 'post', 'source_id' => 999, 'target_type' => 'App\\Models\\Vanished', 'target_id' => 1,
        'checksum' => 'x', 'linked' => false, 'imported_at' => now(),
    ]);
    $asked = false;

    $report = importAndPrune(function () use (&$asked): bool {
        $asked = true;

        return true;
    });

    expect(mappedIds(SourceType::Post))->toBe([])
        ->and($asked)->toBeFalse()
        ->and($report->removedItems())->toBe([])
        ->and($report->errors())->toBe([]);
});

it('finds the records of an application that uses a morph map', function () {
    Relation::morphMap(['wp-post' => Post::class]);

    try {
        $gone = $this->site->post(['post_title' => 'Gone', 'post_author' => 0]);
        importWithoutPruning();
        expect(ImportMapEntry::query()->where('source_id', $gone)->sole()->target_type)->toBe('wp-post');
        deleteSourcePost($this->site, $gone);

        $report = importAndPrune();

        expect(Post::count())->toBe(0)
            ->and($report->removedItems())->toHaveCount(1);
    } finally {
        Relation::morphMap([], false);
    }
});

it('names what it removed, also without a file, an author or a short title', function () {
    config()->set('wp-migrator.comments.enabled', true);
    $long = $this->site->post(['post_title' => str_repeat('a', 100), 'post_author' => 0]);
    $kept = $this->site->post(['post_title' => 'Kept', 'post_author' => 0]);
    $fileless = $this->site->attachment(['post_title' => 'No file', 'post_author' => 0]);
    $comment = $this->site->comment($kept, ['comment_author' => '']);
    importWithoutPruning();
    deleteSourcePost($this->site, $long);
    deleteSourcePost($this->site, $fileless);
    $this->site->table('comments')->where('comment_ID', $comment)->delete();

    $report = importAndPrune();

    expect($report->removedItems())->toBe([
        ['kind' => 'comments', 'source_id' => $comment, 'what' => 'comment'],
        ['kind' => 'posts', 'source_id' => $long, 'what' => 'post "'.str_repeat('a', 80).'..."'],
        ['kind' => 'media', 'source_id' => $fileless, 'what' => '"No file"'],
    ]);
});

it('keeps the redirects to a URL that other content still has', function () {
    config()->set('wp-migrator.urls.patterns.event', '/events');
    $gone = $this->site->post(['post_title' => 'First', 'post_type' => 'event', 'post_author' => 0]);
    $kept = $this->site->post(['post_title' => 'Second', 'post_type' => 'event', 'post_author' => 0]);
    importWithoutPruning();
    expect(Redirect::query()->orderBy('from_path')->pluck('to_path', 'from_path')->all())->toBe(['/?p='.$gone => '/events', '/?p='.$kept => '/events']);
    Redirect::query()->update(['hits' => 3]);
    deleteSourcePost($this->site, $gone);

    $report = importAndPrune();

    expect(Post::query()->pluck('title')->all())->toBe(['Second'])
        ->and(Redirect::query()->orderBy('from_path')->pluck('hits', 'from_path')->all())->toBe(['/?p='.$gone => 3, '/?p='.$kept => 3])
        ->and($report->removedRedirects())->toBe(0);
});

it('removes the redirects that pointed to removed content', function () {
    $this->site->option('permalink_structure', '/%postname%/');
    $gone = $this->site->post(['post_title' => 'Gone', 'post_name' => 'gone', 'post_author' => 0]);
    $keptPost = $this->site->post(['post_title' => 'Kept', 'post_name' => 'kept', 'post_author' => 0]);
    $page = $this->site->page(['post_title' => 'Old page', 'post_name' => 'old-page', 'post_author' => 0]);
    $category = $this->site->term('category', 'News');
    $tag = $this->site->term('post_tag', 'PHP', ['slug' => 'php']);
    $this->site->term('post_tag', 'Laravel', ['slug' => 'laravel']);
    $image = sourceImage($this->site, $this->uploads, 'photo');
    importWithoutPruning();
    Redirect::query()->create(['from_path' => '/promo', 'to_path' => '/blog/gone']);
    Redirect::query()->create(['from_path' => '/manual', 'to_path' => '/somewhere-else']);
    $kept = Redirect::query()->where('from_path', '/kept')->sole();
    $kept->update(['hits' => 7]);
    $before = Redirect::count();
    expect(Redirect::query()->orderBy('from_path')->pluck('from_path')->all())->toContain(
        '/gone', '/?p='.$gone, '/?page_id='.$page, '/category/news', '/?cat='.$category, '/tag/php', '/?tag=php', '/photo', '/?attachment_id='.$image,
    );
    deleteSourcePost($this->site, $gone);
    deleteSourcePost($this->site, $page);
    deleteSourcePost($this->site, $image);
    deleteSourceTerm($this->site, $category);
    deleteSourceTerm($this->site, $tag);

    $report = importAndPrune();

    $left = Redirect::query()->pluck('to_path', 'from_path')->all();
    ksort($left);

    expect(array_keys($left))->toBe(['/?p='.$keptPost, '/?tag=laravel', '/kept', '/manual', '/tag/laravel'])
        ->and($left['/manual'])->toBe('/somewhere-else')
        ->and($kept->fresh()->hits)->toBe(7)
        ->and($report->removedRedirects())->toBe($before - 5)
        ->and($report->toMarkdown())->toContain(sprintf('- redirects: %d that pointed to them', $before - 5));
});

it('shows in a dry run what it would remove and removes nothing', function () {
    $gone = $this->site->post(['post_title' => 'Gone', 'post_author' => 0]);
    $image = sourceImage($this->site, $this->uploads);
    importWithoutPruning();
    deleteSourcePost($this->site, $gone);
    deleteSourcePost($this->site, $image);
    $asked = false;

    $report = importAndPrune(function () use (&$asked): bool {
        $asked = true;

        return true;
    }, dryRun: true);

    expect(Post::count())->toBe(1)
        ->and(Media::count())->toBe(1)
        ->and(mappedIds(SourceType::Post))->toBe([$gone, $image])
        ->and($asked)->toBeFalse()
        ->and(array_column($report->removedItems(), 'kind'))->toBe(['posts', 'media'])
        ->and($report->toMarkdown())->toContain('## Would be removed (2)');

    Storage::disk('public')->assertExists(['wp-uploads/2026/01/hero.jpg', 'wp-uploads/2026/01/hero-150x150.jpg']);
});

it('asks before it removes anything and leaves everything when the answer is no', function () {
    $gone = $this->site->post(['post_title' => 'Gone', 'post_author' => 0]);
    $kept = $this->site->page(['post_title' => 'Kept', 'post_author' => 0]);
    $term = $this->site->term('category', 'News');
    $image = sourceImage($this->site, $this->uploads);
    importWithoutPruning();
    deleteSourcePost($this->site, $gone);
    deleteSourcePost($this->site, $image);
    deleteSourceTerm($this->site, $term);
    $redirects = Redirect::count();
    $seen = [];

    $report = importAndPrune(function (array $counts) use (&$seen): bool {
        $seen[] = $counts;

        return false;
    });

    expect($seen)->toBe([['posts' => 1, 'terms' => 1, 'media' => 1]])
        ->and(Post::count())->toBe(2)
        ->and(Term::count())->toBe(1)
        ->and(Media::count())->toBe(1)
        ->and(Redirect::count())->toBe($redirects)
        ->and(mappedIds(SourceType::Post))->toBe([$gone, $kept, $image])
        ->and($report->removedItems())->toBe([])
        ->and($report->toMarkdown())->toContain('- Prune: declined, nothing was removed');

    Storage::disk('public')->assertExists('wp-uploads/2026/01/hero.jpg');

    $report = importAndPrune(fn (array $counts): bool => true);

    expect(Post::count())->toBe(1)
        ->and(Term::count())->toBe(0)
        ->and(Media::count())->toBe(0)
        ->and($report->removedItems())->toHaveCount(3)
        ->and($report->toMarkdown())->toContain('- Prune: content that is gone from the old site was removed');
});

it('does not ask when nothing is gone', function () {
    $this->site->post(['post_title' => 'Kept', 'post_author' => 0]);
    importWithoutPruning();
    $asked = false;

    importAndPrune(function () use (&$asked): bool {
        $asked = true;

        return true;
    });

    expect($asked)->toBeFalse()
        ->and(Post::count())->toBe(1);
});
