<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->enum('offer_type', ['gift', 'lend'])->default('lend')->after('is_available');
            $table->boolean('is_archived')->default(false)->after('offer_type');
        });

        // Migrate existing gift items
        DB::statement("UPDATE items SET offer_type = 'gift' WHERE credit_type = 'gift'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['offer_type', 'is_archived']);
        });
    }
};
