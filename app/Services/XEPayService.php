<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XEPayService
{
    private $apiUrl;
    private $apiKey;
    private $webhookSecret;

    public function __construct()
    {
        $this->apiUrl = config('services.xe_pay.api_url');
        $this->apiKey = config('services.xe_pay.api_key');
        $this->webhookSecret = config('services.xe_pay.webhook_secret');
    }

    /**
     * Create a payment link for an invoice.
     */
    public function createPaymentLink(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->apiUrl}/links", [
                'invoice_id' => $data['invoice_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'GBP',
                'description' => $data['description'] ?? '',
                'return_url' => $data['return_url'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('XE Pay API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Failed to create payment link');
        } catch (\Exception $e) {
            Log::error('XE Pay Service Error', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Check payment status.
     */
    public function checkPaymentStatus(string $transactionId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get("{$this->apiUrl}/transactions/{$transactionId}");

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('Failed to check payment status');
        } catch (\Exception $e) {
            Log::error('XE Pay Status Check Error', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Process webhook.
     */
    public function processWebhook(array $payload, string $signature): bool
    {
        // TODO: Verify webhook signature
        // TODO: Process payment webhook events
        
        return true;
    }
}

