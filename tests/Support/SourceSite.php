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
