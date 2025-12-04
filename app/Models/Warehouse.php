<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Warehouse extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'contact_person',
        'phone',
        'email',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the company that owns the warehouse.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get inventory items in this warehouse.
     */
    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class, 'warehouse_id');
    }
}

