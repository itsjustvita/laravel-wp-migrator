<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use ItsJustVita\WpMigrator\Database\Tables;
use ItsJustVita\WpMigrator\Models\Concerns\HasPrefixedTable;

/**
 * @property int $id
 * @property string $taxonomy
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int|null $parent_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Term|null $parent
 * @property-read Collection<int, Term> $children
 * @property-read Collection<int, Post> $posts
 */
class Term extends Model
{
    use HasPrefixedTable;

    protected $table = 'terms';

    protected $guarded = [];

    /**
     * @return BelongsTo<Term, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Term, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return MorphToMany<Post, $this>
     */
    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'termable', Tables::prefixed('termables'));
    }
}
