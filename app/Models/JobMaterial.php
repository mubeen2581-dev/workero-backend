<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class JobMaterial extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'job_id',
        'item_id',
        'issued_from',
        'issued_from_type',
        'quantity',
        'unit_cost',
        'total_cost',
        'status',
        'issued_by',
        'issued_at',
        'returned_at',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'issued_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    /**
     * Get the company that owns the job material.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the job that uses this material.
     */
    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * Get the inventory item.
     */
    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    /**
     * Get the user who issued the material.
     */
    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}

