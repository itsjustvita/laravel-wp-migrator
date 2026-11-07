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
        Schema::create(Tables::prefixed('wp_import_map'), function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('checksum', 64);
            $table->boolean('linked')->default(false);
            $table->timestamp('imported_at');

            $table->unique(['source_type', 'source_id']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::prefixed('wp_import_map'));
    }
};
