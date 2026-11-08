<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Tests\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

/**
 * A tiny WP site living in a test database. Every method inserts rows the
 * way WP would store them and returns the generated ID.
 */
final class SourceSite
{
    public const string NOW = '2026-01-15 10:00:00';

    public function __construct(
        private readonly Connection $connection,
        private readonly string $prefix = 'wp_',
    ) {}

    public function createSchema(): self
    {
        WpSchema::create($this->connection, $this->prefix);

        return $this;
    }

    public function table(string $table): Builder
    {
        return $this->connection->table($this->prefix.$table);
    }

    public function option(string $name, string $value): self
    {
        $this->table('options')->updateOrInsert(['option_name' => $name], ['option_value' => $value]);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function post(array $attributes = []): int
    {
        $title = (string) ($attributes['post_title'] ?? 'Untitled');

        $row = array_merge([
            'post_author' => 1,
            'post_date' => self::NOW,
            'post_date_gmt' => self::NOW,
            'post_content' => '',
            'post_title' => $title,
            'post_excerpt' => '',
            'post_status' => 'publish',
            'post_name' => Str::slug($title),
            'post_modified' => self::NOW,
            'post_modified_gmt' => self::NOW,
            'post_parent' => 0,
            'guid' => '',
            'menu_order' => 0,
            'post_type' => 'post',
            'post_mime_type' => '',
        ], $attributes);

        return (int) $this->table('posts')->insertGetId($row, 'ID');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function page(array $attributes = []): int
    {
        return $this->post(array_merge(['post_type' => 'page'], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function attachment(array $attributes = []): int
    {
        return $this->post(array_merge([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => 'image/jpeg',
            'post_title' => 'image',
        ], $attributes));
    }

    public function acfGroup(string $title = 'Fields'): int
    {
        return $this->post(['post_type' => 'acf-field-group', 'post_title' => $title, 'post_content' => serialize(['location' => []])]);
    }

    /**
     * An ACF field definition below a field group or a parent field.
     *
     * @param  array<string, mixed>  $settings
     */
    public function acfField(int $parent, string $name, string $type, array $settings = [], ?string $key = null, int $order = 0): string
    {
        $key ??= 'field_'.substr(md5($parent.'/'.$name), 0, 13);

        $this->post([
            'post_type' => 'acf-field',
            'post_title' => ucfirst($name),
            'post_name' => $key,
            'post_excerpt' => $name,
            'post_parent' => $parent,
            'menu_order' => $order,
            'post_content' => serialize(['type' => $type, ...$settings]),
        ]);

        return $key;
    }

    public function acfFieldId(string $key): int
    {
        return (int) $this->table('posts')->where('post_type', 'acf-field')->where('post_name', $key)->value('ID');
    }

    /**
     * A field value the way ACF stores it: the value and a reference to the field key.
     */
    public function acfValue(int $postId, string $name, string $key, string $value): self
    {
        return $this->postMeta($postId, $name, $value)->postMeta($postId, '_'.$name, $key);
    }

    public function menu(string $name, ?string $slug = null): int
    {
        return $this->term('nav_menu', $name, $slug === null ? [] : ['slug' => $slug]);
    }

    /**
     * A classic menu item, stored as a post with its settings in post meta.
     *
     * @param  array{title?: string, order?: int, parent?: int, type?: string, object?: string, object_id?: int, url?: string, target?: string, classes?: list<string>, status?: string}  $item
     */
    public function menuItem(int $menu, array $item): int
    {
        $id = $this->post([
            'post_type' => 'nav_menu_item',
            'post_status' => $item['status'] ?? 'publish',
            'post_title' => $item['title'] ?? '',
            'post_name' => '',
            'menu_order' => $item['order'] ?? 0,
        ]);

        $meta = [
            '_menu_item_type' => $item['type'] ?? 'custom',
            '_menu_item_object' => $item['object'] ?? 'custom',
            '_menu_item_object_id' => (string) ($item['object_id'] ?? $id),
            '_menu_item_menu_item_parent' => (string) ($item['parent'] ?? 0),
            '_menu_item_url' => $item['url'] ?? '',
            '_menu_item_target' => $item['target'] ?? '',
            '_menu_item_classes' => serialize($item['classes'] ?? ['']),
            '_menu_item_xfn' => '',
        ];

        foreach ($meta as $key => $value) {
            $this->postMeta($id, $key, $value);
        }

        $this->attachTerm($id, $menu);

        return $id;
    }

    /**
     * @param  array<string, int>  $locations  location => menu term ID
     */
    public function menuLocations(array $locations, string $theme = 'twentytwentyone'): self
    {
        return $this->option('stylesheet', $theme)
            ->option('theme_mods_'.$theme, serialize(['nav_menu_locations' => $locations]));
    }

    public function postMeta(int $postId, string $key, string $value): self
    {
        $this->table('postmeta')->insert(['post_id' => $postId, 'meta_key' => $key, 'meta_value' => $value]);

        return $this;
    }

    /**
     * Creates a term with its taxonomy row and returns the term ID.
     *
     * @param  array<string, mixed>  $attributes  slug, description, parent (term ID)
     */
    public function term(string $taxonomy, string $name, array $attributes = []): int
    {
        $termId = (int) $this->table('terms')->insertGetId([
            'name' => $name,
            'slug' => (string) ($attributes['slug'] ?? Str::slug($name)),
        ], 'term_id');

        $this->table('term_taxonomy')->insert([
            'term_id' => $termId,
            'taxonomy' => $taxonomy,
            'description' => (string) ($attributes['description'] ?? ''),
            'parent' => (int) ($attributes['parent'] ?? 0),
        ]);

        return $termId;
    }

    public function attachTerm(int $objectId, int $termId): self
    {
        $taxonomyId = (int) $this->table('term_taxonomy')->where('term_id', $termId)->value('term_taxonomy_id');

        $this->table('term_relationships')->insert([
            'object_id' => $objectId,
            'term_taxonomy_id' => $taxonomyId,
        ]);

        return $this;
    }

    public function termMeta(int $termId, string $key, string $value): self
    {
        $this->table('termmeta')->insert(['term_id' => $termId, 'meta_key' => $key, 'meta_value' => $value]);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function user(array $attributes = []): int
    {
        $login = (string) ($attributes['user_login'] ?? 'user'.Str::random(6));

        $row = array_merge([
            'user_login' => $login,
            'user_pass' => '$P$BvQhUw2EiJc3cLSm0tn1g/w7qVkT5l.',
            'user_nicename' => Str::slug($login),
            'user_email' => $login.'@example.com',
            'user_url' => '',
            'user_registered' => self::NOW,
            'display_name' => $login,
        ], $attributes);

        return (int) $this->table('users')->insertGetId($row, 'ID');
    }

    public function userMeta(int $userId, string $key, string $value): self
    {
        $this->table('usermeta')->insert(['user_id' => $userId, 'meta_key' => $key, 'meta_value' => $value]);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function comment(int $postId, array $attributes = []): int
    {
        $row = array_merge([
            'comment_post_ID' => $postId,
            'comment_author' => 'Visitor',
            'comment_author_email' => 'visitor@example.com',
            'comment_date' => self::NOW,
            'comment_date_gmt' => self::NOW,
            'comment_content' => 'Nice post.',
            'comment_approved' => '1',
        ], $attributes);

        return (int) $this->table('comments')->insertGetId($row, 'comment_ID');
    }
}
