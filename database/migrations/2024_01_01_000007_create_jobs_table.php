<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('client_id');
            $table->uuid('quote_id')->nullable();
            $table->string('title');
            $table->text('description');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->decimal('estimated_duration', 5, 2)->nullable();
            $table->decimal('actual_duration', 5, 2)->nullable();
            $table->uuid('assigned_technician')->nullable();
            $table->dateTime('scheduled_date');
            $table->dateTime('completed_date')->nullable();
            $table->json('location');
            $table->json('materials')->nullable();
            $table->json('photos')->nullable();
            $table->text('notes')->nullable();
            $table->text('signature')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('quote_id')->references('id')->on('quotes')->onDelete('set null');
            $table->foreign('assigned_technician')->references('id')->on('users')->onDelete('set null');
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};

