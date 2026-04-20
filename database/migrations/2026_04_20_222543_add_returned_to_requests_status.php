<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE requests MODIFY status ENUM('pending','accepted','in_progress','completed','declined','cancelled','returned') NOT NULL DEFAULT 'pending'");
        }
        // SQLite doesn't enforce enum constraints, so this is a no-op for SQLite
        // The 'returned' status value will work fine in application code
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE requests MODIFY status ENUM('pending','accepted','in_progress','completed','declined','cancelled') NOT NULL DEFAULT 'pending'");
        }
        // SQLite doesn't enforce enum constraints, so this is a no-op for SQLite
    }
};
