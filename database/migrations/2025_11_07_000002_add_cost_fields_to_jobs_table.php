<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->decimal('estimated_cost', 10, 2)->nullable()->after('estimated_duration');
            $table->decimal('actual_cost', 10, 2)->nullable()->after('actual_duration');
            $table->decimal('labor_cost', 10, 2)->nullable()->after('actual_cost');
            $table->decimal('material_cost', 10, 2)->nullable()->after('labor_cost');
            $table->decimal('profit_margin', 5, 2)->nullable()->after('material_cost');
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropColumn(['estimated_cost', 'actual_cost', 'labor_cost', 'material_cost', 'profit_margin']);
        });
    }
};

