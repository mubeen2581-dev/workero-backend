<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->uuid('recurring_schedule_id')->nullable()->after('job_id');
            $table->unsignedSmallInteger('travel_time_minutes')->nullable()->after('priority');
            $table->unsignedSmallInteger('buffer_minutes')->nullable()->after('travel_time_minutes');
            $table->unsignedSmallInteger('flexibility_minutes')->default(0)->after('buffer_minutes');
            $table->json('metadata')->nullable()->after('color');

            $table->foreign('recurring_schedule_id')
                ->references('id')
                ->on('recurring_schedules')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->dropForeign(['recurring_schedule_id']);
            $table->dropColumn([
                'recurring_schedule_id',
                'travel_time_minutes',
                'buffer_minutes',
                'flexibility_minutes',
                'metadata',
            ]);
        });
    }
};


