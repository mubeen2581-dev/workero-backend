<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('job_id')->nullable();
            $table->uuid('technician_id')->nullable();
            $table->string('title');
            $table->dateTime('start');
            $table->dateTime('end');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->enum('type', ['job', 'break', 'training', 'maintenance', 'meeting'])->default('job');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->nullable();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('color')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('job_id')->references('id')->on('jobs')->onDelete('set null');
            $table->foreign('technician_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['company_id', 'start', 'end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_events');
    }
};

