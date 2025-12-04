<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL doesn't support modifying ENUM directly, so we need to alter the column
        // For MySQL, we'll change it to a string temporarily, then back to enum with all values
        
        if (DB::getDriverName() === 'mysql') {
            // First, change enum to string to allow any value
            DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(255) NOT NULL DEFAULT 'technician'");
            
            // Then change back to enum with all values
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'technician', 'dispatcher', 'warehouse', 'client') NOT NULL DEFAULT 'technician'");
        } else {
            // For other databases (PostgreSQL, SQLite), modify the enum
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'manager', 'technician', 'dispatcher', 'warehouse', 'client'])
                    ->default('technician')
                    ->change();
            });
        }
    }

    public function down(): void
    {
        // Revert to original enum values
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(255) NOT NULL DEFAULT 'technician'");
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'technician', 'dispatcher') NOT NULL DEFAULT 'technician'");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'manager', 'technician', 'dispatcher'])
                    ->default('technician')
                    ->change();
            });
        }
    }
};

