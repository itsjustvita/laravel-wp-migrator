<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ItsJustVita\WpMigrator\Facades\WpMigrator;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\ImportReport;
use ItsJustVita\WpMigrator\Import\ImportRunner;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Mapping\DefaultPostMapper;
use ItsJustVita\WpMigrator\Mapping\PostMapper;
use ItsJustVita\WpMigrator\Mapping\UserMapper;
use ItsJustVita\WpMigrator\Models\ImportMapEntry;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Models\SeoMeta;
use ItsJustVita\WpMigrator\Models\Term;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;
use ItsJustVita\WpMigrator\Source\Dto\SourceUser;
use ItsJustVita\WpMigrator\Tests\Support\Event;
use ItsJustVita\WpMigrator\Tests\Support\SourceSite;
use ItsJustVita\WpMigrator\Tests\Support\User;
use ItsJustVita\WpMigrator\Tests\TestCase;

final class EventPostMapper implements PostMapper
{
    public function map(SourcePost $post, ImportContext $context): ?Model
    {
        if ($post->status !== 'publish') {
            return null;
        }

        $event = $context->existing(SourceType::Post, $post->id, Event::class) ?? new Event;
        $event->fill(['title' => $post->title, 'starts_at' => $post->meta->get('starts_at')])->save();

        return $event;
    }
}

final class ExplodingPostMapper extends DefaultPostMapper
{
    public function map(SourcePost $post, ImportContext $context): ?Model
    {
        if ($post->title === 'Boom') {
            parent::map($post, $context);

            throw new RuntimeException('Mapper exploded');
        }

        return parent::map($post, $context);
    }
}

final class RenamingPostMapper extends DefaultPostMapper {}

final class BlockReadingMapper implements PostMapper
{
    public function map(SourcePost $post, ImportContext $context): ?Model
    {
        $event = new Event;
        $event->fill(['title' => $post->blocks[0]->innerHtml ?? 'none'])->save();

        return $event;
    }
}

final class ForgetfulUserMapper implements UserMapper
{
    public function map(SourceUser $user, ImportContext $context): ?Model
    {
        return User::query()->where('email', $user->email)->first();
    }
}

/**
 * @return array<string, int>
 */
function seedSite(SourceSite $site, string $uploads): array
{
    $author = $site->user(['user_login' => 'alice', 'user_email' => 'alice@example.com', 'display_name' => 'Alice']);
    $site->userMeta($author, 'wp_capabilities', serialize(['editor' => true]));

    $image = $site->attachment(['post_title' => 'Hero', 'post_author' => $author]);
    $site->postMeta($image, '_wp_attached_file', '2026/01/hero.jpg');
    File::ensureDirectoryExists($uploads.'/2026/01');
    file_put_contents($uploads.'/2026/01/hero.jpg', 'jpg-bytes');

    $news = $site->term('category', 'News');

    // The child gets the lower ID so ID order alone would import it first.
    $child = $site->page(['post_title' => 'Team', 'post_author' => $author]);
    $parent = $site->page(['post_title' => 'Company', 'post_author' => $author]);
    $site->table('posts')->where('ID', $child)->update(['post_parent' => $parent]);
    $site->postMeta($child, '_thumbnail_id', (string) $image);

    $post = $site->post(['post_title' => 'Hello', 'post_author' => $author]);
    $site->attachTerm($post, $news);

    return compact('author', 'image', 'news', 'child', 'parent', 'post');
}

function runImport(ImportOptions $options = new ImportOptions): ImportReport
{
    return app(ImportRunner::class)->run($options);
}

beforeEach(function () {
    Storage::fake('public');
    $this->uploads = sys_get_temp_dir().'/wp-migrator-uploads-'.uniqid();
    config()->set('wp-migrator.source.uploads_path', $this->uploads);
    config()->set('wp-migrator.media.download_missing', false);
});

afterEach(function () {
    File::deleteDirectory($this->uploads);
});

