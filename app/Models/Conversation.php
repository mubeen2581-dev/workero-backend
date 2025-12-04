<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Conversation extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'title',
        'type',
        'participant_id',
        'participant_type',
        'last_message_at',
        'unread_count',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'unread_count' => 'integer',
    ];

    /**
     * Get the company that owns the conversation.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the participant (client, user, etc.).
     */
    public function participant()
    {
        return $this->morphTo();
    }

    /**
     * Get the messages for the conversation.
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}

