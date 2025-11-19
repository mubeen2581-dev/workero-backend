<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (!Schema::hasColumn('quotes', 'deposit_amount')) {
                $table->decimal('deposit_amount', 10, 2)->nullable()->after('total');
            }
            if (!Schema::hasColumn('quotes', 'deposit_percentage')) {
                $table->decimal('deposit_percentage', 5, 2)->nullable()->after('deposit_amount');
            }
            if (!Schema::hasColumn('quotes', 'payment_schedule')) {
                $table->json('payment_schedule')->nullable()->after('deposit_percentage');
            }
            if (!Schema::hasColumn('quotes', 'permit_costs')) {
                $table->json('permit_costs')->nullable()->after('payment_schedule');
            }
            if (!Schema::hasColumn('quotes', 'total_permit_cost')) {
                $table->decimal('total_permit_cost', 10, 2)->default(0)->after('permit_costs');
            }
            if (!Schema::hasColumn('quotes', 'contract_template_id')) {
                $table->uuid('contract_template_id')->nullable()->after('total_permit_cost');
            }
            if (!Schema::hasColumn('quotes', 'contract_generated')) {
                $table->boolean('contract_generated')->default(false)->after('contract_template_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn([
                'deposit_amount',
                'deposit_percentage',
                'payment_schedule',
                'permit_costs',
                'total_permit_cost',
                'contract_template_id',
                'contract_generated',
            ]);
        });
    }
};
