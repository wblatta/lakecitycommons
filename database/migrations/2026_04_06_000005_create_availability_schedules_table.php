<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_schedules', function (Blueprint $table) {
            $table->id();
            $table->enum('resource_type', ['skill', 'item']);
            $table->unsignedBigInteger('resource_id');
            $table->enum('recurrence', ['weekly', 'specific']);
            $table->tinyInteger('day_of_week')->unsigned()->nullable(); // 0=Sun, 6=Sat
            $table->date('specific_date')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();

            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_schedules');
    }
};
