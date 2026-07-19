<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048)->nullable();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('content_hash', 64);
            $table->string('kind'); // news|event|notice
            $table->timestamp('published_at')->nullable();
            $table->timestamp('fetched_at');
            $table->string('status')->default('new'); // new|in_digest|ignored
            $table->timestamps();
            $table->unique(['source_id', 'content_hash']);
            $table->index(['status', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_items');
    }
};
