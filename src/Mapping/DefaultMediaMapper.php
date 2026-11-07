<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Mapping;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Mapping\Concerns\StoresSourceTimestamps;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Source\Dto\SourceMedia;
use ItsJustVita\WpMigrator\Source\Uploads;
use RuntimeException;

/**
 * Copies attachments and their resized variants onto a Laravel disk and
 * records them in the media table. The directory layout of wp-content/uploads
 * is kept below the configured directory so old URLs can be mapped later.
 */
class DefaultMediaMapper implements MediaMapper
{
    use StoresSourceTimestamps;

    public function __construct(
        protected readonly Repository $config,
        protected readonly FilesystemFactory $filesystems,
        protected readonly Uploads $uploads,
    ) {}

    public function map(SourceMedia $media, ImportContext $context): ?Model
    {
        $model = $context->existing(SourceType::Post, $media->id, Media::class) ?? new Media;
        $diskName = $this->config->string('wp-migrator.media.disk', 'public');
        $disk = $this->filesystems->disk($diskName);

        if ($media->metadataMalformed) {
            $context->warn($media->id, 'Attachment metadata is malformed; dimensions and sizes are missing.');
        }

        $path = '';
        $size = $media->filesize;
        $variants = [];
        $file = $media->file === null ? null : $this->safeRelativePath($media->file);

        if ($media->file === null || $media->file === '') {
            $context->warn($media->id, 'Attachment has no file (_wp_attached_file is missing).');
        } elseif ($file === null) {
            $context->warn($media->id, sprintf('Attachment file "%s" is not a relative path inside the uploads directory; it was not copied.', $media->file));
        } else {
            $path = Media::pathFor($file);

            if (! $context->dryRun) {
                $copied = $this->copy($file, $path, $disk);

                if ($copied === null) {
                    $context->warn($media->id, sprintf('File %s not found in the uploads directory and could not be downloaded.', $file));
                    $context->markIncomplete();
                } else {
                    $size = $copied;
                }
            }

            $variants = $this->variants($media, $file, $disk, $context);
        }

        $model->fill([
            'disk' => $diskName,
            'path' => $path,
            'mime_type' => $media->mimeType,
            'size' => $size,
            'width' => $media->width,
            'height' => $media->height,
            'alt' => $media->alt !== '' ? $media->alt : null,
            'caption' => $media->caption,
            'title' => $media->title !== '' ? $media->title : null,
            'variants' => $variants === [] ? null : $variants,
        ]);

        $this->saveWithSourceTimestamps($model, $media->uploadedAt, $media->modifiedAt);

        return $model;
    }

    /**
     * @return array<string, array{path: string, width: int|null, height: int|null, mime_type: string|null}>
     */
    protected function variants(SourceMedia $media, string $file, Filesystem $disk, ImportContext $context): array
    {
        $directory = dirname($file);
        $prefix = $directory === '.' ? '' : $directory.'/';

        $variants = [];
        $missing = [];
        $copied = [];

        foreach ($media->sizes as $name => $size) {
            $variantFile = $this->safeRelativePath($prefix.$size->file);

            if ($variantFile === null) {
                $missing[] = $name;

                continue;
            }

            $target = Media::pathFor($variantFile);

            if (! $context->dryRun && ! isset($copied[$target])) {
                if ($this->copy($variantFile, $target, $disk) === null) {
                    $missing[] = $name;

                    continue;
                }

                $copied[$target] = true;
            }

            $variants[$name] = [
                'path' => $target,
                'width' => $size->width,
                'height' => $size->height,
                'mime_type' => $size->mimeType,
            ];
        }

        if ($missing !== []) {
            $context->warn($media->id, sprintf('%d of %d sizes not found and skipped: %s.', count($missing), count($media->sizes), implode(', ', $missing)));
        }

        return $variants;
    }

    /**
     * Copies a file onto the disk and returns its size, or null when the
     * source file cannot be found.
     */
    protected function copy(string $file, string $target, Filesystem $disk): ?int
    {
        $stream = $this->uploads->open($file);

        if ($stream === null) {
            return null;
        }

        try {
            if (! $disk->writeStream($target, $stream)) {
                throw new RuntimeException(sprintf('Could not write %s to the media disk.', $target));
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $disk->size($target);
    }

    /**
     * Rejects absolute paths and parent directory segments so a crafted
     * attachment row cannot read or write outside the uploads directories.
     */
    protected function safeRelativePath(string $file): ?string
    {
        $file = str_replace('\\', '/', $file);

        if ($file === ''
            || str_starts_with($file, '/')
            || str_contains($file, "\0")
            || preg_match('#^[A-Za-z]:#', $file) === 1
            || preg_match('#(^|/)\.\.(/|$)#', $file) === 1) {
            return null;
        }

        return $file;
    }
}
