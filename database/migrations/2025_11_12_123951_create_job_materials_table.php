<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('job_id');
            $table->uuid('item_id'); // Inventory item used
            $table->uuid('issued_from')->nullable(); // warehouse_id or van_stock_id
            $table->string('issued_from_type')->nullable(); // 'warehouse' or 'van'
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 10, 2)->nullable(); // Cost at time of issuance
            $table->decimal('total_cost', 10, 2)->nullable(); // quantity * unit_cost
            $table->enum('status', ['issued', 'used', 'returned', 'wasted'])->default('issued');
            $table->uuid('issued_by')->nullable(); // User who issued the material
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('job_id')->references('id')->on('jobs')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('inventory_items')->onDelete('cascade');
            $table->foreign('issued_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['company_id', 'job_id']);
            $table->index(['company_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_materials');
    }
};

