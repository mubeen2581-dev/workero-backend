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
     * Generate quote suggestions using XE AI Workspace with enhanced prompt engineering
     */
    public function generateQuoteSuggestions(string $description, array $context = []): ?array
    {
        try {
            if (empty($this->apiUrl) || empty($this->apiKey)) {
                // XE AI not configured, return null to use fallback
                return null;
            }

            // Build enhanced context with historical data if available
            $enhancedContext = $this->buildEnhancedContext($description, $context);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(45)->post("{$this->apiUrl}/quote/suggestions", [
                'description' => $description,
                'context' => $enhancedContext,
                'smart_pricing' => $context['smart_pricing'] ?? true,
                'company_id' => $context['company_id'] ?? null,
                'prompt_version' => 'v2', // Enhanced prompt version
                'include_historical_analysis' => true,
                'include_material_recommendations' => true,
                'market_region' => 'UK',
            ]);

            if ($response->successful()) {
                $result = $response->json();
                
                // Validate and enhance response
                if (isset($result['suggestions']) && is_array($result['suggestions'])) {
                    return $this->validateAndEnhanceResponse($result, $context);
                }
                
                return $result;
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

    /**
     * Build enhanced context with historical pricing data
     */
    protected function buildEnhancedContext(string $description, array $context): array
    {
        $enhancedContext = $context;
        
        // Add historical pricing context if company ID is available
        if (isset($context['company_id']) && $context['company_id']) {
            try {
                $historicalData = $this->getHistoricalPricingContext($context['company_id'], $description);
                $enhancedContext['historical_pricing'] = $historicalData;
            } catch (\Exception $e) {
                Log::debug('Could not fetch historical pricing context', ['error' => $e->getMessage()]);
            }
        }
        
        // Add material catalog context
        $enhancedContext['material_catalog'] = $this->getMaterialCatalogContext();
        
        return $enhancedContext;
    }

    /**
     * Get historical pricing context for similar projects
     */
    protected function getHistoricalPricingContext(string $companyId, string $description): array
    {
        try {
            $quotes = \App\Models\Quote::where('company_id', $companyId)
                ->where('status', 'accepted')
                ->where('created_at', '>=', now()->subMonths(12))
                ->with('items')
                ->get();

            if ($quotes->isEmpty()) {
                return [];
            }

            // Extract average pricing data
            $avgTotal = $quotes->avg('total');
            $avgMargin = $quotes->avg('profit_margin');
            
            // Get item-level statistics
            $itemStats = [];
            foreach ($quotes as $quote) {
                foreach ($quote->items as $item) {
                    $key = strtolower($item->description);
                    if (!isset($itemStats[$key])) {
                        $itemStats[$key] = [];
                    }
                    $itemStats[$key][] = $item->unit_price;
                }
            }

            // Calculate averages
            $avgItemPrices = [];
            foreach ($itemStats as $key => $prices) {
                $avgItemPrices[$key] = [
                    'average' => array_sum($prices) / count($prices),
                    'min' => min($prices),
                    'max' => max($prices),
                    'count' => count($prices),
                ];
            }

            return [
                'total_quotes' => $quotes->count(),
                'average_total' => $avgTotal,
                'average_margin' => $avgMargin,
                'item_statistics' => $avgItemPrices,
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching historical pricing context', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get material catalog context
     */
    protected function getMaterialCatalogContext(): array
    {
        // Return common material pricing ranges for UK market
        return [
            'countertops' => [
                'laminate' => ['min' => 30, 'max' => 80, 'unit' => 'per_sqft'],
                'quartz' => ['min' => 50, 'max' => 150, 'unit' => 'per_sqft'],
                'granite' => ['min' => 40, 'max' => 120, 'unit' => 'per_sqft'],
                'marble' => ['min' => 60, 'max' => 200, 'unit' => 'per_sqft'],
            ],
            'cabinets' => [
                'particle_board' => ['min' => 50, 'max' => 150, 'unit' => 'per_linear_ft'],
                'plywood' => ['min' => 100, 'max' => 250, 'unit' => 'per_linear_ft'],
                'solid_wood' => ['min' => 200, 'max' => 500, 'unit' => 'per_linear_ft'],
            ],
            'labor_rates' => [
                'general' => ['min' => 50, 'max' => 75, 'unit' => 'per_hour'],
                'specialized' => ['min' => 75, 'max' => 100, 'unit' => 'per_hour'],
                'premium' => ['min' => 100, 'max' => 150, 'unit' => 'per_hour'],
            ],
        ];
    }

    /**
     * Validate and enhance XE AI response
     */
    protected function validateAndEnhanceResponse(array $result, array $context): array
    {
        // Ensure all suggestions have required fields
        foreach ($result['suggestions'] as &$suggestion) {
            if (!isset($suggestion['id'])) {
                $suggestion['id'] = uniqid('xe-');
            }
            if (!isset($suggestion['confidence'])) {
                $suggestion['confidence'] = 75;
            }
            if (!isset($suggestion['profitMargin'])) {
                $suggestion['profitMargin'] = 25.0;
            }
            
            // Ensure items have proper structure
            if (isset($suggestion['items']) && is_array($suggestion['items'])) {
                foreach ($suggestion['items'] as &$item) {
                    if (!isset($item['price'])) {
                        $item['price'] = ($item['materialCost'] ?? 0) + ($item['laborCost'] ?? 0);
                    }
                    if (!isset($item['category'])) {
                        $item['category'] = 'Labor';
                    }
                }
            }
            
            // Recalculate total if needed
            if (isset($suggestion['items']) && is_array($suggestion['items'])) {
                $suggestion['totalEstimate'] = array_sum(array_column($suggestion['items'], 'price'));
            }
        }
        
        return $result;
    }

    /**
     * Chat-based quote generation for conversational interface
     */
    public function chatQuoteGeneration(string $message, array $context = []): ?array
    {
        try {
            if (empty($this->apiUrl) || empty($this->apiKey)) {
                return null;
            }

            $conversationHistory = $context['conversation_history'] ?? [];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(45)->post("{$this->apiUrl}/quote/chat", [
                'message' => $message,
                'conversation_history' => $conversationHistory,
                'context' => $context,
                'smart_pricing' => $context['smart_pricing'] ?? true,
                'company_id' => $context['company_id'] ?? null,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('XE AI Chat Error', ['message' => $e->getMessage()]);
            return null;
        }
    }
}

