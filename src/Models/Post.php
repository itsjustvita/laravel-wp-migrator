<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use ItsJustVita\WpMigrator\Database\Tables;
use ItsJustVita\WpMigrator\Models\Concerns\HasPrefixedTable;
use ItsJustVita\WpMigrator\Models\Concerns\HasSeoMeta;

/**
 * @property int $id
 * @property string $type
 * @property string $status
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $content_raw
 * @property array<int, mixed>|null $blocks
 * @property array<string, mixed>|null $fields
 * @property string|null $template
 * @property int|null $parent_id
 * @property int $menu_order
 * @property int|null $author_id
 * @property int|null $featured_media_id
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Post|null $parent
 * @property-read Collection<int, Post> $children
 * @property-read Media|null $featuredMedia
 * @property-read Collection<int, Term> $terms
 * @property-read SeoMeta|null $seo
 * @property-read Collection<int, Comment> $comments
 */
class Post extends Model
{
    use HasPrefixedTable;
    use HasSeoMeta;

    protected $table = 'posts';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'fields' => 'array',
            'menu_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('menu_order');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    /**
     * @return MorphToMany<Term, $this>
     */
    public function terms(): MorphToMany
    {
        return $this->morphToMany(Term::class, 'termable', Tables::prefixed('termables'));
    }

    /**
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
