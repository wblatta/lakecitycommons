<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // event|announcement
            $table->string('submitter_name');
            $table->string('submitter_email');
            $table->string('title');
            $table->text('body');
            $table->json('event_fields')->nullable();
            $table->string('status')->default('pending'); // pending|approved|rejected
            $table->string('ip_hash', 64);
            $table->timestamps();
            $table->index('status');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreign('submission_id')->references('id')->on('submissions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['submission_id']);
        });
        Schema::dropIfExists('submissions');
    }
};
