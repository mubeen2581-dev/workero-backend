<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringSchedule extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'job_id',
        'technician_id',
        'frequency',
        'interval',
        'weekdays',
        'month_day',
        'start_date',
        'end_date',
        'timezone',
        'status',
        'next_occurrence',
        'constraints',
    ];

    protected $casts = [
        'weekdays' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'next_occurrence' => 'datetime',
        'constraints' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function events()
    {
        return $this->hasMany(ScheduleEvent::class);
    }
}
