<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            if (!Schema::hasColumn('quote_items', 'group_name')) {
                $table->string('group_name')->nullable()->after('quote_id'); // e.g., "Labor", "Materials", "Optional Add-ons"
            }
            if (!Schema::hasColumn('quote_items', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('group_name');
            }
            if (!Schema::hasColumn('quote_items', 'option_type')) {
                $table->string('option_type')->nullable()->after('sort_order'); // 'good', 'better', 'best', 'optional', 'required'
            }
            if (!Schema::hasColumn('quote_items', 'material_choice_id')) {
                $table->uuid('material_choice_id')->nullable()->after('option_type'); // Reference to material choice
            }
            if (!Schema::hasColumn('quote_items', 'material_options')) {
                $table->json('material_options')->nullable()->after('material_choice_id'); // Available material choices
            }
            if (!Schema::hasColumn('quote_items', 'is_optional')) {
                $table->boolean('is_optional')->default(false)->after('material_options');
            }
            if (!Schema::hasColumn('quote_items', 'category')) {
                $table->string('category')->nullable()->after('is_optional'); // e.g., "Equipment", "Permits", "Materials"
            }
        });
    }

    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn([
                'group_name',
                'sort_order',
                'option_type',
                'material_choice_id',
                'material_options',
                'is_optional',
                'category'
            ]);
        });
    }
};
