<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('item_id');
            $table->enum('type', ['in', 'out', 'transfer']);
            $table->decimal('quantity', 10, 2);
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
            $table->string('reason');
            $table->string('reference')->nullable();
            $table->uuid('performed_by');
            $table->dateTime('performed_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('inventory_items')->onDelete('cascade');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['company_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};

