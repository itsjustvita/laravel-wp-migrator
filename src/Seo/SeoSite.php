<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Seo;

use ItsJustVita\WpMigrator\Source\WpDatabase;
use ItsJustVita\WpMigrator\Support\MalformedSerializedValue;
use ItsJustVita\WpMigrator\Support\Serialized;

/**
 * Site-wide values the SEO template variables need, read once from the source.
 */
class SeoSite
{
    /** Yoast stores the separator as one of these names. */
    private const array YOAST_SEPARATORS = [
        'sc-dash' => '-', 'sc-ndash' => '–', 'sc-mdash' => '—', 'sc-colon' => ':', 'sc-middot' => '·',
        'sc-bull' => '•', 'sc-star' => '*', 'sc-smstar' => '⋆', 'sc-pipe' => '|', 'sc-tilde' => '~',
        'sc-laquo' => '«', 'sc-raquo' => '»', 'sc-lt' => '<', 'sc-gt' => '>',
    ];

    private const string RANK_MATH = 'seo-by-rank-math/rank-math.php';

    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(private readonly WpDatabase $db) {}

    public function siteName(): string
    {
        return $this->remember('blogname', fn (): string => $this->text('blogname'));
    }

    public function siteDescription(): string
    {
        return $this->remember('blogdescription', fn (): string => $this->text('blogdescription'));
    }

    /**
     * The plugin whose site-wide settings the old site applied to posts
     * without settings of their own: the first in order that has any. Null
     * when the site printed the titles of WP itself.
     */
    public function defaultsPlugin(): ?SeoPlugin
    {
        foreach ($this->pluginOrder() as $plugin) {
            if ($this->settings($plugin) !== []) {
                return $plugin;
            }
        }

        return null;
    }

    /**
     * The titles and meta settings of the plugin, by the keys the plugin uses.
     *
     * @return array<array-key, mixed>
     */
    public function settings(SeoPlugin $plugin): array
    {
        return $this->remember('settings.'.$plugin->value, fn (): array => $this->arrayOption(
            $plugin === SeoPlugin::RankMath ? 'rank-math-options-titles' : 'wpseo_titles',
        ));
    }

    /**
     * The ID of the page WP shows on the front page, 0 for the latest posts.
     */
    public function frontPageId(): int
    {
        return $this->remember('front', fn (): int => $this->db->option('show_on_front') === 'page' ? (int) $this->db->option('page_on_front') : 0);
    }

    public function dateFormat(): string
    {
        return $this->remember('date_format', fn (): string => $this->db->option('date_format') ?: 'F j, Y');
    }

    public function timezone(): string
    {
        return $this->db->timezone();
    }

    public function separator(SeoPlugin $plugin): string
    {
        return $this->remember('separator.'.$plugin->value, function () use ($plugin): string {
            if ($plugin === SeoPlugin::RankMath) {
                $separator = $this->settings($plugin)['title_separator'] ?? null;

                return is_string($separator) && $separator !== '' ? $separator : '-';
            }

            $setting = $this->settings($plugin)['separator'] ?? null;

            return is_string($setting) ? (self::YOAST_SEPARATORS[$setting] ?? '-') : '-';
        });
    }

    /**
     * Yoast first unless only Rank Math is active.
     *
     * @return list<SeoPlugin>
     */
    public function pluginOrder(): array
    {
        return $this->remember('plugins', function (): array {
            $active = $this->arrayOption('active_plugins');

            return in_array(self::RANK_MATH, $active, true)
                ? [SeoPlugin::RankMath, SeoPlugin::Yoast]
                : [SeoPlugin::Yoast, SeoPlugin::RankMath];
        });
    }

    public function authorName(?int $id): string
    {
        if ($id === null) {
            return '';
        }

        return $this->remember('author.'.$id, fn (): string => (string) $this->db->table('users')->where('ID', $id)->value('display_name'));
    }

    public function postTitle(?int $id): string
    {
        if ($id === null) {
            return '';
        }

        return $this->remember('post.'.$id, fn (): string => (string) $this->db->table('posts')->where('ID', $id)->value('post_title'));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $resolve
     * @return T
     */
    private function remember(string $key, callable $resolve): mixed
    {
        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $resolve();
        }

        return $this->cache[$key];
    }

    /**
     * A text option as the old site printed it. WP stores the site name and
     * the tagline with HTML entities.
     */
    private function text(string $option): string
    {
        return html_entity_decode((string) $this->db->option($option), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @return array<array-key, mixed>
     */
    private function arrayOption(string $name): array
    {
        try {
            $value = Serialized::decode((string) $this->db->option($name));
        } catch (MalformedSerializedValue) {
            return [];
        }

        return is_array($value) ? $value : [];
    }
}
