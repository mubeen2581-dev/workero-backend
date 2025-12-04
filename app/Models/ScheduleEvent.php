<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class ScheduleEvent extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'job_id',
        'technician_id',
        'recurring_schedule_id',
        'title',
        'start',
        'end',
        'status',
        'type',
        'priority',
        'description',
        'location',
        'color',
        'travel_time_minutes',
        'buffer_minutes',
        'flexibility_minutes',
        'metadata',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the company that owns the event.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the job associated with the event.
     */
    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * Get the technician assigned to the event.
     */
    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function recurringSchedule()
    {
        return $this->belongsTo(RecurringSchedule::class);
    }
}

