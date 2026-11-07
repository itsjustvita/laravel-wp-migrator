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
        Schema::create(Tables::prefixed('media'), function (Blueprint $table): void {
            $table->id();
            $table->string('disk', 64);
            $table->string('path', 500);
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->text('alt')->nullable();
            $table->text('caption')->nullable();
            $table->string('title', 500)->nullable();
            $table->json('variants')->nullable();
            $table->timestamps();

            $table->index(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::prefixed('media'));
    }
};
