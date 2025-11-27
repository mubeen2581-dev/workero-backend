<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TechnicianAvailability extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'technician_id',
        'day_of_week',
        'is_available',
        'start_time',
        'end_time',
        'timezone',
        'effective_from',
        'effective_to',
        'max_hours_per_day',
        'max_jobs_per_day',
        'metadata',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_available' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'metadata' => 'array',
    ];

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
