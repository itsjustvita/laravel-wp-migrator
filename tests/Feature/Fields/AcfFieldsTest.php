<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use ItsJustVita\WpMigrator\Fields\FieldResolver;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Models\Term;
use ItsJustVita\WpMigrator\Source\Readers\PostReader;
use ItsJustVita\WpMigrator\Tests\Support\User;

/**
 * @return array<string, mixed>
 */
function resolveFields(int $postId, ?ImportContext $context = null): array
{
    $post = app(PostReader::class)->byIds([$postId])[0];

    return app(FieldResolver::class)->resolve($post, $context ?? importContext('posts'));
}

beforeEach(function () {
    Storage::fake('public');
    $this->site = $this->source();
    $this->site->option('home', 'https://old.example.com');
    $this->site->option('permalink_structure', '/%postname%/');
    $this->group = $this->site->acfGroup();
    $this->post = $this->site->post(['post_title' => 'With fields']);
});

it('converts scalar field types', function (string $type, string $raw, mixed $expected, array $settings = []) {
    $key = $this->site->acfField($this->group, 'value', $type, $settings);
    $this->site->acfValue($this->post, 'value', $key, $raw);

    expect(resolveFields($this->post))->toBe(['value' => $expected]);
})->with([
    'text' => ['text', 'Hello', 'Hello'],
    'textarea' => ['textarea', "a\nb", "a\nb"],
    'true' => ['true_false', '1', true],
    'false' => ['true_false', '0', false],
    'empty boolean' => ['true_false', '', false],
    'integer' => ['number', '42', 42],
    'negative' => ['number', '-3', -3],
    'decimal' => ['number', '4.5', 4.5],
    'empty number' => ['number', '', null],
    'range' => ['range', '7', 7],
    'date' => ['date_picker', '20260115', '2026-01-15'],
    'iso date' => ['date_picker', '2026-01-15', '2026-01-15'],
    'empty date' => ['date_picker', '', null],
    'time' => ['time_picker', '18:30:00', '18:30:00'],
    'checkbox' => ['checkbox', serialize(['red', 'blue']), ['red', 'blue']],
    'empty checkbox' => ['checkbox', '', []],
    'select' => ['select', 'red', 'red'],
    'multiple select' => ['select', serialize(['red', 'blue']), ['red', 'blue']],
    'radio' => ['radio', 'a', 'a'],
    'color' => ['color_picker', '#ff0000', '#ff0000'],
    'google map' => ['google_map', serialize(['lat' => '52.5', 'lng' => '13.4']), ['lat' => '52.5', 'lng' => '13.4']],
    'unknown type' => ['vendor_special', serialize(['a' => 1]), ['a' => 1]],
]);

it('reads date and time values in the site timezone', function () {
    $this->site->option('timezone_string', 'Europe/Berlin');
    $key = $this->site->acfField($this->group, 'starts', 'date_time_picker');
    $this->site->acfValue($this->post, 'starts', $key, '2026-07-15 20:00:00');

    expect(resolveFields($this->post))->toBe(['starts' => '2026-07-15T20:00:00+02:00']);
});

it('keeps dates it cannot read and warns', function () {
    $key = $this->site->acfField($this->group, 'day', 'date_picker');
    $this->site->acfValue($this->post, 'day', $key, 'someday');
    $context = importContext('posts');

    expect(resolveFields($this->post, $context))->toBe(['day' => 'someday'])
        ->and($context->report->warnings()[0]['message'])->toContain('day');
});

