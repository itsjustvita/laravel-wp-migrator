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
        Schema::create(Tables::prefixed('comments'), function (Blueprint $table): void {
            $table->id();
            $table->morphs('commentable');
            $table->foreignId('parent_id')->nullable()->constrained(Tables::prefixed('comments'))->cascadeOnDelete();
            $table->unsignedBigInteger('author_id')->nullable()->index();
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->string('author_url', 2048)->nullable();
            $table->text('content');
            $table->string('status', 32)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::prefixed('comments'));
    }
};
