<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class QuoteSignature extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'quote_id',
        'user_id',
        'signature_data',
        'signature_type',
        'ip_address',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    /**
     * Get the quote that owns the signature.
     */
    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * Get the user who signed.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
