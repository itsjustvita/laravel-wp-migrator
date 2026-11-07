<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Seo;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Content\Urls\UrlRewriter;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Models\SeoMeta;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;
use ItsJustVita\WpMigrator\Source\Dto\SourceTerm;
use ItsJustVita\WpMigrator\Support\MalformedSerializedValue;
use ItsJustVita\WpMigrator\Support\Serialized;

/**
 * Imports the per-post settings of Yoast SEO and Rank Math into seo_meta.
 *
 * Template variables in titles and descriptions are resolved now, because the
 * new site has no plugin to resolve them. Variables that cannot be resolved,
 * such as %%currentyear%%, are left out and listed in the report. Site-wide
 * default templates are not applied: a post without its own title gets none.
 */
class SeoImporter
{
    /** @var array<string, array<string, string>> meta keys per attribute and plugin */
    private const array KEYS = [
        'title' => ['yoast' => '_yoast_wpseo_title', 'rank-math' => 'rank_math_title'],
        'description' => ['yoast' => '_yoast_wpseo_metadesc', 'rank-math' => 'rank_math_description'],
        'canonical' => ['yoast' => '_yoast_wpseo_canonical', 'rank-math' => 'rank_math_canonical_url'],
        'og_title' => ['yoast' => '_yoast_wpseo_opengraph-title', 'rank-math' => 'rank_math_facebook_title'],
        'og_description' => ['yoast' => '_yoast_wpseo_opengraph-description', 'rank-math' => 'rank_math_facebook_description'],
        'og_image_url' => ['yoast' => '_yoast_wpseo_opengraph-image', 'rank-math' => 'rank_math_facebook_image'],
        'og_image_id' => ['yoast' => '_yoast_wpseo_opengraph-image-id', 'rank-math' => 'rank_math_facebook_image_id'],
    ];

    private const array TEMPLATES = ['title', 'description', 'og_title', 'og_description'];

    private const array PRIMARY_CATEGORY = ['yoast' => '_yoast_wpseo_primary_category', 'rank-math' => 'rank_math_primary_category'];

    private const int EXCERPT_LENGTH = 155;

    public function __construct(
        private readonly SeoSite $site,
        private readonly UrlRewriter $urls,
        private readonly Repository $config,
    ) {}

    public function sync(Model $model, SourcePost $post, ImportContext $context): void
    {
        if (! $this->config->boolean('wp-migrator.seo.enabled', true)) {
            return;
        }

        $attributes = $this->resolve($post, $context);
        $existing = SeoMeta::query()->whereMorphedTo('seoable', $model)->first();

        if ($attributes === null) {
            $existing?->delete();

            return;
        }

        $seo = $existing ?? new SeoMeta(['seoable_type' => $model->getMorphClass(), 'seoable_id' => $model->getKey()]);
        $seo->fill($attributes)->save();
    }

    /**
     * @return array<string, mixed>|null null when the post has no SEO settings
     */
    public function resolve(SourcePost $post, ImportContext $context): ?array
    {
        $attributes = array_fill_keys(['title', 'description', 'canonical', 'robots', 'og_title', 'og_description', 'og_image_url', 'og_image_id'], null);

        foreach (self::KEYS as $attribute => $keys) {
            foreach ($this->site->pluginOrder() as $plugin) {
                $raw = trim((string) $post->meta->get($keys[$plugin->value]));

                if ($raw === '') {
                    continue;
                }

                $attributes[$attribute] = in_array($attribute, self::TEMPLATES, true)
                    ? $this->render($raw, $plugin, $post, $context)
                    : $raw;

                break;
            }
        }

        $attributes['robots'] = $this->robots($post, $context);

        if ($attributes['canonical'] !== null) {
            $attributes['canonical'] = $this->urls->rewrite($attributes['canonical'], $post->id, $context);
        }

        $imageId = $attributes['og_image_id'];
        $attributes['og_image_id'] = is_string($imageId) && ctype_digit($imageId) && (int) $imageId > 0
            ? $context->resolve(SourceType::Post, (int) $imageId, $post->id, 'seo image', Media::class)
            : null;

        // A resolved image makes its old URL redundant.
        $attributes['og_image_url'] = $attributes['og_image_id'] !== null || $attributes['og_image_url'] === null
            ? null
            : $this->urls->rewrite($attributes['og_image_url'], $post->id, $context);

        $attributes = array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $attributes);

