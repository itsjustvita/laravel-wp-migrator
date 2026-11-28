<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Seo;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Content\Autop;
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
 * Imports what Yoast SEO and Rank Math printed for a post into seo_meta: the
 * settings of the post itself and, where it has none, what the site-wide
 * settings for its post type gave it. A site without either plugin printed
 * the title of WP itself, which is imported the same way.
 *
 * Template variables in titles and descriptions are resolved now, because the
 * new site has no plugin to resolve them. Variables that cannot be resolved,
 * such as %%currentyear%%, are left out and listed in the report.
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

    /** Rank Math cuts the descriptions it builds from a template at this length. */
    private const int DESCRIPTION_LENGTH = 160;

    /** What the plugins fall back to when the title template of a post type is empty. */
    private const array TITLE_TEMPLATE = ['yoast' => '%%title%% %%page%% %%sep%% %%sitename%%', 'rank-math' => '%title% %sep% %sitename%'];

    /** @var array<string, true> variables of site-wide templates that were reported */
    private array $reported = [];

    public function __construct(
        private readonly SeoSite $site,
        private readonly UrlRewriter $urls,
        private readonly Repository $config,
        private readonly Autop $autop,
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

        $defaults = $this->config->boolean('wp-migrator.seo.site_defaults', true);
        $attributes['robots'] = $this->robots($post, $context, $defaults);

        if ($defaults) {
            // A template of the post that came out empty counts as none.
            if (($attributes['title'] ?? '') === '') {
                $attributes['title'] = $this->defaultTitle($post, $context);
            }

            if (($attributes['description'] ?? '') === '') {
                $attributes['description'] = $this->defaultDescription($post, $context);
            }
        }

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

    /**
     * The title the site gave a post without one of its own: from the
     * template of the plugin for the post type, or the one WP prints itself.
     */
    private function defaultTitle(SourcePost $post, ImportContext $context): ?string
    {
        $plugin = $this->site->defaultsPlugin();

        if ($plugin === null) {
            $parts = $post->id === $this->site->frontPageId()
                ? [$this->site->siteName(), $this->site->siteDescription()]
                : [$post->title, $this->site->siteName()];

            // WP joins the parts with a hyphen and prints it as a dash.
            return self::filled(implode(' – ', array_filter($parts, fn (string $part): bool => trim($part) !== '')));
        }

        $template = $this->site->settings($plugin)[$plugin === SeoPlugin::RankMath ? 'pt_'.$post->type.'_title' : 'title-'.$post->type] ?? null;

        return self::filled($this->render(
            is_string($template) && trim($template) !== '' ? $template : self::TITLE_TEMPLATE[$plugin->value],
            $plugin,
            $post,
            $context,
            siteWide: true,
        ));
    }

    /**
     * The description the plugin gave a post without one of its own. Rank
     * Math takes the excerpt first and cuts what its template yields; WP
     * itself prints no description.
     */
    private function defaultDescription(SourcePost $post, ImportContext $context): ?string
    {
        $plugin = $this->site->defaultsPlugin();

        if ($plugin === null) {
            return null;
        }

        if ($plugin === SeoPlugin::RankMath && trim($post->excerpt) !== '') {
            return self::filled(self::plain($post->excerpt));
        }

        $template = $this->site->settings($plugin)[$plugin === SeoPlugin::RankMath ? 'pt_'.$post->type.'_description' : 'metadesc-'.$post->type] ?? null;

        if (! is_string($template) || trim($template) === '') {
            return null;
        }

        $description = self::plain($this->render($template, $plugin, $post, $context, siteWide: true));

        return self::filled($plugin === SeoPlugin::RankMath ? self::cut($description, self::DESCRIPTION_LENGTH) : $description);
    }

    private static function filled(string $text): ?string
    {
        $text = trim($text);

        return $text === '' ? null : $text;
    }

    private static function plain(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
    }

    /**
     * At most the given length, ending at a word.
     */
    private static function cut(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $cut = mb_substr($text, 0, $length);
        $space = mb_strrpos(trim($cut), ' ');

        return rtrim($space === false ? $cut : mb_substr($cut, 0, $space));
    }

    /**
     * @param  bool  $siteWide  the template belongs to the site, not the post: what it cannot resolve is reported once
     */
    private function render(string $template, SeoPlugin $plugin, SourcePost $post, ImportContext $context, bool $siteWide = false): string
    {
        $rendered = preg_replace_callback($plugin->variablePattern(), function (array $matches) use ($plugin, $post, $context, $siteWide): string {
            $value = $this->variable(strtolower($matches[1]), $plugin, $post);

            if ($value === null) {
                if (! $siteWide || ! isset($this->reported[$matches[0]])) {
                    $context->report->unresolved($context->importer, $post->id, 'seo variable', $matches[0]);
                }

                if ($siteWide) {
                    $this->reported[$matches[0]] = true;
                }

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
            'excerpt' => $plugin === SeoPlugin::RankMath ? $this->rankMathExcerpt($post) : $this->excerpt($post),
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

    /**
     * What Rank Math puts for %excerpt%: the excerpt, or else a paragraph of
     * the content, the first with the focus keyword or simply the first.
     */
    private function rankMathExcerpt(SourcePost $post): string
    {
        if (trim($post->excerpt) !== '') {
            return self::plain($post->excerpt);
        }

        // Without shortcodes, then in paragraphs as WP would print it.
        $content = (string) preg_replace('/\[[^\]]*\]/', '', $post->content);
        preg_match_all('#<p[^>]*>(.*?)</p>#su', $this->autop->format($content), $matches);

        $paragraphs = array_values(array_filter(
            array_map(fn (string $paragraph): string => self::plain(html_entity_decode($paragraph, ENT_QUOTES | ENT_HTML5, 'UTF-8')), $matches[1]),
            fn (string $paragraph): bool => $paragraph !== '',
        ));

        $keyword = trim(explode(',', (string) $post->meta->get('rank_math_focus_keyword'))[0]);

        if ($keyword !== '') {
            foreach ($paragraphs as $paragraph) {
                if (mb_stripos($paragraph, $keyword) !== false) {
                    return $paragraph;
                }
            }
        }

        return $paragraphs[0] ?? self::plain(html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
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

    /**
     * The robots meta as the old site printed it, without what changes
     * nothing: index, follow and limits of -1.
     *
     * @param  bool  $defaults  whether the settings of the site count where the post has none
     */
    private function robots(SourcePost $post, ImportContext $context, bool $defaults): ?string
    {
        $siteWide = $defaults ? $this->site->defaultsPlugin() : null;
        $directives = [];

        foreach ($this->site->pluginOrder() as $plugin) {
            $directives = $plugin === SeoPlugin::Yoast
                ? $this->yoastRobots($post, $siteWide === SeoPlugin::Yoast && self::switchedOn($this->site->settings($plugin)['noindex-'.$post->type] ?? null))
                : $this->rankMathRobots($post, $context);

            if ($directives !== []) {
                break;
            }
        }

        if ($directives === [] && $siteWide === SeoPlugin::RankMath) {
            $directives = $this->rankMathSiteRobots($post);
        }

        $directives = array_values(array_unique([...$directives, ...$this->previews($post, $directives, $defaults)]));

        return $directives === [] ? null : implode(', ', $directives);
    }

    /**
     * What Rank Math sets for every post of the type, when that is switched
     * on, or else for the whole site. A plain "index" is the default anyway.
     *
     * @return list<string>
     */
    private function rankMathSiteRobots(SourcePost $post): array
    {
        $settings = $this->site->settings(SeoPlugin::RankMath);
        $directives = self::switchedOn($settings['pt_'.$post->type.'_custom_robots'] ?? null)
            ? self::directives($settings['pt_'.$post->type.'_robots'] ?? null)
            : [];

        if ($directives === []) {
            $directives = self::directives($settings['robots_global'] ?? null);
        }

        return $directives === ['index'] ? [] : $directives;
    }

    /**
     * @return list<string>
     */
    private static function directives(mixed $robots): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn (mixed $directive): string => is_scalar($directive) ? trim((string) $directive) : '', is_array($robots) ? $robots : []),
            fn (string $directive): bool => $directive !== '',
        )));
    }

    /**
     * How much of a page search engines may show in their results: the
     * max-image-preview, max-snippet and max-video-preview directives. WP
     * allows large image previews on every page, and so do the plugins
     * unless a setting says otherwise. None of them prints these next to
     * noindex.
     *
     * @param  list<string>  $directives  what the robots meta holds so far
     * @return list<string>
     */
    private function previews(SourcePost $post, array $directives, bool $defaults): array
    {
        if (in_array('noindex', $directives, true)) {
            return [];
        }

        $own = self::previewValues($post->meta->get('rank_math_advanced_robots'));

        if (! $defaults) {
            return self::previewDirectives($own);
        }

        return match ($this->site->defaultsPlugin()) {
            // Rank Math prints none next to nosnippet.
            SeoPlugin::RankMath => in_array('nosnippet', $directives, true) ? [] : self::previewDirectives($this->rankMathPreviews($post, $own)),
            // Yoast turns noimageindex into a preview of none.
            SeoPlugin::Yoast => [in_array('noimageindex', $directives, true) ? 'max-image-preview:none' : 'max-image-preview:large'],
            null => ['max-image-preview:large'],
        };
    }

    /**
     * The previews Rank Math allows for a post: its own, those of its post
     * type when that is switched on, or those of the site.
     *
     * @param  array<string, string>  $own
     * @return array<string, string>
     */
    private function rankMathPreviews(SourcePost $post, array $own): array
    {
        if ($own !== []) {
            return $own;
        }

        $settings = $this->site->settings(SeoPlugin::RankMath);

        if (self::switchedOn($settings['pt_'.$post->type.'_custom_robots'] ?? null)) {
            $type = self::previewValues($settings['pt_'.$post->type.'_advanced_robots'] ?? null);

            if ($type !== []) {
                return $type;
            }
        }

        return [...['max-image-preview' => 'large'], ...self::previewValues($settings['advanced_robots_global'] ?? null)];
    }

    /**
     * The preview settings Rank Math stores, as an array or serialized, with
     * values a robots meta can hold. What comes from the old site ends up in
     * a tag of the new one, so anything else is dropped.
     *
     * @return array<string, string>
     */
    private static function previewValues(mixed $stored): array
    {
        if (is_string($stored) && $stored !== '') {
            try {
                $stored = Serialized::decode($stored);
            } catch (MalformedSerializedValue) {
                return [];
            }
        }

        $values = [];

        foreach (is_array($stored) ? $stored : [] as $key => $value) {
            $value = is_scalar($value) ? trim((string) $value) : '';

            $valid = match ($key) {
                'max-image-preview' => in_array($value, ['none', 'standard', 'large'], true),
                // Rank Math leaves out 0.
                'max-snippet', 'max-video-preview' => preg_match('/^-?[1-9][0-9]*$/', $value) === 1,
                default => false,
            };

            if ($valid) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     * @return list<string>
     */
    private static function previewDirectives(array $values): array
    {
        $directives = [];

        foreach (['max-snippet', 'max-video-preview', 'max-image-preview'] as $key) {
            // A limit of -1 is no limit, which search engines assume anyway.
            if (isset($values[$key]) && $values[$key] !== '-1') {
                $directives[] = $key.':'.$values[$key];
            }
        }

        return $directives;
    }

    /**
     * The plugins store a switch as "on" and "off", as a boolean or as "1".
     */
    private static function switchedOn(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'on';
    }

    /**
     * @param  bool  $typeNoindex  Yoast keeps the posts of this type out of the index unless a post says otherwise
     * @return list<string>
     */
    private function yoastRobots(SourcePost $post, bool $typeNoindex): array
    {
        $directives = [];

        $index = $post->meta->get('_yoast_wpseo_meta-robots-noindex');

        if ($index === '1' || ($typeNoindex && $index !== '2')) {
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
