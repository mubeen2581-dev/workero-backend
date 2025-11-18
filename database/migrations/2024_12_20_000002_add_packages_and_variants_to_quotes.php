<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (!Schema::hasColumn('quotes', 'package_type')) {
                $table->string('package_type')->nullable()->after('esignature_status'); // basic, standard, premium
            }
            if (!Schema::hasColumn('quotes', 'variants')) {
                $table->json('variants')->nullable()->after('package_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['package_type', 'variants']);
        });
    }
};