        return array_filter($attributes, fn (mixed $value): bool => $value !== null) === [] ? null : $attributes;
    }

    private function render(string $template, SeoPlugin $plugin, SourcePost $post, ImportContext $context): string
    {
        $rendered = preg_replace_callback($plugin->variablePattern(), function (array $matches) use ($plugin, $post, $context): string {
            $value = $this->variable(strtolower($matches[1]), $plugin, $post);

            if ($value === null) {
                $context->report->unresolved($context->importer, $post->id, 'seo variable', $matches[0]);

                return '';
            }

            return $value;
        }, $template) ?? $template;

        return $this->tidySeparators(trim((string) preg_replace('/\s+/u', ' ', $rendered)), $plugin);
    }

    /**
     * Variables that came out empty leave separators behind, as in "Title -"
     * or "Title - - Site". Only separators between spaces count, so a title
     * such as "Top-10" stays as it is.
     */
    private function tidySeparators(string $text, SeoPlugin $plugin): string
    {
        $separators = array_unique([$this->site->separator($plugin), '-', '–', '—', '|', '·', '•', '»', '«', '~', '*']);
        $any = implode('|', array_map(fn (string $separator): string => preg_quote($separator, '/'), $separators));

        $text = (string) preg_replace('/(?<=^|\s)('.$any.')(?:\s+(?:'.$any.'))+(?=\s|$)/u', '$1', $text);
        $text = (string) preg_replace('/^(?:'.$any.')\s+|\s+(?:'.$any.')$/u', '', $text);

        return trim($text);
    }

    private function variable(string $name, SeoPlugin $plugin, SourcePost $post): ?string
    {
        return match ($name) {
            'title' => $post->title,
            'sitename' => $this->site->siteName(),
            'sitedesc' => $this->site->siteDescription(),
            'sep' => $this->site->separator($plugin),
            'excerpt' => $this->excerpt($post),
            'excerpt_only' => $post->excerpt,
            'category' => $this->firstCategory($post)->name ?? '',
            'primary_category' => $this->primaryCategory($post, $plugin)->name ?? '',
            'date' => $this->date($post),
            'name' => $this->site->authorName($post->authorId),
            'parent_title' => $this->site->postTitle($post->parentId),
            'page', 'pagenumber', 'pagetotal' => '',
            default => null,
        };
    }

    private function excerpt(SourcePost $post): string
    {
        if ($post->excerpt !== '') {
            return $post->excerpt;
        }

        $text = (string) preg_replace('/\[[^\]]*\]/', ' ', $post->content);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if (mb_strlen($text) <= self::EXCERPT_LENGTH) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::EXCERPT_LENGTH);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space === false ? $cut : mb_substr($cut, 0, $space), ' ,;:');
    }

    private function firstCategory(SourcePost $post): ?SourceTerm
    {
        foreach ($post->terms as $term) {
            if ($term->taxonomy === 'category') {
                return $term;
            }
        }

        return null;
    }

    private function primaryCategory(SourcePost $post, SeoPlugin $plugin): ?SourceTerm
    {
        $id = (int) $post->meta->get(self::PRIMARY_CATEGORY[$plugin->value]);

        foreach ($post->terms as $term) {
            if ($term->taxonomy === 'category' && $term->id === $id) {
                return $term;
            }
        }

        return $this->firstCategory($post);
    }

    private function date(SourcePost $post): string
    {
        if ($post->publishedAt === null) {
            return '';
        }

        return CarbonImmutable::instance($post->publishedAt)->setTimezone($this->site->timezone())->format($this->site->dateFormat());
    }

    private function robots(SourcePost $post, ImportContext $context): ?string
    {
        foreach ($this->site->pluginOrder() as $plugin) {
            $directives = $plugin === SeoPlugin::Yoast ? $this->yoastRobots($post) : $this->rankMathRobots($post, $context);

            if ($directives !== []) {
                return implode(', ', array_values(array_unique($directives)));
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function yoastRobots(SourcePost $post): array
    {
        $directives = [];

        $index = $post->meta->get('_yoast_wpseo_meta-robots-noindex');

        if ($index === '1') {
            $directives[] = 'noindex';
        } elseif ($index === '2') {
            $directives[] = 'index';
        }

        if ($post->meta->get('_yoast_wpseo_meta-robots-nofollow') === '1') {
            $directives[] = 'nofollow';
        }

        foreach (explode(',', (string) $post->meta->get('_yoast_wpseo_meta-robots-adv')) as $directive) {
            $directive = trim($directive);

            if ($directive !== '' && $directive !== '-' && $directive !== 'none') {
                $directives[] = $directive;
            }
        }

        return $directives;
    }

    /**
     * @return list<string>
     */
    private function rankMathRobots(SourcePost $post, ImportContext $context): array
    {
        $raw = $post->meta->get('rank_math_robots');

        if ($raw === null || $raw === '') {
            return [];
        }

        try {
            $value = Serialized::decode($raw);
        } catch (MalformedSerializedValue) {
            $context->warn($post->id, 'The Rank Math robots setting is malformed and was left out.');

            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $directive): string => is_scalar($directive) ? trim((string) $directive) : '', is_array($value) ? $value : [$value]),
            fn (string $directive): bool => $directive !== '',
        ));
    }
}
