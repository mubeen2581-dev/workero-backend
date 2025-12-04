<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Conversation;
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

        // Update conversation last message timestamp
        $conversation->update([
            'last_message_at' => now(),
            'unread_count' => DB::raw('unread_count + 1'),
        ]);

        return $this->success(
            $message->load('sender', 'receiver')->toArray(),
            'Message sent successfully',
            201
        );
    }

    public function threads(Request $request)
    {
        $companyId = $this->getCompanyId();
        $threads = Conversation::where('company_id', $companyId)
            ->with('participant')
            ->orderBy('last_message_at', 'desc')
            ->paginate(10);
        
        return $this->paginated($threads->items(), [
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
            ->with('sender', 'receiver')
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
}

