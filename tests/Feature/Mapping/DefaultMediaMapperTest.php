<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use ItsJustVita\WpMigrator\Mapping\DefaultMediaMapper;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\Dto\SourceMedia;
use ItsJustVita\WpMigrator\Source\Dto\SourceMediaSize;

function sourceMedia(int $id = 7, ?string $file = '2026/01/hero.jpg', array $sizes = []): SourceMedia
{
    return new SourceMedia(
        id: $id,
        file: $file,
        url: 'https://old.example.com/wp-content/uploads/'.$file,
        mimeType: 'image/jpeg',
        title: 'Hero',
        alt: 'A hero',
        caption: 'Caption',
        description: null,
        width: 1200,
        height: 800,
        filesize: null,
        sizes: $sizes,
        parentId: null,
        authorId: null,
        uploadedAt: CarbonImmutable::parse('2026-01-10 09:00:00'),
        modifiedAt: CarbonImmutable::parse('2026-01-11 09:00:00'),
        metadataMalformed: false,
        meta: MetaBag::empty(),
    );
}

function uploadsDir(): string
{
    $dir = sys_get_temp_dir().'/wp-migrator-uploads-'.uniqid();
    mkdir($dir.'/2026/01', 0777, true);
    config()->set('wp-migrator.source.uploads_path', $dir);

    return $dir;
}

beforeEach(function () {
    Storage::fake('public');
    config()->set('wp-migrator.media.disk', 'public');
    config()->set('wp-migrator.media.directory', 'wp-uploads');
});

it('copies the file and its variants from the uploads directory', function () {
    $dir = uploadsDir();
    file_put_contents($dir.'/2026/01/hero.jpg', 'original-bytes');
    file_put_contents($dir.'/2026/01/hero-150x150.jpg', 'thumb');
    $context = importContext('media');

    $media = app(DefaultMediaMapper::class)->map(sourceMedia(sizes: [
        'thumbnail' => new SourceMediaSize('hero-150x150.jpg', 150, 150, 'image/jpeg'),
    ]), $context);

    expect($media)->toBeInstanceOf(Media::class)
        ->and($media->disk)->toBe('public')
        ->and($media->path)->toBe('wp-uploads/2026/01/hero.jpg')
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and($media->size)->toBe(14)
        ->and($media->width)->toBe(1200)
        ->and($media->alt)->toBe('A hero')
        ->and($media->caption)->toBe('Caption')
        ->and($media->title)->toBe('Hero')
        ->and($media->created_at?->toIso8601String())->toBe('2026-01-10T09:00:00+00:00')
        ->and($media->variants)->toBe([
            'thumbnail' => ['path' => 'wp-uploads/2026/01/hero-150x150.jpg', 'width' => 150, 'height' => 150, 'mime_type' => 'image/jpeg'],
        ])
        ->and($context->report->warnings())->toBe([]);

    Storage::disk('public')->assertExists('wp-uploads/2026/01/hero.jpg');
    Storage::disk('public')->assertExists('wp-uploads/2026/01/hero-150x150.jpg');
    expect(Storage::disk('public')->get('wp-uploads/2026/01/hero.jpg'))->toBe('original-bytes');
});

it('downloads files that are missing locally from the old site', function () {
    uploadsDir();
    config()->set('wp-migrator.source.site_url', 'https://old.example.com');
    Http::fake(['https://old.example.com/wp-content/uploads/2026/01/hero.jpg' => Http::response('downloaded')]);

    $media = app(DefaultMediaMapper::class)->map(sourceMedia(), importContext('media'));

    expect(Storage::disk('public')->get('wp-uploads/2026/01/hero.jpg'))->toBe('downloaded')
        ->and($media->size)->toBe(10);
});

it('keeps the record but warns when the file cannot be found anywhere', function () {
    uploadsDir();
    config()->set('wp-migrator.source.site_url', 'https://old.example.com');
    Http::fake(['*' => Http::response('', 404)]);
    $context = importContext('media');

    $media = app(DefaultMediaMapper::class)->map(sourceMedia(), $context);

    expect($media->exists)->toBeTrue()
        ->and($media->path)->toBe('wp-uploads/2026/01/hero.jpg')
        ->and($context->report->warnings()[0]['message'])->toContain('not found');

    Storage::disk('public')->assertMissing('wp-uploads/2026/01/hero.jpg');
});

it('warns about attachments without a file and malformed metadata', function () {
    $context = importContext('media');

    $media = app(DefaultMediaMapper::class)->map(sourceMedia(file: null), $context);

    expect($media->path)->toBe('')
        ->and($context->report->warnings()[0]['message'])->toContain('no file');
});

it('copies nothing during a dry run', function () {
    $dir = uploadsDir();
    file_put_contents($dir.'/2026/01/hero.jpg', 'original-bytes');

    $media = app(DefaultMediaMapper::class)->map(sourceMedia(), importContext('media', dryRun: true));

    expect($media->path)->toBe('wp-uploads/2026/01/hero.jpg');
    Storage::disk('public')->assertMissing('wp-uploads/2026/01/hero.jpg');
});

