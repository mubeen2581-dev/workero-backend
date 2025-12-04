<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Message extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'conversation_id',
        'sender_id',
        'sender_type',
        'receiver_id',
        'receiver_type',
        'type',
        'content',
        'attachments',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Get the company that owns the message.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the conversation.
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the sender.
     */
    public function sender()
    {
        return $this->morphTo();
    }

    /**
     * Get the receiver.
     */
    public function receiver()
    {
        return $this->morphTo();
    }
}

