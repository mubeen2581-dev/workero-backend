<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class QuoteItem extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'quote_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'line_total',
        'group_name',
        'sort_order',
        'option_type',
        'material_choice_id',
        'material_options',
        'is_optional',
        'category',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'line_total' => 'decimal:2',
        'material_options' => 'array',
        'is_optional' => 'boolean',
    ];

    /**
     * Get the quote that owns the item.
     */
    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }
}