it('refuses file paths that leave the uploads directory', function (string $file) {
    $dir = uploadsDir();
    file_put_contents(dirname($dir).'/wp-migrator-secret.txt', 'secret');
    $context = importContext('media');

    $media = app(DefaultMediaMapper::class)->map(sourceMedia(file: $file), $context);

    expect($media->path)->toBe('')
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and($context->report->warnings()[0]['message'])->toContain('not a relative path');

    unlink(dirname($dir).'/wp-migrator-secret.txt');
})->with([
    'parent segments' => '../wp-migrator-secret.txt',
    'nested parent segments' => '2026/../../wp-migrator-secret.txt',
    'backslashes' => '..\\wp-migrator-secret.txt',
    'absolute' => '/etc/hosts',
    'windows drive' => 'C:/windows/win.ini',
]);

it('refuses variant files that leave the uploads directory', function () {
    $dir = uploadsDir();
    file_put_contents($dir.'/2026/01/hero.jpg', 'original');
    file_put_contents(dirname($dir).'/wp-migrator-secret.txt', 'secret');
    $context = importContext('media');

    $media = app(DefaultMediaMapper::class)->map(sourceMedia(sizes: [
        'evil' => new SourceMediaSize('../../../wp-migrator-secret.txt', 1, 1, 'text/plain'),
    ]), $context);

    expect($media->variants)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe(['wp-uploads/2026/01/hero.jpg'])
        ->and($context->report->warnings()[0]['message'])->toContain('1 of 1 sizes');

    unlink(dirname($dir).'/wp-migrator-secret.txt');
});

it('refuses file types that a web server could execute or that run script in the browser', function (string $file) {
    $dir = uploadsDir();
    File::ensureDirectoryExists(dirname($dir.'/'.$file));
    file_put_contents($dir.'/'.$file, '<?php echo "pwned";');
    $context = importContext('media');

    $media = app(DefaultMediaMapper::class)->map(sourceMedia(file: $file), $context);

    expect($media->path)->toBe('')
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and($context->report->warnings()[0]['message'])->toContain('not allowed')
        ->and($context->rowIncomplete())->toBeFalse();
})->with([
    'php' => '2026/01/shell.php',
    'uppercase php' => '2026/01/shell.PHP',
    'php5' => '2026/01/shell.php5',
    'phtml' => '2026/01/shell.phtml',
    'phar' => '2026/01/shell.phar',
    'double extension' => '2026/01/shell.php.jpg',
    'htaccess' => '2026/01/.htaccess',
    'user ini' => '2026/01/.user.ini',
    'dotfile' => '2026/01/.hidden.jpg',
    'svg' => '2026/01/logo.svg',
    'html' => '2026/01/page.html',
    'no extension' => '2026/01/README',
]);

it('copies the common media and document types', function (string $file) {
    $dir = uploadsDir();
    file_put_contents($dir.'/2026/01/'.$file, 'bytes');

    $media = app(DefaultMediaMapper::class)->map(sourceMedia(file: '2026/01/'.$file), importContext('media'));

    expect($media->path)->toBe('wp-uploads/2026/01/'.$file);
    Storage::disk('public')->assertExists('wp-uploads/2026/01/'.$file);
})->with(['a.jpg', 'a.JPEG', 'a.png', 'a.webp', 'a.gif', 'a.avif', 'a.mp4', 'a.mp3', 'a.pdf', 'a.docx', 'a.xlsx', 'a.zip', 'my.file.name.png']);

it('copies further types when the configuration allows them', function () {
    config()->set('wp-migrator.media.allowed_extensions', ['svg']);
    $dir = uploadsDir();
    file_put_contents($dir.'/2026/01/logo.svg', '<svg/>');
    file_put_contents($dir.'/2026/01/a.jpg', 'x');

    expect(app(DefaultMediaMapper::class)->map(sourceMedia(file: '2026/01/logo.svg'), importContext('media'))->path)->toBe('wp-uploads/2026/01/logo.svg')
        ->and(app(DefaultMediaMapper::class)->map(sourceMedia(id: 8, file: '2026/01/a.jpg'), importContext('media'))->path)->toBe('');
});

it('never copies executable types, whatever the configuration says', function () {
    config()->set('wp-migrator.media.allowed_extensions', ['php', 'jpg']);
    $dir = uploadsDir();
    file_put_contents($dir.'/2026/01/shell.php', 'x');

    expect(app(DefaultMediaMapper::class)->map(sourceMedia(file: '2026/01/shell.php'), importContext('media'))->path)->toBe('');
    Storage::disk('public')->assertMissing('wp-uploads/2026/01/shell.php');
});

it('refuses variant files of types that are not allowed', function () {
    $dir = uploadsDir();
    file_put_contents($dir.'/2026/01/hero.jpg', 'original');
    file_put_contents($dir.'/2026/01/hero.php', 'x');
    $context = importContext('media');

    $media = app(DefaultMediaMapper::class)->map(sourceMedia(sizes: [
        'evil' => new SourceMediaSize('hero.php', 1, 1, 'image/jpeg'),
    ]), $context);

    expect($media->variants)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe(['wp-uploads/2026/01/hero.jpg']);
});
