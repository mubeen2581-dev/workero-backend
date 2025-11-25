<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XEAIService
{
    private $apiUrl;
    private $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.xe_ai.api_url');
        $this->apiKey = config('services.xe_ai.api_key');
    }

    /**
     * Generate quote using AI.
     */
    public function generateQuote(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->apiUrl}/quote/generate", [
                'description' => $data['description'],
                'job_type' => $data['job_type'] ?? null,
                'location' => $data['location'] ?? null,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('XE AI API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Failed to generate quote');
        } catch (\Exception $e) {
            Log::error('XE AI Service Error', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get AI scheduling recommendations.
     */
    public function getSchedulingRecommendations(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->apiUrl}/scheduling/recommend", [
                'job_id' => $data['job_id'],
                'technicians' => $data['technicians'] ?? [],
                'date_range' => $data['date_range'] ?? null,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('Failed to get scheduling recommendations');
        } catch (\Exception $e) {
            Log::error('XE AI Scheduling Error', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Generate job summary.
     */
    public function generateJobSummary(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->apiUrl}/job/summary", [
                'notes' => $data['notes'] ?? '',
                'photos' => $data['photos'] ?? [],
                'materials_used' => $data['materials_used'] ?? [],
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('Failed to generate job summary');
        } catch (\Exception $e) {
            Log::error('XE AI Summary Error', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Generate quote suggestions using XE AI Workspace
     */
    public function generateQuoteSuggestions(string $description, array $context = []): ?array
    {
        try {
            if (empty($this->apiUrl) || empty($this->apiKey)) {
                // XE AI not configured, return null to use fallback
                return null;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->apiUrl}/quote/suggestions", [
                'description' => $description,
                'context' => $context,
                'smart_pricing' => $context['smart_pricing'] ?? true,
                'company_id' => $context['company_id'] ?? null,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('XE AI Quote Suggestions API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::warning('XE AI Quote Suggestions Error', ['message' => $e->getMessage()]);
            return null; // Return null to trigger fallback
        }
    }
}