it('maps image, file and gallery ids to media ids', function () {
    $context = importContext('posts');
    $a = Media::create(['disk' => 'public', 'path' => 'a.jpg']);
    $b = Media::create(['disk' => 'public', 'path' => 'b.jpg']);
    $context->map->record(SourceType::Post, 501, $a, 'x');
    $context->map->record(SourceType::Post, 502, $b, 'x');
    $this->site->acfValue($this->post, 'hero', $this->site->acfField($this->group, 'hero', 'image'), '501');
    $this->site->acfValue($this->post, 'brochure', $this->site->acfField($this->group, 'brochure', 'file'), '502');
    $this->site->acfValue($this->post, 'photos', $this->site->acfField($this->group, 'photos', 'gallery'), serialize(['502', '501']));
    $this->site->acfValue($this->post, 'empty', $this->site->acfField($this->group, 'empty', 'image'), '');

    expect(resolveFields($this->post, $context))->toBe([
        'hero' => $a->id,
        'brochure' => $b->id,
        'photos' => [$b->id, $a->id],
        'empty' => null,
    ])->and($context->report->unresolvedReferences())->toBe([]);
});

it('reports media it cannot resolve and maps the row again later', function () {
    $context = importContext('posts');
    $this->site->acfValue($this->post, 'hero', $this->site->acfField($this->group, 'hero', 'image'), '777');
    $this->site->acfValue($this->post, 'photos', $this->site->acfField($this->group, 'photos', 'gallery'), serialize(['778']));

    expect(resolveFields($this->post, $context))->toBe(['hero' => null, 'photos' => []])
        ->and(array_column($context->report->unresolvedReferences(), 'kind'))->toBe(['acf media', 'acf media'])
        ->and($context->rowIncomplete())->toBeTrue();
});

it('maps post, user and term references to their targets', function () {
    $context = importContext('posts');
    $other = Post::create(['type' => 'post', 'status' => 'publish', 'title' => 'Other', 'slug' => 'other']);
    $third = Post::create(['type' => 'post', 'status' => 'publish', 'title' => 'Third', 'slug' => 'third']);
    $user = User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);
    $term = Term::create(['taxonomy' => 'category', 'name' => 'News', 'slug' => 'news']);
    $context->map->record(SourceType::Post, 601, $other, 'x');
    $context->map->record(SourceType::Post, 602, $third, 'x');
    $context->map->record(SourceType::User, 7, $user, 'x');
    $context->map->record(SourceType::Term, 8, $term, 'x');

    $fields = [
        'single' => ['post_object', '601'],
        'several' => ['post_object', serialize(['602', '601'])],
        'related' => ['relationship', serialize(['601'])],
        'page' => ['page_link', '602'],
        'person' => ['user', '7'],
        'people' => ['user', serialize(['7'])],
        'topic' => ['taxonomy', '8'],
        'topics' => ['taxonomy', serialize(['8'])],
    ];

    foreach ($fields as $name => [$type, $raw]) {
        $this->site->acfValue($this->post, $name, $this->site->acfField($this->group, $name, $type), $raw);
    }

    expect(resolveFields($this->post, $context))->toBe([
        'single' => $other->id,
        'several' => [$third->id, $other->id],
        'related' => [$other->id],
        'page' => $third->id,
        'person' => $user->id,
        'people' => [$user->id],
        'topic' => $term->id,
        'topics' => [$term->id],
    ])->and($context->report->unresolvedReferences())->toBe([]);
});

it('reports references it cannot resolve', function () {
    $context = importContext('posts');
    $this->site->acfValue($this->post, 'related', $this->site->acfField($this->group, 'related', 'relationship'), serialize(['901']));
    $this->site->acfValue($this->post, 'person', $this->site->acfField($this->group, 'person', 'user'), '902');
    $this->site->acfValue($this->post, 'topic', $this->site->acfField($this->group, 'topic', 'taxonomy'), '903');

    expect(resolveFields($this->post, $context))->toBe(['related' => [], 'person' => null, 'topic' => null])
        ->and(array_column($context->report->unresolvedReferences(), 'kind'))->toBe(['acf post', 'acf user', 'acf term']);
});

