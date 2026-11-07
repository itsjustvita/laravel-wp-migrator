<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\View\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Models\SeoMeta;

/**
 * <x-wp-seo :model="$post" /> renders the title and meta tags of a model.
 * The title falls back to the model's title attribute.
 */
class Seo extends Component
{
    public function __construct(
        public ?Model $model = null,
    ) {}

    public function render(): Htmlable
    {
        if ($this->model === null) {
            return new HtmlString('');
        }

        $seo = $this->seoMeta($this->model);
        $fallback = $this->model->getAttribute('title');
        $title = $seo?->title ?: (is_string($fallback) ? $fallback : null);
        $canonical = $this->absoluteUrl($seo?->canonical);

        $lines = [];

        if ($title !== null && $title !== '') {
            $lines[] = '<title>'.e($title).'</title>';
        }

        $this->meta($lines, 'name', 'description', $seo?->description);

        if ($canonical !== null) {
            $lines[] = '<link rel="canonical" href="'.e($canonical).'">';
        }

        $this->meta($lines, 'name', 'robots', $seo?->robots);
        $this->meta($lines, 'property', 'og:title', $seo?->og_title ?: $title);
        $this->meta($lines, 'property', 'og:description', $seo?->og_description ?: $seo?->description);
        $this->meta($lines, 'property', 'og:image', $this->image($seo));
        $this->meta($lines, 'property', 'og:url', $canonical);

        if (is_array($seo?->schema) && $seo->schema !== []) {
            $json = json_encode($seo->schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($json !== false) {
                $lines[] = '<script type="application/ld+json">'.$json.'</script>';
            }
        }

        return new HtmlString(implode("\n", $lines));
    }

    private function seoMeta(Model $model): ?SeoMeta
    {
        if ($model->relationLoaded('seo')) {
            $seo = $model->getRelation('seo');

            return $seo instanceof SeoMeta ? $seo : null;
        }

        return SeoMeta::query()->whereMorphedTo('seoable', $model)->first();
    }

    /**
     * @param  list<string>  $lines
     */
    private function meta(array &$lines, string $attribute, string $name, ?string $content): void
    {
        if ($content !== null && $content !== '') {
            $lines[] = '<meta '.$attribute.'="'.$name.'" content="'.e($content).'">';
        }
    }

    private function image(?SeoMeta $seo): ?string
    {
        if ($seo?->og_image_id !== null) {
            $media = Media::query()->find($seo->og_image_id);

            if ($media !== null) {
                return $this->absoluteUrl($media->url());
            }
        }

        return $this->absoluteUrl($seo?->og_image_url);
    }

    /**
     * Only web addresses and paths on this site; anything else, such as a
     * javascript: URL, is left out.
     */
    private function absoluteUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return preg_match('#^/(?!/)#', $url) === 1 ? url($url) : null;
    }
}
