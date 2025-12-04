<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class InventoryItem extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'name',
        'description',
        'sku',
        'barcode',
        'category',
        'quantity',
        'current_stock',
        'min_quantity',
        'min_stock',
        'max_quantity',
        'max_stock',
        'unit_price',
        'cost_price',
        'reorder_point',
        'location',
        'last_audit_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'current_stock' => 'decimal:2',
        'min_quantity' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'max_quantity' => 'decimal:2',
        'max_stock' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'reorder_point' => 'decimal:2',
        'last_audit_date' => 'date',
    ];

    /**
     * Get the company that owns the item.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the warehouse that stores this item.
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the stock movements for the item.
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'item_id');
    }

    /**
     * Get van stock records for this item.
     */
    public function vanStock()
    {
        return $this->hasMany(VanStock::class, 'item_id');
    }

    /**
     * Get job materials that use this item.
     */
    public function jobMaterials()
    {
        return $this->hasMany(JobMaterial::class, 'item_id');
    }
}