it('rewrites links to the old site in url, link and page link fields', function () {
    $this->site->post(['post_name' => 'hello']);
    $this->site->acfValue($this->post, 'website', $this->site->acfField($this->group, 'website', 'url'), 'https://old.example.com/hello/');
    $this->site->acfValue($this->post, 'cta', $this->site->acfField($this->group, 'cta', 'link'), serialize(['title' => 'Go', 'url' => 'https://old.example.com/hello/', 'target' => '']));
    $this->site->acfValue($this->post, 'archive', $this->site->acfField($this->group, 'archive', 'page_link'), 'https://old.example.com/hello/');
    $this->site->acfValue($this->post, 'external', $this->site->acfField($this->group, 'external', 'url'), 'https://example.net/');

    expect(resolveFields($this->post))->toBe([
        'website' => '/blog/hello',
        'cta' => ['title' => 'Go', 'url' => '/blog/hello', 'target' => ''],
        'archive' => '/blog/hello',
        'external' => 'https://example.net/',
    ]);
});

it('formats wysiwyg fields like classic content', function () {
    $this->site->post(['post_name' => 'hello']);
    $this->site->acfValue($this->post, 'body', $this->site->acfField($this->group, 'body', 'wysiwyg'), "One\n\n<a href=\"https://old.example.com/hello/\">Two</a> [embed]https://vimeo.com/1[/embed]");

    $body = resolveFields($this->post)['body'];

    expect($body)->toStartWith("<p>One</p>\n<p><a href=\"/blog/hello\">Two</a>")
        ->toContain('player.vimeo.com/video/1');
});

it('turns repeaters into lists of rows', function () {
    $repeater = $this->site->acfField($this->group, 'team', 'repeater');
    $parent = $this->site->acfFieldId($repeater);
    $name = $this->site->acfField($parent, 'name', 'text', order: 1);
    $active = $this->site->acfField($parent, 'active', 'true_false', order: 2);
    $this->site->acfValue($this->post, 'team', $repeater, '2');
    $this->site->acfValue($this->post, 'team_0_name', $name, 'Ada');
    $this->site->acfValue($this->post, 'team_0_active', $active, '1');
    $this->site->acfValue($this->post, 'team_1_name', $name, 'Linus');
    $this->site->acfValue($this->post, 'team_1_active', $active, '0');

    expect(resolveFields($this->post))->toBe(['team' => [
        ['name' => 'Ada', 'active' => true],
        ['name' => 'Linus', 'active' => false],
    ]]);
});

it('handles repeaters inside repeaters and empty repeaters', function () {
    $outer = $this->site->acfField($this->group, 'sections', 'repeater');
    $inner = $this->site->acfField($this->site->acfFieldId($outer), 'items', 'repeater');
    $label = $this->site->acfField($this->site->acfFieldId($inner), 'label', 'text');
    $none = $this->site->acfField($this->group, 'none', 'repeater');
    $this->site->acfValue($this->post, 'sections', $outer, '1');
    $this->site->acfValue($this->post, 'sections_0_items', $inner, '2');
    $this->site->acfValue($this->post, 'sections_0_items_0_label', $label, 'a');
    $this->site->acfValue($this->post, 'sections_0_items_1_label', $label, 'b');
    $this->site->acfValue($this->post, 'none', $none, '');

    expect(resolveFields($this->post))->toBe([
        'sections' => [['items' => [['label' => 'a'], ['label' => 'b']]]],
        'none' => [],
    ]);
});

it('turns flexible content into rows with their layout name', function () {
    $flexible = $this->site->acfField($this->group, 'blocks', 'flexible_content', ['layouts' => [
        'layout_text' => ['key' => 'layout_text', 'name' => 'text', 'label' => 'Text'],
        'layout_quote' => ['key' => 'layout_quote', 'name' => 'quote', 'label' => 'Quote'],
    ]]);
    $parent = $this->site->acfFieldId($flexible);
    $body = $this->site->acfField($parent, 'body', 'textarea', ['parent_layout' => 'layout_text']);
    $quote = $this->site->acfField($parent, 'quote', 'text', ['parent_layout' => 'layout_quote']);
    $author = $this->site->acfField($parent, 'author', 'text', ['parent_layout' => 'layout_quote']);
    $this->site->acfValue($this->post, 'blocks', $flexible, serialize(['quote', 'text', 'gone']));
    $this->site->acfValue($this->post, 'blocks_0_quote', $quote, 'To be');
    $this->site->acfValue($this->post, 'blocks_0_author', $author, 'W. S.');
    $this->site->acfValue($this->post, 'blocks_1_body', $body, 'Hello');

    expect(resolveFields($this->post))->toBe(['blocks' => [
        ['layout' => 'quote', 'quote' => 'To be', 'author' => 'W. S.'],
        ['layout' => 'text', 'body' => 'Hello'],
        ['layout' => 'gone'],
    ]]);
});

