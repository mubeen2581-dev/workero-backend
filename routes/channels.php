<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('company.{companyId}', function ($user, $companyId) {
    return (string) $user->company_id === (string) $companyId;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (string) $user->id === (string) $userId;
});

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    // Check if user is part of the conversation
    $conversation = \App\Models\Conversation::find($conversationId);
    if (!$conversation) {
        return false;
    }
    
    // User can listen if they are the participant or if they're in the same company
    return (string) $user->company_id === (string) $conversation->company_id;
});

