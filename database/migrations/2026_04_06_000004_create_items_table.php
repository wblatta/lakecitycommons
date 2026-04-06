<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->enum('condition', ['excellent', 'good', 'fair', 'poor']);
            $table->enum('credit_type', ['gift', 'time_equal', 'custom']);
            $table->decimal('custom_credit_value', 8, 2)->nullable();
            $table->boolean('is_available')->default(true);
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
