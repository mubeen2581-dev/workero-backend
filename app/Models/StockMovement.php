<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class StockMovement extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'item_id',
        'type',
        'quantity',
        'from_location',
        'to_location',
        'reason',
        'reference',
        'performed_by',
        'performed_at',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'performed_at' => 'datetime',
    ];

    /**
     * Get the company that owns the movement.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the inventory item.
     */
    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    /**
     * Get the user who performed the movement.
     */
    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}

