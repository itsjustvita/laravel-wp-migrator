<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use ItsJustVita\WpMigrator\Source\Dto\SourceMedia;
use ItsJustVita\WpMigrator\Source\Readers\MediaFilter;
use ItsJustVita\WpMigrator\Source\Readers\MediaReader;

function readAllMedia(MediaFilter $filter = new MediaFilter): array
{
    return array_merge(...iterator_to_array(app(MediaReader::class)->chunks($filter), false) ?: [[]]);
}

it('reads attachments with file, dimensions, sizes and captions', function () {
    $site = $this->source();
    $parent = $site->post();
    $author = $site->user();
    $id = $site->attachment([
        'post_title' => 'Hero',
        'post_excerpt' => 'Caption',
        'post_content' => 'Description',
        'post_parent' => $parent,
        'post_author' => $author,
        'guid' => 'https://old.example.com/wp-content/uploads/2026/01/hero.jpg',
        'post_mime_type' => 'image/jpeg',
        'post_date_gmt' => '2026-01-10 09:00:00',
    ]);
    $site->postMeta($id, '_wp_attached_file', '2026/01/hero.jpg');
    $site->postMeta($id, '_wp_attachment_image_alt', 'A hero');
    $site->postMeta($id, '_wp_attachment_metadata', serialize([
        'width' => 1200,
        'height' => 800,
        'file' => '2026/01/hero.jpg',
        'filesize' => 123456,
        'sizes' => [
            'thumbnail' => ['file' => 'hero-150x150.jpg', 'width' => 150, 'height' => 150, 'mime-type' => 'image/jpeg'],
            'medium' => ['file' => 'hero-300x200.jpg', 'width' => 300, 'height' => 200, 'mime-type' => 'image/jpeg'],
        ],
    ]));

    $media = readAllMedia();

    expect($media)->toHaveCount(1);

    $item = $media[0];

    expect($item)->toBeInstanceOf(SourceMedia::class)
        ->and($item->id)->toBe($id)
        ->and($item->file)->toBe('2026/01/hero.jpg')
        ->and($item->url)->toBe('https://old.example.com/wp-content/uploads/2026/01/hero.jpg')
        ->and($item->mimeType)->toBe('image/jpeg')
        ->and($item->title)->toBe('Hero')
        ->and($item->alt)->toBe('A hero')
        ->and($item->caption)->toBe('Caption')
        ->and($item->description)->toBe('Description')
        ->and($item->width)->toBe(1200)
        ->and($item->height)->toBe(800)
        ->and($item->filesize)->toBe(123456)
        ->and($item->parentId)->toBe($parent)
        ->and($item->authorId)->toBe($author)
        ->and($item->uploadedAt?->toIso8601String())->toBe('2026-01-10T09:00:00+00:00')
        ->and($item->metadataMalformed)->toBeFalse()
        ->and(array_keys($item->sizes))->toBe(['thumbnail', 'medium'])
        ->and($item->sizes['thumbnail']->file)->toBe('hero-150x150.jpg')
        ->and($item->sizes['thumbnail']->width)->toBe(150)
        ->and($item->sizes['medium']->mimeType)->toBe('image/jpeg');
});

it('copes with missing or malformed attachment metadata', function () {
    $site = $this->source();
    $plain = $site->attachment(['post_mime_type' => 'application/pdf']);
    $site->postMeta($plain, '_wp_attached_file', '2026/01/doc.pdf');
    $broken = $site->attachment();
    $site->postMeta($broken, '_wp_attached_file', '2026/01/broken.jpg');
    $site->postMeta($broken, '_wp_attachment_metadata', 'a:2:{s:5:"width";i:10;}');

    [$pdf, $image] = readAllMedia();

    expect($pdf->width)->toBeNull()
        ->and($pdf->sizes)->toBe([])
        ->and($pdf->metadataMalformed)->toBeFalse()
        ->and($image->metadataMalformed)->toBeTrue()
        ->and($image->sizes)->toBe([])
        ->and($image->file)->toBe('2026/01/broken.jpg');
});

it('reads attachments in chunks and filters by modification date', function () {
    config()->set('wp-migrator.import.chunk_size', 2);
    $site = $this->source();
    $ids = [];
    foreach (range(1, 5) as $i) {
        $ids[] = $site->attachment(['post_title' => "image {$i}"]);
    }
    $site->attachment(['post_title' => 'old', 'post_modified_gmt' => '2025-01-01 00:00:00']);
    $site->post();

    $reader = app(MediaReader::class);
    $chunks = iterator_to_array($reader->chunks(new MediaFilter), false);

    expect(array_map(count(...), $chunks))->toBe([2, 2, 2])
        ->and($reader->count(new MediaFilter))->toBe(6)
        ->and(array_map(fn ($m) => $m->id, readAllMedia(new MediaFilter(since: CarbonImmutable::parse('2026-01-01')))))->toBe($ids);
});
