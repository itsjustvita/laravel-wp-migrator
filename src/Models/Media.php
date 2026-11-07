<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Models;

use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use ItsJustVita\WpMigrator\Models\Concerns\HasPrefixedTable;

/**
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string|null $mime_type
 * @property int|null $size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt
 * @property string|null $caption
 * @property string|null $title
 * @property array<string, mixed>|null $variants
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Media extends Model
{
    use HasPrefixedTable;

    protected $table = 'media';

    protected $guarded = [];

    /**
     * The path of an uploaded file on the media disk, for a path relative to
     * the old uploads directory.
     */
    public static function pathFor(string $file): string
    {
        $directory = trim(app(Repository::class)->string('wp-migrator.media.directory', ''), '/');

        return $directory === '' ? $file : $directory.'/'.$file;
    }

    /**
     * The URL of the file, or of one of its sizes when that size exists.
     */
    public function url(?string $size = null): string
    {
        return Storage::disk($this->disk)->url(($size === null ? null : $this->variantPath($size)) ?? $this->path);
    }

    public function variantPath(string $size): ?string
    {
        $variant = $this->variants[$size] ?? null;
        $path = is_array($variant) ? ($variant['path'] ?? null) : null;

        return is_string($path) ? $path : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variants' => 'array',
        ];
    }
}
