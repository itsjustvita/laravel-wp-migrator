<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ItsJustVita\WpMigrator\Database\Tables;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Tables::prefixed('posts'), function (Blueprint $table): void {
            $table->id();
            $table->string('type', 64)->index();
            $table->string('status', 32)->index();
            $table->text('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('content_raw')->nullable();
            $table->json('blocks')->nullable();
            $table->json('fields')->nullable();
            $table->string('template')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained(Tables::prefixed('posts'))->nullOnDelete();
            $table->integer('menu_order')->default(0);
            $table->unsignedBigInteger('author_id')->nullable()->index();
            $table->foreignId('featured_media_id')->nullable()->constrained(Tables::prefixed('media'))->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->index(['type', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::prefixed('posts'));
    }
};
