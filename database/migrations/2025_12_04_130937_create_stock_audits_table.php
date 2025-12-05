<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('item_id');
            $table->uuid('warehouse_id')->nullable();
            $table->decimal('expected_quantity', 10, 2);
            $table->decimal('actual_quantity', 10, 2);
            $table->decimal('variance', 10, 2); // actual - expected
            $table->string('reason')->nullable(); // 'damaged', 'lost', 'theft', 'error', 'other'
            $table->text('notes')->nullable();
            $table->uuid('audited_by');
            $table->dateTime('audited_at');
            $table->boolean('adjusted')->default(false); // Whether stock was adjusted based on audit
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('inventory_items')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('set null');
            $table->foreign('audited_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['company_id', 'audited_at']);
            $table->index(['item_id', 'audited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audits');
    }
};
