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
        Schema::create(Tables::prefixed('terms'), function (Blueprint $table): void {
            $table->id();
            $table->string('taxonomy', 64)->index();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained(Tables::prefixed('terms'))->nullOnDelete();
            $table->timestamps();

            $table->index(['taxonomy', 'slug']);
        });

        Schema::create(Tables::prefixed('termables'), function (Blueprint $table): void {
            $table->foreignId('term_id')->constrained(Tables::prefixed('terms'))->cascadeOnDelete();
            $table->morphs('termable');

            $table->unique(['term_id', 'termable_type', 'termable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::prefixed('termables'));
        Schema::dropIfExists(Tables::prefixed('terms'));
    }
};