it('imports users, media, terms and posts in dependency order', function () {
    seedSite($this->source(), $this->uploads);

    $report = runImport();

    expect(array_keys($report->counts()))->toBe(['users', 'media', 'terms', 'posts', 'menus', 'menu items', 'navigation menus', 'redirects'])
        ->and($report->counts())->toMatchArray([
            'users' => ['created' => 1, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0],
            'media' => ['created' => 1, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0],
            'terms' => ['created' => 1, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0],
            'posts' => ['created' => 3, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0],
        ])
        ->and($report->counts()['redirects']['created'])->toBeGreaterThan(0)
        ->and($report->unresolvedReferences())->toBe([])
        ->and($report->errors())->toBe([]);

    $alice = User::query()->where('email', 'alice@example.com')->sole();
    $team = Post::query()->where('title', 'Team')->sole();
    $company = Post::query()->where('title', 'Company')->sole();
    $hello = Post::query()->where('title', 'Hello')->sole();

    expect($team->parent_id)->toBe($company->id)
        ->and($team->author_id)->toBe($alice->id)
        ->and($team->featuredMedia?->path)->toBe('wp-uploads/2026/01/hero.jpg')
        ->and($hello->terms->pluck('slug')->all())->toBe(['news'])
        ->and(Term::count())->toBe(1);

    Storage::disk('public')->assertExists('wp-uploads/2026/01/hero.jpg');
});

it('skips unchanged rows on a second run', function () {
    seedSite($this->source(), $this->uploads);
    runImport();

    $report = runImport();

    expect(array_map(fn (array $counts) => $counts['unchanged'], $report->counts()))
        ->toMatchArray(['users' => 1, 'media' => 1, 'terms' => 1, 'posts' => 3])
        ->and(array_sum(array_map(fn (array $counts) => $counts['created'] + $counts['updated'], $report->counts())))->toBe(0)
        ->and(Post::count())->toBe(3)
        ->and(Media::count())->toBe(1)
        ->and(User::count())->toBe(1)
        ->and(ImportMapEntry::count())->toBe(6);
});

it('updates rows that changed since the last run', function () {
    $ids = seedSite($site = $this->source(), $this->uploads);
    runImport();
    $site->table('posts')->where('ID', $ids['post'])->update(['post_title' => 'Hello again', 'post_modified_gmt' => '2026-02-01 10:00:00']);

    $report = runImport();

    expect($report->counts()['posts'])->toMatchArray(['created' => 0, 'updated' => 1, 'unchanged' => 2])
        ->and(Post::query()->where('title', 'Hello again')->exists())->toBeTrue()
        ->and(Post::count())->toBe(3);
});

it('maps rows with unresolved references again on the next run', function () {
    seedSite($this->source(), $this->uploads);

    $first = runImport(new ImportOptions(only: ['posts', 'pages']));

    expect(array_column($first->unresolvedReferences(), 'kind'))->toContain('author', 'featured media', 'term')
        ->and(Post::query()->whereNotNull('author_id')->count())->toBe(0);

    $second = runImport();

    expect($second->counts()['posts']['updated'])->toBe(3)
        ->and($second->unresolvedReferences())->toBe([])
        ->and(Post::query()->whereNotNull('author_id')->count())->toBe(3)
        ->and(Post::query()->where('title', 'Team')->sole()->featured_media_id)->not->toBeNull();
});

it('re-maps everything with force', function () {
    seedSite($this->source(), $this->uploads);
    runImport();

    $report = runImport(new ImportOptions(force: true));

    expect($report->counts()['posts'])->toMatchArray(['updated' => 3, 'unchanged' => 0])
        ->and(Post::count())->toBe(3);
});

it('re-maps rows when the mapper for their type changes', function () {
    seedSite($this->source(), $this->uploads);
    runImport();
    WpMigrator::mapPostType('post', RenamingPostMapper::class);

    $report = runImport();

    expect($report->counts()['posts'])->toMatchArray(['updated' => 1, 'unchanged' => 2]);
});

it('limits posts and media to rows modified since a date', function () {
    $site = $this->source();
    $site->post(['post_title' => 'Recent']);
    $site->post(['post_title' => 'Old', 'post_modified_gmt' => '2025-06-01 00:00:00']);
    $oldImage = $site->attachment(['post_modified_gmt' => '2025-06-01 00:00:00']);
    $site->postMeta($oldImage, '_wp_attached_file', '2025/06/old.jpg');

    $report = runImport(new ImportOptions(since: CarbonImmutable::parse('2026-01-01')));

    expect(Post::query()->pluck('title')->all())->toBe(['Recent'])
        ->and($report->counts()['media']['created'])->toBe(0);
});

