<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use ItsJustVita\WpMigrator\Mapping\DefaultMediaMapper;
use ItsJustVita\WpMigrator\Mapping\DefaultPostMapper;
use ItsJustVita\WpMigrator\Mapping\DefaultUserMapper;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\Dto\SourceMedia;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;
use ItsJustVita\WpMigrator\Source\Dto\SourceUser;
use ItsJustVita\WpMigrator\Tests\Support\User;

const UTC_INSTANT = '2026-01-15T10:00:00+00:00';
const BERLIN_CLOCK = '2026-01-15T11:00:00+01:00';

beforeEach(function () {
    config()->set('app.timezone', 'Europe/Berlin');
    date_default_timezone_set('Europe/Berlin');
});

afterEach(function () {
    date_default_timezone_set('UTC');
});

it('stores post dates as the same instant in an application with another timezone', function () {
    $instant = CarbonImmutable::parse(UTC_INSTANT);

    $post = app(DefaultPostMapper::class)->map(new SourcePost(
        id: 1, type: 'post', status: 'publish', title: 'A', slug: 'a', content: '', excerpt: '',
        authorId: null, parentId: null, menuOrder: 0, mimeType: null, guid: null,
        publishedAt: $instant, modifiedAt: $instant, meta: MetaBag::empty(),
    ), importContext('posts'));

    $fresh = Post::query()->findOrFail($post->id);

    expect($fresh->published_at?->toIso8601String())->toBe(BERLIN_CLOCK)
        ->and($fresh->created_at?->toIso8601String())->toBe(BERLIN_CLOCK)
        ->and($fresh->updated_at?->toIso8601String())->toBe(BERLIN_CLOCK);
});

it('stores media dates as the same instant in an application with another timezone', function () {
    Storage::fake('public');
    $instant = CarbonImmutable::parse(UTC_INSTANT);

    $media = app(DefaultMediaMapper::class)->map(new SourceMedia(
        id: 1, file: null, url: null, mimeType: null, title: 'A', alt: null, caption: null, description: null,
        width: null, height: null, filesize: null, sizes: [], parentId: null, authorId: null,
        uploadedAt: $instant, modifiedAt: $instant, metadataMalformed: false, meta: MetaBag::empty(),
    ), importContext('media'));

    expect(Media::query()->findOrFail($media->id)->created_at?->toIso8601String())->toBe(BERLIN_CLOCK);
});

it('stores the registration date as the same instant in an application with another timezone', function () {
    $user = app(DefaultUserMapper::class)->map(new SourceUser(
        id: 1, login: 'a', email: 'a@example.com', displayName: 'A', nicename: 'a', url: null,
        passwordHash: '$P$x', registeredAt: CarbonImmutable::parse(UTC_INSTANT), roles: [], rolesMalformed: false, meta: MetaBag::empty(),
    ), importContext('users'));

    expect(User::query()->findOrFail($user->getKey())->created_at?->toIso8601String())->toBe(BERLIN_CLOCK);
});
