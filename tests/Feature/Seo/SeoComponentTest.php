<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Models\SeoMeta;
use ItsJustVita\WpMigrator\Tests\Support\Event;

function seoPost(array $seo = []): Post
{
    $post = Post::create(['type' => 'post', 'status' => 'publish', 'title' => 'Hello & Welcome', 'slug' => 'hello']);

    if ($seo !== []) {
        $post->seo()->create($seo);
    }

    return $post->fresh();
}

function renderSeo(mixed $model): string
{
    return trim(Blade::render('<x-wp-seo :model="$model" />', ['model' => $model]));
}

beforeEach(function () {
    Storage::fake('public');
});

it('renders the stored meta tags', function () {
    $html = renderSeo(seoPost([
        'title' => 'SEO Title',
        'description' => 'A description',
        'canonical' => '/blog/hello',
        'robots' => 'noindex, nofollow',
        'og_title' => 'OG Title',
        'og_description' => 'OG Description',
        'og_image_url' => '/storage/og.jpg',
    ]));

    expect($html)->toContain('<title>SEO Title</title>')
        ->toContain('<meta name="description" content="A description">')
        ->toContain('<link rel="canonical" href="'.url('/blog/hello').'">')
        ->toContain('<meta name="robots" content="noindex, nofollow">')
        ->toContain('<meta property="og:title" content="OG Title">')
        ->toContain('<meta property="og:description" content="OG Description">')
        ->toContain('<meta property="og:image" content="'.url('/storage/og.jpg').'">')
        ->toContain('<meta property="og:url" content="'.url('/blog/hello').'">');
});

it('falls back to the title of the model and leaves out what is missing', function () {
    $html = renderSeo(seoPost());

    expect($html)->toBe("<title>Hello &amp; Welcome</title>\n<meta property=\"og:title\" content=\"Hello &amp; Welcome\">");
});

it('uses the open graph image from the media library', function () {
    $media = Media::create(['disk' => 'public', 'path' => 'wp-uploads/og.jpg']);

    expect(renderSeo(seoPost(['og_image_id' => $media->id, 'og_image_url' => 'https://old.example.com/x.jpg'])))
        ->toContain('<meta property="og:image" content="'.url(Storage::disk('public')->url('wp-uploads/og.jpg')).'">');
});

it('escapes every value', function () {
    $html = renderSeo(seoPost(['title' => '"><script>alert(1)</script>', 'description' => '" onload="x', 'canonical' => 'javascript:alert(1)']));

    expect($html)->not->toContain('<script>alert')
        ->not->toContain('" onload')
        ->not->toContain('javascript:');
});

it('renders structured data safely', function () {
    $html = renderSeo(seoPost(['title' => 'T', 'schema' => ['@type' => 'Article', 'name' => '</script><script>alert(1)</script>']]));

    expect($html)->toContain('<script type="application/ld+json">')
        ->not->toContain('</script><script>');
});

it('works for application models without a seo relation', function () {
    Schema::create('events', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('starts_at')->nullable();
        $table->timestamps();
    });
    $event = Event::create(['title' => 'Concert']);
    SeoMeta::create(['seoable_type' => $event->getMorphClass(), 'seoable_id' => $event->id, 'title' => 'Concert tickets']);

    expect(renderSeo($event))->toContain('<title>Concert tickets</title>');
});

it('uses an eager loaded relation without another query', function () {
    seoPost(['title' => 'Loaded']);
    $post = Post::query()->with('seo')->sole();
    $queries = 0;
    Post::getConnectionResolver()->connection()->listen(function () use (&$queries) {
        $queries++;
    });

    expect(renderSeo($post))->toContain('<title>Loaded</title>')
        ->and($queries)->toBe(0);
});

it('renders nothing without a model', function () {
    expect(renderSeo(null))->toBe('');
});