it('selects importers and post types', function () {
    $site = $this->source();
    $site->post(['post_title' => 'A post']);
    $site->page(['post_title' => 'A page']);
    $site->post(['post_title' => 'An event', 'post_type' => 'event']);
    $site->user();

    $pages = runImport(new ImportOptions(only: ['pages']));

    expect(array_keys($pages->counts()))->toBe(['posts'])
        ->and(Post::query()->pluck('title')->all())->toBe(['A page'])
        ->and(User::count())->toBe(0);

    runImport(new ImportOptions(only: ['posts']));

    expect(Post::query()->orderBy('id')->pluck('title')->all())->toBe(['A page', 'A post', 'An event']);
});

it('imports only the given post types', function () {
    $site = $this->source();
    $site->post(['post_title' => 'A post']);
    $site->post(['post_title' => 'An event', 'post_type' => 'event']);

    runImport(new ImportOptions(only: ['posts'], types: ['event']));

    expect(Post::query()->pluck('title')->all())->toBe(['An event']);
});

it('writes nothing during a dry run but reports what it would do', function () {
    seedSite($this->source(), $this->uploads);

    $report = runImport(new ImportOptions(dryRun: true));

    expect($report->dryRun)->toBeTrue()
        ->and($report->counts()['posts']['created'])->toBe(3)
        ->and($report->counts()['users']['created'])->toBe(1)
        ->and($report->unresolvedReferences())->toBe([])
        ->and(Post::count())->toBe(0)
        ->and(User::count())->toBe(0)
        ->and(Media::count())->toBe(0)
        ->and(ImportMapEntry::count())->toBe(0);

    Storage::disk('public')->assertMissing('wp-uploads/2026/01/hero.jpg');
});

it('reports a failing row, rolls it back and carries on', function () {
    $site = $this->source();
    $boom = $site->post(['post_title' => 'Boom']);
    $site->post(['post_title' => 'Fine']);
    WpMigrator::mapPostType('post', ExplodingPostMapper::class);

    $report = runImport(new ImportOptions(only: ['posts']));

    expect($report->counts()['posts'])->toMatchArray(['created' => 1, 'failed' => 1])
        ->and($report->errors()[0])->toMatchArray(['importer' => 'posts', 'source_id' => $boom, 'message' => 'Mapper exploded'])
        ->and(Post::query()->pluck('title')->all())->toBe(['Fine'])
        ->and(ImportMapEntry::count())->toBe(1);
});

it('maps a custom post type onto an application model and skips what the mapper declines', function () {
    Schema::create('events', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('starts_at')->nullable();
        $table->timestamps();
    });
    WpMigrator::mapPostType('event', EventPostMapper::class);
    $site = $this->source();
    $concert = $site->post(['post_title' => 'Concert', 'post_type' => 'event']);
    $site->postMeta($concert, 'starts_at', '2026-03-01 20:00');
    $draft = $site->post(['post_title' => 'Planned', 'post_type' => 'event', 'post_status' => 'draft']);

    $report = runImport(new ImportOptions(only: ['posts']));

    expect(Event::query()->sole()->starts_at)->toBe('2026-03-01 20:00')
        ->and(Post::count())->toBe(0)
        ->and(ImportMapEntry::query()->where('source_id', $concert)->value('target_type'))->toBe(Event::class)
        ->and($report->counts()['posts'])->toMatchArray(['created' => 1, 'skipped' => 1])
        ->and($report->skippedItems()[0]['source_id'])->toBe($draft);
});

it('adds unsupported content to the report', function () {
    $site = $this->source();
    $landing = $site->page(['post_title' => 'Landing']);
    $site->postMeta($landing, '_elementor_data', '[{"id":"x"}]');

    $report = runImport(new ImportOptions(only: ['pages']));

    expect(array_map(fn ($finding) => $finding->key, $report->findings()))->toBe(['elementor'])
        ->and($report->toMarkdown())->toContain('**Elementor**: 1 post (ID '.$landing.')');
});

it('never changes a linked user, not even when the source row changes later', function () {
    $existing = User::create(['name' => 'App Admin', 'email' => 'alice@example.com', 'password' => 'secret']);
    $appHash = User::query()->value('password');
    $site = $this->source();
    $id = $site->user(['user_login' => 'alice', 'user_email' => 'alice@example.com', 'display_name' => 'Alice']);

    runImport(new ImportOptions(only: ['users']));
    $site->table('users')->where('ID', $id)->update(['display_name' => 'Alice Changed', 'user_pass' => '\$P\$changed']);
    runImport(new ImportOptions(only: ['users']));

    expect($existing->fresh()->name)->toBe('App Admin')
        ->and(User::query()->value('password'))->toBe($appHash)
        ->and(User::count())->toBe(1)
        ->and(ImportMapEntry::query()->where('source_id', $id)->sole()->linked)->toBeTrue();
});

