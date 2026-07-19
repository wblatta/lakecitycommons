<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('source_id')->nullable()->after('organization_id')->constrained('sources')->nullOnDelete();
            $table->string('external_uid')->nullable()->after('submission_id');
            $table->unique(['source_id', 'external_uid']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['source_id', 'external_uid']);
            $table->dropForeign(['source_id']);
            $table->dropColumn(['source_id', 'external_uid']);
        });
    }
};
