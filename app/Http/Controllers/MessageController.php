<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Conversation;
use App\Models\Notification;
use App\Events\MessageSent;
use App\Events\NotificationCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId();
        $messages = Message::where('company_id', $companyId)->paginate(10);
        
        return $this->paginated($messages->items(), [
            'page' => $messages->currentPage(),
            'limit' => $messages->perPage(),
            'total' => $messages->total(),
            'totalPages' => $messages->lastPage(),
        ]);
    }

    public function send(Request $request)
    {
        $companyId = $this->getCompanyId();
        $user = auth()->user();

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'conversation_id' => 'required|uuid|exists:conversations,id',
            'receiver_id' => 'required|uuid',
            'receiver_type' => 'required|in:App\Models\User,App\Models\Client',
            'content' => 'required_without:attachments|string|max:5000',
            'type' => 'sometimes|in:text,image,file,voice,template',
            'attachments' => 'sometimes|array',
            'attachments.*' => 'file|max:10240', // 10MB max per file
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        // Verify conversation belongs to company
        $conversation = Conversation::where('company_id', $companyId)
            ->findOrFail($request->input('conversation_id'));

        // Handle file uploads
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('messages/' . $companyId, 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'url' => Storage::disk('public')->url($path),
                ];
            }
        }

        // Determine message type based on attachments
        $messageType = $request->input('type', 'text');
        if (!empty($attachments)) {
            $firstAttachment = $attachments[0];
            if (str_starts_with($firstAttachment['mime_type'], 'image/')) {
                $messageType = 'image';
            } elseif (str_starts_with($firstAttachment['mime_type'], 'audio/') || str_starts_with($firstAttachment['mime_type'], 'video/')) {
                $messageType = 'voice';
            } else {
                $messageType = 'file';
            }
        }

        $receiverId = $request->input('receiver_id');
        $receiverType = $request->input('receiver_type');

        $message = Message::create([
            'company_id' => $companyId,
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => 'App\Models\User',
            'receiver_id' => $receiverId,
            'receiver_type' => $receiverType,
            'type' => $messageType,
            'content' => $request->input('content', ''),
            'attachments' => !empty($attachments) ? $attachments : null,
            'is_read' => false,
        ]);

        // Update conversation last message timestamp and increment unread count
        $conversation->last_message_at = now();
        $conversation->increment('unread_count');
        $conversation->save();

        // Create notification for receiver
        if ($receiverType === 'App\Models\User') {
            $notification = Notification::create([
                'company_id' => $companyId,
                'user_id' => $receiverId,
                'type' => 'message',
                'title' => 'New Message',
                'body' => ($user->first_name . ' ' . $user->last_name) . ': ' . substr($request->input('content', 'Sent an attachment'), 0, 100),
                'data' => [
                    'message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                    'sender_id' => $user->id,
                    'sender_name' => $user->first_name . ' ' . $user->last_name,
                ],
            ]);

            // Broadcast notification event
            event(new NotificationCreated($notification));
        }

        // Broadcast message event
        event(new MessageSent($message->load('sender', 'receiver', 'conversation')));

        return $this->success(
            $message->load('sender', 'receiver')->toArray(),
            'Message sent successfully',
            201
        );
    }

    public function threads(Request $request)
    {
        $companyId = $this->getCompanyId();
        $user = auth()->user();
        
        // Get conversations where user is either sender or receiver
        $threads = Conversation::where('company_id', $companyId)
            ->with(['participant', 'messages' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(1);
            }])
            ->orderBy('last_message_at', 'desc')
            ->paginate(20);
        
        // Format threads with participant info
        $formattedThreads = $threads->map(function($thread) use ($user) {
            $participant = $thread->participant;
            $lastMessage = $thread->messages->first();
            
            // Determine title from participant
            $title = $thread->title;
            if (!$title && $participant) {
                if (method_exists($participant, 'name')) {
                    $title = $participant->name;
                } elseif (isset($participant->first_name)) {
                    $title = ($participant->first_name ?? '') . ' ' . ($participant->last_name ?? '');
                } elseif (isset($participant->email)) {
                    $title = $participant->email;
                }
            }
            
            return [
                'id' => $thread->id,
                'company_id' => $thread->company_id,
                'title' => $title ?: 'Conversation',
                'type' => $thread->type,
                'participant_id' => $thread->participant_id,
                'participant_type' => $thread->participant_type,
                'participant' => $participant ? [
                    'id' => $participant->id,
                    'name' => $title,
                    'email' => $participant->email ?? null,
                ] : null,
                'last_message_at' => $thread->last_message_at?->toISOString(),
                'unread_count' => $thread->unread_count ?? 0,
                'last_message' => $lastMessage ? [
                    'id' => $lastMessage->id,
                    'content' => $lastMessage->content,
                    'type' => $lastMessage->type,
                    'timestamp' => $lastMessage->created_at->toISOString(),
                ] : null,
                'created_at' => $thread->created_at->toISOString(),
                'updated_at' => $thread->updated_at->toISOString(),
            ];
        });
        
        return $this->paginated($formattedThreads->toArray(), [
            'page' => $threads->currentPage(),
            'limit' => $threads->perPage(),
            'total' => $threads->total(),
            'totalPages' => $threads->lastPage(),
        ]);
    }

    public function uploadFile(Request $request)
    {
        $companyId = $this->getCompanyId();

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $file = $request->file('file');
        $path = $file->store('messages/' . $companyId, 'public');

        return $this->success([
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'url' => Storage::disk('public')->url($path),
        ], 'File uploaded successfully', 201);
    }

    public function threadMessages(Request $request, string $id)
    {
        $companyId = $this->getCompanyId();
        
        $conversation = Conversation::where('company_id', $companyId)
            ->findOrFail($id);

        $messages = Message::where('conversation_id', $conversation->id)
            ->with(['sender' => function($query) {
                $query->select('id', 'first_name', 'last_name', 'email');
            }, 'receiver' => function($query) {
                $query->select('id', 'first_name', 'last_name', 'email');
            }])
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        return $this->paginated($messages->items(), [
            'page' => $messages->currentPage(),
            'limit' => $messages->perPage(),
            'total' => $messages->total(),
            'totalPages' => $messages->lastPage(),
        ]);
    }

    public function templates()
    {
        // Return message templates
        return $this->success([
            [
                'id' => 'quote_sent',
                'name' => 'Quote Sent',
                'content' => 'Your quote has been sent. Please review and let us know if you have any questions.',
            ],
            [
                'id' => 'job_scheduled',
                'name' => 'Job Scheduled',
                'content' => 'Your job has been scheduled. We will arrive on {date} at {time}.',
            ],
            [
                'id' => 'payment_reminder',
                'name' => 'Payment Reminder',
                'content' => 'This is a reminder that invoice #{invoice_number} is due on {due_date}.',
            ],
        ]);
    }

    /**
     * Search messages
     * 
     * GET /api/messages/search
     */
    public function search(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'query' => 'required|string|min:1|max:200',
            'conversation_id' => 'nullable|uuid|exists:conversations,id',
            'type' => 'nullable|in:text,image,file,voice,template',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $companyId = $this->getCompanyId();
        $query = $request->input('query');
        
        $messagesQuery = Message::where('company_id', $companyId)
            ->where(function ($q) use ($query) {
                $q->where('content', 'like', '%' . $query . '%')
                  ->orWhereHas('sender', function ($senderQuery) use ($query) {
                      $senderQuery->where('name', 'like', '%' . $query . '%');
                  });
            });

        // Filter by conversation
        if ($request->has('conversation_id')) {
            $messagesQuery->where('conversation_id', $request->input('conversation_id'));
        }

        // Filter by type
        if ($request->has('type')) {
            $messagesQuery->where('type', $request->input('type'));
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $messagesQuery->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $messagesQuery->where('created_at', '<=', $request->input('date_to'));
        }

        $messages = $messagesQuery->with('sender', 'receiver', 'conversation')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('limit', 20));

        return $this->paginated($messages->items(), [
            'page' => $messages->currentPage(),
            'limit' => $messages->perPage(),
            'total' => $messages->total(),
            'totalPages' => $messages->lastPage(),
        ]);
    }

    /**
     * Mark message as read
     * 
     * PUT /api/messages/{id}/read
     */
    public function markAsRead(Request $request, string $id)
    {
        $companyId = $this->getCompanyId();
        $user = auth()->user();

        $message = Message::where('company_id', $companyId)
            ->where('receiver_id', $user->id)
            ->findOrFail($id);

        if (!$message->is_read) {
            $message->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            // Update conversation unread count
            $conversation = $message->conversation;
            if ($conversation && $conversation->unread_count > 0) {
                $conversation->decrement('unread_count');
            }
        }

        return $this->success($message->toArray(), 'Message marked as read');
    }
}