it('resumes after an aborted run without importing finished rows again', function () {
    seedSite($this->source(), $this->uploads);
    runImport(new ImportOptions(only: ['users', 'media']));

    $report = runImport();

    expect($report->counts()['users'])->toMatchArray(['created' => 0, 'unchanged' => 1])
        ->and($report->counts()['media'])->toMatchArray(['created' => 0, 'unchanged' => 1])
        ->and($report->counts()['posts']['created'])->toBe(3);
});

it('keeps a row linked even when a custom mapper does not repeat it on later runs', function () {
    User::create(['name' => 'App Admin', 'email' => 'alice@example.com', 'password' => 'secret']);
    $id = $this->source()->user(['user_login' => 'alice', 'user_email' => 'alice@example.com']);
    runImport(new ImportOptions(only: ['users']));
    WpMigrator::mapUsers(ForgetfulUserMapper::class);

    runImport(new ImportOptions(only: ['users'], force: true));

    expect(ImportMapEntry::query()->where('source_id', $id)->sole()->linked)->toBeTrue();
});

it('leaves pages out when only posts are selected', function () {
    $site = $this->source();
    $site->post(['post_title' => 'A post']);
    $site->page(['post_title' => 'A page']);

    runImport(new ImportOptions(only: ['posts']));

    expect(Post::query()->pluck('title')->all())->toBe(['A post']);
});

it('reports a row with invalid UTF-8 instead of aborting the run', function () {
    $site = $this->source();
    $broken = $site->post(['post_title' => 'Broken']);
    $site->postMeta($broken, 'bad', "\xB1\x31");
    $site->post(['post_title' => 'Fine']);

    $report = runImport(new ImportOptions(only: ['posts']));

    expect($report->counts()['posts'])->toMatchArray(['created' => 1, 'failed' => 1])
        ->and($report->errors()[0]['source_id'])->toBe($broken);
})->skip(fn () => TestCase::usesServerDatabase(), 'MySQL and MariaDB refuse to store invalid UTF-8');

it('stores the raw content and the transformed blocks', function () {
    $site = $this->source();
    $site->user();
    $site->option('home', 'https://old.example.com');
    $site->option('permalink_structure', '/%postname%/');
    $image = $site->attachment();
    $site->postMeta($image, '_wp_attached_file', '2026/01/hero.jpg');
    File::ensureDirectoryExists($this->uploads.'/2026/01');
    file_put_contents($this->uploads.'/2026/01/hero.jpg', 'jpg');

    $target = $site->post(['post_name' => 'target', 'post_title' => 'Target']);
    $classic = "One\n\n<a href=\"https://old.example.com/target/\">Two</a> [foo]";
    $classicId = $site->post(['post_title' => 'Classic', 'post_content' => $classic]);
    $blocks = '<!-- wp:image {"id":'.$image.'} --><figure><img src="https://old.example.com/wp-content/uploads/2026/01/hero.jpg"/></figure><!-- /wp:image --><!-- wp:acme/x -->y<!-- /wp:acme/x -->';
    $blocksId = $site->post(['post_title' => 'Blocks', 'post_content' => $blocks]);

    $report = runImport();

    $classicPost = Post::query()->where('title', 'Classic')->sole();
    $blocksPost = Post::query()->where('title', 'Blocks')->sole();
    $media = Media::query()->sole();

    expect($classicPost->content_raw)->toBe($classic)
        ->and($classicPost->blocks)->toBe([[
            'name' => 'core/freeform',
            'attrs' => [],
            'innerHTML' => "<p>One</p>\n<p><a href=\"/blog/target\">Two</a> [foo]</p>",
            'innerBlocks' => [],
        ]])
        ->and($blocksPost->content_raw)->toBe($blocks)
        ->and($blocksPost->blocks[0]['attrs'])->toBe(['id' => $media->id])
        ->and($blocksPost->blocks[0]['innerHTML'])->toBe('<figure><img src="/storage/wp-uploads/2026/01/hero.jpg"/></figure>')
        ->and($report->unknownShortcodes())->toBe(['foo' => ['count' => 1, 'post_ids' => [$classicId]]])
        ->and($report->unknownBlocks())->toBe(['acme/x' => ['count' => 1, 'post_ids' => [$blocksId]]])
        ->and($report->unresolvedReferences())->toBe([]);

    expect(Post::query()->where('title', 'Target')->exists())->toBeTrue()
        ->and($target)->toBeInt();
});

