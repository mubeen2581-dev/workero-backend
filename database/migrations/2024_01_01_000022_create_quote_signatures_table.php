<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_signatures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quote_id');
            $table->uuid('user_id')->nullable();
            $table->text('signature_data')->nullable();
            $table->string('signature_type')->default('electronic'); // electronic, handwritten
            $table->string('ip_address')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->foreign('quote_id')->references('id')->on('quotes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['quote_id', 'signed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_signatures');
    }
};
