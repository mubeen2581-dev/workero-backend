<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_availabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('technician_id')->nullable();
            $table->unsignedTinyInteger('day_of_week')->comment('0 = Sunday');
            $table->boolean('is_available')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('timezone')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->unsignedSmallInteger('max_hours_per_day')->nullable();
            $table->unsignedSmallInteger('max_jobs_per_day')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('technician_id')->references('id')->on('users')->onDelete('set null');
            $table->unique(['company_id', 'technician_id', 'day_of_week'], 'technician_day_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_availabilities');
    }
};


