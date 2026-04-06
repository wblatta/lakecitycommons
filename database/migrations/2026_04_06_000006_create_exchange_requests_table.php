<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->enum('resource_type', ['skill', 'item']);
            $table->unsignedBigInteger('resource_id');
            $table->dateTime('proposed_datetime');
            $table->decimal('duration_hours', 5, 2)->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'accepted', 'in_progress', 'completed', 'declined', 'cancelled'])
                ->default('pending');
            $table->enum('credit_type', ['gift', 'time_equal', 'custom']);
            $table->decimal('credit_value', 8, 2);
            $table->timestamp('requester_confirmed_at')->nullable();
            $table->timestamp('owner_confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
