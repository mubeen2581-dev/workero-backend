<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class QuoteOptionItem extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'quote_id',
        'quote_option_id',
        'name',
        'description',
        'price',
        'is_selected',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_selected' => 'boolean',
    ];

    /**
     * Get the quote that owns the option item.
     */
    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }
}
