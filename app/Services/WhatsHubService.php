<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsHubService
{
    private $apiUrl;
    private $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.whats_hub.api_url');
        $this->apiKey = config('services.whats_hub.api_key');
    }

    /**
     * Send a WhatsApp message.
     */
    public function sendMessage(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->apiUrl}/messages/send", [
                'to' => $data['to'],
                'type' => $data['type'] ?? 'text',
                'body' => $data['body'] ?? '',
                'template_id' => $data['template_id'] ?? null,
                'media_url' => $data['media_url'] ?? null,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('WhatsHub API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Failed to send WhatsApp message');
        } catch (\Exception $e) {
            Log::error('WhatsHub Service Error', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * List conversation threads.
     */
    public function listThreads(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get("{$this->apiUrl}/threads");

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('Failed to list threads');
        } catch (\Exception $e) {
            Log::error('WhatsHub List Threads Error', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get messages for a thread.
     */
    public function listMessages(string $threadId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get("{$this->apiUrl}/threads/{$threadId}/messages");

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('Failed to list messages');
        } catch (\Exception $e) {
            Log::error('WhatsHub List Messages Error', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Process webhook.
     */
    public function processWebhook(array $payload, string $signature): bool
    {
        // TODO: Verify webhook signature
        // TODO: Process WhatsApp webhook events
        
        return true;
    }
}

