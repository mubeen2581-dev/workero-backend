<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Company extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'settings',
        'subscription_tier',
        'is_active',
    ];

    protected $casts = [
        'address' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the users for the company.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the clients for the company.
     */
    public function clients()
    {
        return $this->hasMany(Client::class);
    }
}

