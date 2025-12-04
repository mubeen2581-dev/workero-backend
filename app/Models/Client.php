<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Client extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'phone',
        'address',
        'tags',
        'lead_score',
    ];

    protected $casts = [
        'address' => 'array',
        'tags' => 'array',
        'lead_score' => 'integer',
    ];

    /**
     * Get the company that owns the client.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the leads for the client.
     */
    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Get the quotes for the client.
     */
    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Get the jobs for the client.
     */
    public function jobs()
    {
        return $this->hasMany(Job::class);
    }

    /**
     * Get the invoices for the client.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}

