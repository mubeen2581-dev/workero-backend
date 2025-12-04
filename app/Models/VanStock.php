<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class VanStock extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'van_stock';

    protected $fillable = [
        'company_id',
        'item_id',
        'technician_id',
        'quantity',
        'reserved_quantity',
        'location',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'reserved_quantity' => 'decimal:2',
    ];

    /**
     * Get the company that owns the van stock.
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
     * Get the technician who has this stock.
     */
    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * Get available quantity (total - reserved).
     */
    public function getAvailableQuantityAttribute()
    {
        return $this->quantity - $this->reserved_quantity;
    }
}

