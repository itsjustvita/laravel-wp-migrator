<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use ItsJustVita\WpMigrator\Source\Uploads;

it('prefers the configured uploads url', function () {
    config()->set('wp-migrator.source.uploads_url', 'https://cdn.example.com/files/');
    config()->set('wp-migrator.source.site_url', 'https://old.example.com');

    expect(app(Uploads::class)->baseUrl())->toBe('https://cdn.example.com/files');
});

it('derives the uploads url from the configured site url', function () {
    config()->set('wp-migrator.source.site_url', 'https://old.example.com/');

    expect(app(Uploads::class)->baseUrl())->toBe('https://old.example.com/wp-content/uploads');
});

it('falls back to the upload_url_path, siteurl and home options', function (array $options, string $expected) {
    $site = $this->source();

    foreach ($options as $name => $value) {
        $site->option($name, $value);
    }

    expect(app(Uploads::class)->baseUrl())->toBe($expected);
})->with([
    'upload path option' => [['upload_url_path' => 'https://media.example.com/up/', 'siteurl' => 'https://old.example.com'], 'https://media.example.com/up'],
    'siteurl before home' => [['siteurl' => 'https://core.example.com', 'home' => 'https://www.example.com'], 'https://core.example.com/wp-content/uploads'],
    'home only' => [['home' => 'https://www.example.com'], 'https://www.example.com/wp-content/uploads'],
]);

it('has no url when nothing is known, even without source tables', function () {
    expect(app(Uploads::class)->baseUrl())->toBeNull()
        ->and(app(Uploads::class)->url('a.jpg'))->toBeNull();
});

it('encodes path segments in file urls but keeps the slashes', function () {
    config()->set('wp-migrator.source.site_url', 'https://old.example.com');

    expect(app(Uploads::class)->url('2026/01/mein bild ü.jpg'))
        ->toBe('https://old.example.com/wp-content/uploads/2026/01/mein%20bild%20%C3%BC.jpg');
});

it('does not download when downloads are turned off', function () {
    config()->set('wp-migrator.source.site_url', 'https://old.example.com');
    config()->set('wp-migrator.media.download_missing', false);
    Http::fake();

    expect(app(Uploads::class)->open('a.jpg'))->toBeNull();

    Http::assertNothingSent();
});

it('treats a connection error like a missing file', function () {
    config()->set('wp-migrator.source.site_url', 'https://old.example.com');
    Http::fake(fn () => throw new ConnectionException('timed out'));

    expect(app(Uploads::class)->open('a.jpg'))->toBeNull();
});
