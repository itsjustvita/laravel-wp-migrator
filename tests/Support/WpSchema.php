<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Tests\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * Creates the core WP tables on a test connection. The column list follows
 * the documented WP schema; defaults are relaxed so tests can insert rows
 * with only the columns they care about.
 */
final class WpSchema
{
    public static function create(Connection $connection, string $prefix = 'wp_'): void
    {
        $schema = $connection->getSchemaBuilder();

        $schema->create($prefix.'posts', function (Blueprint $table): void {
            $table->bigIncrements('ID');
            $table->unsignedBigInteger('post_author')->default(0);
            $table->dateTime('post_date')->nullable();
            $table->dateTime('post_date_gmt')->nullable();
            $table->longText('post_content')->nullable();
            $table->text('post_title')->nullable();
            $table->text('post_excerpt')->nullable();
            $table->string('post_status', 20)->default('publish');
            $table->string('comment_status', 20)->default('open');
            $table->string('ping_status', 20)->default('open');
            $table->string('post_password')->default('');
            $table->string('post_name', 200)->default('');
            $table->text('to_ping')->nullable();
            $table->text('pinged')->nullable();
            $table->dateTime('post_modified')->nullable();
            $table->dateTime('post_modified_gmt')->nullable();
            $table->longText('post_content_filtered')->nullable();
            $table->unsignedBigInteger('post_parent')->default(0);
            $table->string('guid')->default('');
            $table->integer('menu_order')->default(0);
            $table->string('post_type', 20)->default('post');
            $table->string('post_mime_type', 100)->default('');
            $table->bigInteger('comment_count')->default(0);
        });

        $schema->create($prefix.'postmeta', function (Blueprint $table): void {
            $table->bigIncrements('meta_id');
            $table->unsignedBigInteger('post_id')->default(0);
            $table->string('meta_key')->nullable();
            $table->longText('meta_value')->nullable();
        });

        $schema->create($prefix.'terms', function (Blueprint $table): void {
            $table->bigIncrements('term_id');
            $table->string('name', 200)->default('');
            $table->string('slug', 200)->default('');
            $table->bigInteger('term_group')->default(0);
        });

        $schema->create($prefix.'term_taxonomy', function (Blueprint $table): void {
            $table->bigIncrements('term_taxonomy_id');
            $table->unsignedBigInteger('term_id')->default(0);
            $table->string('taxonomy', 32)->default('');
            $table->longText('description')->nullable();
            $table->unsignedBigInteger('parent')->default(0);
            $table->bigInteger('count')->default(0);
        });

        $schema->create($prefix.'term_relationships', function (Blueprint $table): void {
            $table->unsignedBigInteger('object_id')->default(0);
            $table->unsignedBigInteger('term_taxonomy_id')->default(0);
            $table->integer('term_order')->default(0);
            $table->primary(['object_id', 'term_taxonomy_id']);
        });

        $schema->create($prefix.'termmeta', function (Blueprint $table): void {
            $table->bigIncrements('meta_id');
            $table->unsignedBigInteger('term_id')->default(0);
            $table->string('meta_key')->nullable();
            $table->longText('meta_value')->nullable();
        });

        $schema->create($prefix.'users', function (Blueprint $table): void {
            $table->bigIncrements('ID');
            $table->string('user_login', 60)->default('');
            $table->string('user_pass')->default('');
            $table->string('user_nicename', 50)->default('');
            $table->string('user_email', 100)->default('');
            $table->string('user_url', 100)->default('');
            $table->dateTime('user_registered')->nullable();
            $table->string('user_activation_key')->default('');
            $table->integer('user_status')->default(0);
            $table->string('display_name', 250)->default('');
        });

        $schema->create($prefix.'usermeta', function (Blueprint $table): void {
            $table->bigIncrements('umeta_id');
            $table->unsignedBigInteger('user_id')->default(0);
            $table->string('meta_key')->nullable();
            $table->longText('meta_value')->nullable();
        });

        $schema->create($prefix.'options', function (Blueprint $table): void {
            $table->bigIncrements('option_id');
            $table->string('option_name', 191)->unique();
            $table->longText('option_value');
            $table->string('autoload', 20)->default('yes');
        });

        $schema->create($prefix.'comments', function (Blueprint $table): void {
            $table->bigIncrements('comment_ID');
            $table->unsignedBigInteger('comment_post_ID')->default(0);
            $table->text('comment_author')->nullable();
            $table->string('comment_author_email', 100)->default('');
            $table->string('comment_author_url', 200)->default('');
            $table->string('comment_author_IP', 100)->default('');
            $table->dateTime('comment_date')->nullable();
            $table->dateTime('comment_date_gmt')->nullable();
            $table->text('comment_content')->nullable();
            $table->integer('comment_karma')->default(0);
            $table->string('comment_approved', 20)->default('1');
            $table->string('comment_agent')->default('');
            $table->string('comment_type', 20)->default('comment');
            $table->unsignedBigInteger('comment_parent')->default(0);
            $table->unsignedBigInteger('user_id')->default(0);
        });

        $schema->create($prefix.'commentmeta', function (Blueprint $table): void {
            $table->bigIncrements('meta_id');
            $table->unsignedBigInteger('comment_id')->default(0);
            $table->string('meta_key')->nullable();
            $table->longText('meta_value')->nullable();
        });
    }
}
