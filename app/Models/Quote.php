<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Quote extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'client_id',
        'subtotal',
        'tax_amount',
        'total',
        'profit_margin',
        'status',
        'valid_until',
        'notes',
        'options',
        'has_signature',
        'requires_esignature',
        'esignature_sent_at',
        'esignature_signed_at',
        'esignature_status',
        'package_type',
        'variants',
        'deposit_amount',
        'deposit_percentage',
        'payment_schedule',
        'permit_costs',
        'total_permit_cost',
        'contract_template_id',
        'contract_generated',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'profit_margin' => 'decimal:2',
        'valid_until' => 'datetime',
        'options' => 'array',
        'variants' => 'array',
        'esignature_sent_at' => 'datetime',
        'esignature_signed_at' => 'datetime',
        'deposit_amount' => 'decimal:2',
        'deposit_percentage' => 'decimal:2',
        'payment_schedule' => 'array',
        'permit_costs' => 'array',
        'total_permit_cost' => 'decimal:2',
        'contract_generated' => 'boolean',
    ];

    /**
     * Get the company that owns the quote.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the client that owns the quote.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the items for the quote.
     */
    public function items()
    {
        return $this->hasMany(QuoteItem::class);
    }

    /**
     * Get the jobs created from this quote.
     */
    public function jobs()
    {
        return $this->hasMany(Job::class);
    }

    /**
     * Get the option items for the quote.
     */
    public function optionItems()
    {
        return $this->hasMany(QuoteOptionItem::class);
    }

    /**
     * Get the signatures for the quote.
     */
    public function signatures()
    {
        return $this->hasMany(QuoteSignature::class);
    }

    /**
     * Get items grouped by group_name.
     */
    public function getGroupedItems()
    {
        return $this->items()->orderBy('group_name')->orderBy('sort_order')->get()->groupBy('group_name');
    }
}