it('resolves a link to a post that is imported later in the same run', function () {
    $site = $this->source();
    $site->option('home', 'https://old.example.com');
    $site->option('permalink_structure', '/%postname%/');
    $first = $site->post(['post_title' => 'First', 'post_author' => 0, 'post_content' => '<a href="/second/">x</a>']);
    $site->post(['post_title' => 'Second', 'post_author' => 0, 'post_name' => 'second']);

    $report = runImport(new ImportOptions(only: ['posts']));

    expect($report->unresolvedReferences())->toBe([])
        ->and(Post::query()->where('title', 'First')->sole()->blocks[0]['innerHTML'])->toBe('<p><a href="/blog/second">x</a></p>')
        ->and($first)->toBeInt();
});

it('hands the transformed blocks to a custom mapper', function () {
    Schema::create('events', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('starts_at')->nullable();
        $table->timestamps();
    });
    WpMigrator::mapPostType('event', BlockReadingMapper::class);
    $this->source()->post(['post_title' => 'Gig', 'post_type' => 'event', 'post_content' => 'Hello']);

    runImport(new ImportOptions(only: ['posts']));

    expect(Event::query()->sole()->title)->toBe('<p>Hello</p>');
});

it('imports media before posts so block media resolves in one run and stays stable', function () {
    $site = $this->source();
    $site->user();
    $site->option('home', 'https://old.example.com');
    $image = $site->attachment();
    $site->postMeta($image, '_wp_attached_file', '2026/01/hero.jpg');
    File::ensureDirectoryExists($this->uploads.'/2026/01');
    file_put_contents($this->uploads.'/2026/01/hero.jpg', 'jpg');
    $site->post(['post_title' => 'A', 'post_content' => '<!-- wp:image {"id":'.$image.'} /-->']);

    $first = runImport();
    $second = runImport();

    expect($first->unresolvedReferences())->toBe([])
        ->and($first->counts()['posts']['created'])->toBe(1)
        ->and($second->counts()['posts'])->toMatchArray(['updated' => 0, 'unchanged' => 1]);
});

it('maps a post again once the media it needs has been imported', function () {
    $site = $this->source();
    $site->user();
    $site->option('home', 'https://old.example.com');
    $image = $site->attachment();
    $site->postMeta($image, '_wp_attached_file', '2026/01/hero.jpg');
    File::ensureDirectoryExists($this->uploads.'/2026/01');
    file_put_contents($this->uploads.'/2026/01/hero.jpg', 'jpg');
    $site->post(['post_title' => 'A', 'post_content' => '<!-- wp:image {"id":'.$image.'} --><img src="/wp-content/uploads/2026/01/hero.jpg"/><!-- /wp:image -->']);

    $first = runImport(new ImportOptions(only: ['posts']));
    $second = runImport();

    expect(array_column($first->unresolvedReferences(), 'kind'))->toContain('block media', 'media url')
        ->and($second->counts()['posts']['updated'])->toBe(1)
        ->and($second->unresolvedReferences())->toBe([])
        ->and(Post::query()->sole()->blocks[0]['innerHTML'])->toBe('<img src="/storage/wp-uploads/2026/01/hero.jpg"/>');
});

it('stores very deep block nesting without exceeding the json depth of the database', function () {
    $depth = 200;
    $content = str_repeat('<!-- wp:group {"style":{"spacing":{"padding":{"top":"1px"}}}} --><div>', $depth)
        .'<!-- wp:paragraph --><p>Core</p><!-- /wp:paragraph -->'
        .str_repeat('</div><!-- /wp:group -->', $depth);
    $this->source()->post(['post_title' => 'Deep', 'post_author' => 0, 'post_content' => $content]);

    $report = runImport(new ImportOptions(only: ['posts']));

    expect($report->errors())->toBe([])
        ->and(json_encode(Post::query()->sole()->blocks))->toContain('Core');
});

