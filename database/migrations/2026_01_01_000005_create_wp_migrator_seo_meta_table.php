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
        Schema::create(Tables::prefixed('seo_meta'), function (Blueprint $table): void {
            $table->id();
            $table->string('seoable_type');
            $table->unsignedBigInteger('seoable_id');
            $table->string('title', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('canonical', 2048)->nullable();
            $table->string('robots', 128)->nullable();
            $table->string('og_title', 500)->nullable();
            $table->text('og_description')->nullable();
            $table->foreignId('og_image_id')->nullable()->constrained(Tables::prefixed('media'))->nullOnDelete();
            $table->string('og_image_url', 2048)->nullable();
            $table->json('schema')->nullable();
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::prefixed('seo_meta'));
    }
};
