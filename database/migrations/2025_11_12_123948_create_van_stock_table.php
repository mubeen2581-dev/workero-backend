<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('van_stock', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('item_id');
            $table->uuid('technician_id'); // The technician/driver who has this stock in their van
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('reserved_quantity', 10, 2)->default(0); // Reserved for specific jobs
            $table->string('location')->nullable(); // Optional: specific location in van
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('inventory_items')->onDelete('cascade');
            $table->foreign('technician_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['company_id', 'item_id', 'technician_id']); // One record per item per technician
            $table->index(['company_id', 'technician_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('van_stock');
    }
};

