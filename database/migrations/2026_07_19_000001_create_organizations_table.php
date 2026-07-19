<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category'); // community|services|business|government
            $table->text('description')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_sponsor')->default(false);
            $table->string('sponsor_tier')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
