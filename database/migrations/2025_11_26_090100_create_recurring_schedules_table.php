<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('job_id')->nullable();
            $table->uuid('technician_id')->nullable();
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'custom'])->default('weekly');
            $table->unsignedTinyInteger('interval')->default(1);
            $table->json('weekdays')->nullable();
            $table->unsignedTinyInteger('month_day')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->string('timezone')->nullable();
            $table->enum('status', ['active', 'paused', 'completed', 'cancelled'])->default('active');
            $table->dateTime('next_occurrence')->nullable();
            $table->json('constraints')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('job_id')->references('id')->on('jobs')->onDelete('set null');
            $table->foreign('technician_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_schedules');
    }
};


