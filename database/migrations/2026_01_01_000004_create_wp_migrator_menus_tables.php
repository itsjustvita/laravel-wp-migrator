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
        Schema::create(Tables::prefixed('menus'), function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('locations')->nullable();
            $table->timestamps();
        });

        Schema::create(Tables::prefixed('menu_items'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained(Tables::prefixed('menus'))->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained(Tables::prefixed('menu_items'))->cascadeOnDelete();
            $table->string('title');
            $table->string('url', 2048)->nullable();
            $table->nullableMorphs('linkable');
            $table->unsignedInteger('order')->default(0);
            $table->string('target', 32)->nullable();
            $table->json('classes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::prefixed('menu_items'));
        Schema::dropIfExists(Tables::prefixed('menus'));
    }
};