it('stores multibyte text, emoji and quotes in the json column', function () {
    $html = '<p>Grüße 👋 "quoted" \\ \'single\' </p>';
    $this->source()->post(['post_title' => 'Text', 'post_author' => 0, 'post_content' => '<!-- wp:paragraph {"note":"Grüße 👋"} -->'.$html.'<!-- /wp:paragraph -->']);

    $report = runImport(new ImportOptions(only: ['posts']));

    $block = Post::query()->sole()->blocks[0];

    expect($report->errors())->toBe([])
        ->and($block['innerHTML'])->toBe($html)
        ->and($block['attrs']['note'])->toBe('Grüße 👋');
});

it('resolves references to posts imported later in the same run', function () {
    $site = $this->source();
    $group = $site->acfGroup();
    $key = $site->acfField($group, 'related', 'relationship');
    $first = $site->post(['post_title' => 'First', 'post_author' => 0]);
    $second = $site->post(['post_title' => 'Second', 'post_author' => 0]);
    $site->acfValue($first, 'related', $key, serialize([(string) $second]));
    $site->acfValue($second, 'related', $key, serialize([(string) $first]));

    $report = runImport(new ImportOptions(only: ['posts']));
    $again = runImport(new ImportOptions(only: ['posts']));

    $firstPost = Post::query()->where('title', 'First')->sole();
    $secondPost = Post::query()->where('title', 'Second')->sole();

    expect($report->counts()['posts'])->toMatchArray(['created' => 2, 'updated' => 0])
        ->and($report->unresolvedReferences())->toBe([])
        ->and($firstPost->fields)->toBe(['related' => [$secondPost->id]])
        ->and($secondPost->fields)->toBe(['related' => [$firstPost->id]])
        ->and($again->counts()['posts'])->toMatchArray(['unchanged' => 2, 'updated' => 0]);
});

it('reports a reference once when it stays unresolved after the second pass', function () {
    $site = $this->source();
    $key = $site->acfField($site->acfGroup(), 'related', 'relationship');
    $post = $site->post(['post_title' => 'Lonely', 'post_author' => 0]);
    $site->acfValue($post, 'related', $key, serialize(['999']));
    $site->postMeta($post, 'broken', 'a:1:{x');

    $report = runImport(new ImportOptions(only: ['posts']));

    expect($report->counts()['posts'])->toMatchArray(['created' => 1, 'updated' => 0])
        ->and($report->unresolvedReferences())->toHaveCount(1)
        ->and($report->warnings())->toHaveCount(1);
});

it('stores the seo settings for the model a post was mapped onto', function () {
    Schema::create('events', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('starts_at')->nullable();
        $table->timestamps();
    });
    WpMigrator::mapPostType('event', EventPostMapper::class);
    $site = $this->source();
    $site->option('blogname', 'Acme');
    $post = $site->post(['post_title' => 'News', 'post_author' => 0]);
    $site->postMeta($post, '_yoast_wpseo_title', '%%title%% %%sep%% %%sitename%%');
    $event = $site->post(['post_title' => 'Gig', 'post_type' => 'event', 'post_author' => 0]);
    $site->postMeta($event, 'rank_math_description', 'Live music');

    runImport(new ImportOptions(only: ['posts']));

    expect(Post::query()->sole()->seo?->title)->toBe('News - Acme')
        ->and(SeoMeta::query()->whereMorphedTo('seoable', Event::query()->sole())->value('description'))->toBe('Live music');
});

it('reads the source afresh on every run in the same process', function () {
    $site = $this->source();
    $site->option('home', 'https://old.example.com');
    $site->option('permalink_structure', '/%postname%/');
    $site->post(['post_title' => 'Target', 'post_name' => 'first-slug', 'post_author' => 0]);
    $linking = $site->post(['post_title' => 'Linking', 'post_author' => 0, 'post_content' => '<a href="/first-slug/">x</a>']);
    runImport(new ImportOptions(only: ['posts']));

    $site->table('posts')->where('post_title', 'Target')->update(['post_name' => 'second-slug', 'post_modified_gmt' => '2026-02-01 00:00:00']);
    $site->table('posts')->where('ID', $linking)->update(['post_content' => '<a href="/second-slug/">x</a>', 'post_modified_gmt' => '2026-02-01 00:00:00']);
    $report = runImport(new ImportOptions(only: ['posts']));

    expect($report->unresolvedReferences())->toBe([])
        ->and(Post::query()->where('title', 'Linking')->sole()->blocks[0]['innerHTML'])->toContain('/blog/second-slug');
});
