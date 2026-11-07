<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use ItsJustVita\WpMigrator\Models\SeoMeta;

/**
 * Gives a model its imported SEO settings. Add it to application models that
 * custom mappers write to, then eager load "seo" for lists.
 */
trait HasSeoMeta
{
    /**
     * @return MorphOne<SeoMeta, $this>
     */
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }
}