it('turns groups into nested objects', function () {
    $group = $this->site->acfField($this->group, 'address', 'group');
    $parent = $this->site->acfFieldId($group);
    $street = $this->site->acfField($parent, 'street', 'text');
    $zip = $this->site->acfField($parent, 'zip', 'number');
    $this->site->acfValue($this->post, 'address', $group, '');
    $this->site->acfValue($this->post, 'address_street', $street, 'Main St');
    $this->site->acfValue($this->post, 'address_zip', $zip, '12345');

    expect(resolveFields($this->post))->toBe(['address' => ['street' => 'Main St', 'zip' => 12345]]);
});

it('copies values without a definition as they are and keeps the other meta', function () {
    $this->site->acfValue($this->post, 'mystery', 'field_unknown', 'value');
    $this->site->postMeta($this->post, 'plain', 'yes');
    $this->site->postMeta($this->post, '_internal', 'hidden');
    $key = $this->site->acfField($this->group, 'known', 'number');
    $this->site->acfValue($this->post, 'known', $key, '5');

    expect(resolveFields($this->post))->toBe(['mystery' => 'value', 'plain' => 'yes', 'known' => 5]);
});

it('ignores field definitions that cannot be read', function () {
    $this->site->post(['post_type' => 'acf-field', 'post_name' => 'field_broken', 'post_excerpt' => 'broken', 'post_parent' => $this->group, 'post_content' => 'a:1:{broken']);
    $this->site->acfValue($this->post, 'broken', 'field_broken', '1');

    expect(resolveFields($this->post))->toBe(['broken' => '1']);
});

it('stops at a row count that cannot be real', function () {
    $repeater = $this->site->acfField($this->group, 'rows', 'repeater');
    $this->site->acfField($this->site->acfFieldId($repeater), 'x', 'text');
    $this->site->acfValue($this->post, 'rows', $repeater, '999999999');

    $start = microtime(true);
    $fields = resolveFields($this->post);

    expect(microtime(true) - $start)->toBeLessThan(2.0)
        ->and(count($fields['rows']))->toBeLessThan(100);
});

it('can be turned off', function () {
    config()->set('wp-migrator.fields.acf', false);
    $key = $this->site->acfField($this->group, 'count', 'number');
    $this->site->acfValue($this->post, 'count', $key, '5');

    expect(resolveFields($this->post))->toBe(['count' => '5']);
});

it('does not accept a media field that points at something other than media', function () {
    $context = importContext('posts');
    $post = Post::create(['type' => 'post', 'status' => 'publish', 'title' => 'Not an image', 'slug' => 'x']);
    $context->map->record(SourceType::Post, 501, $post, 'x');
    $this->site->acfValue($this->post, 'hero', $this->site->acfField($this->group, 'hero', 'image'), '501');

    expect(resolveFields($this->post, $context))->toBe(['hero' => null])
        ->and($context->report->unresolvedReferences()[0]['kind'])->toBe('acf media');
});

it('always stores a relationship as a list', function () {
    $context = importContext('posts');
    $other = Post::create(['type' => 'post', 'status' => 'publish', 'title' => 'Other', 'slug' => 'other']);
    $context->map->record(SourceType::Post, 601, $other, 'x');
    $this->site->acfValue($this->post, 'related', $this->site->acfField($this->group, 'related', 'relationship'), '601');

    expect(resolveFields($this->post, $context))->toBe(['related' => [$other->id]]);
});
