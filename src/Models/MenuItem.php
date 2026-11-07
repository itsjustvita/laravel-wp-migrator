<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use ItsJustVita\WpMigrator\Models\Concerns\HasPrefixedTable;

/**
 * @property int $id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property string $title
 * @property string|null $url
 * @property string|null $linkable_type
 * @property int|null $linkable_id
 * @property int $order
 * @property string|null $target
 * @property list<string>|null $classes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Menu $menu
 * @property-read MenuItem|null $parent
 * @property-read Collection<int, MenuItem> $children
 * @property-read Model|null $linkable
 */
class MenuItem extends Model
{
    use HasPrefixedTable;

    protected $table = 'menu_items';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'classes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
