<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Lead extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'client_id',
        'source',
        'status',
        'priority',
        'estimated_value',
        'notes',
        'assigned_to',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
    ];

    /**
     * Get the company that owns the lead.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the client that owns the lead.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the user assigned to the lead.
     */
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the activities for the lead.
     */
    public function activities()
    {
        return $this->hasMany(LeadActivity::class)->orderBy('created_at', 'desc');
    }
}

