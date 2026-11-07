<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use ItsJustVita\WpMigrator\Models\Concerns\HasPrefixedTable;

/**
 * @property int $id
 * @property string $seoable_type
 * @property int $seoable_id
 * @property string|null $title
 * @property string|null $description
 * @property string|null $canonical
 * @property string|null $robots
 * @property string|null $og_title
 * @property string|null $og_description
 * @property int|null $og_image_id
 * @property string|null $og_image_url
 * @property array<string, mixed>|null $schema
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $seoable
 * @property-read Media|null $ogImage
 */
class SeoMeta extends Model
{
    use HasPrefixedTable;

    protected $table = 'seo_meta';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_image_id');
    }
}
