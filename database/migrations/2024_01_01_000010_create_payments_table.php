<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['credit_card', 'bank_transfer', 'check', 'cash', 'xe_pay', 'apple_pay', 'google_pay']);
            $table->date('payment_date');
            $table->string('reference');
            $table->text('notes')->nullable();
            $table->enum('status', ['completed', 'pending', 'failed', 'processing'])->default('pending');
            $table->string('xe_pay_transaction_id')->nullable();
            $table->boolean('is_deposit')->default(false);
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

