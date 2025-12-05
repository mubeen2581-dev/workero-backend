<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class StockAudit extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'item_id',
        'warehouse_id',
        'expected_quantity',
        'actual_quantity',
        'variance',
        'reason',
        'notes',
        'audited_by',
        'audited_at',
        'adjusted',
    ];

    protected $casts = [
        'expected_quantity' => 'decimal:2',
        'actual_quantity' => 'decimal:2',
        'variance' => 'decimal:2',
        'audited_at' => 'datetime',
        'adjusted' => 'boolean',
    ];

    /**
     * Get the company that owns the audit.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the inventory item being audited.
     */
    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    /**
     * Get the warehouse where the audit was performed.
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the user who performed the audit.
     */
    public function auditor()
    {
        return $this->belongsTo(User::class, 'audited_by');
    }
}
