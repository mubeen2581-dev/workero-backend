<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use Carbon\Carbon;

class GoogleCalendarConnection extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'user_id',
        'google_email',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'calendar_id',
        'sync_settings',
        'last_sync_at',
        'last_sync_status',
        'last_sync_error',
        'is_active',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'sync_settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }

        return $this->token_expires_at->isPast();
    }

    public function needsRefresh(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }

        // Refresh if expires within 5 minutes
        return $this->token_expires_at->subMinutes(5)->isPast();
    }
}
