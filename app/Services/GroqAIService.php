<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqAIService
{
    private $apiKey;
    private $apiUrl = 'https://api.groq.com/openai/v1';
    private $model = 'llama-3.1-70b-versatile'; // Fast and capable model

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key');
    }

    /**
     * Generate quote suggestions using GROQ AI
     */
    public function generateQuoteSuggestions(string $description, array $context = []): ?array
    {
        try {
            if (empty($this->apiKey)) {
                // GROQ not configured, return null to use fallback
                return null;
            }

            $smartPricing = $context['smart_pricing'] ?? true;
            $companyId = $context['company_id'] ?? null;

            // Build a comprehensive prompt for quote generation
            $prompt = $this->buildQuotePrompt($description, $smartPricing, $companyId);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->apiUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 2000,
                'response_format' => ['type' => 'json_object'],
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $content = $responseData['choices'][0]['message']['content'] ?? null;
                
                if ($content) {
                    $suggestions = json_decode($content, true);
                    return $this->formatGroqResponse($suggestions);
                }
            }

            Log::warning('GROQ API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::warning('GROQ Quote Suggestions Error', ['message' => $e->getMessage()]);
            return null; // Return null to trigger fallback
        }
    }

    /**
     * Build the prompt for quote generation
     */
    protected function buildQuotePrompt(string $description, bool $smartPricing, ?string $companyId): string
    {
        $prompt = "Generate a detailed quote for the following construction/renovation project:\n\n";
        $prompt .= "Project Description: {$description}\n\n";
        
        if ($smartPricing) {
            $prompt .= "Requirements:\n";
            $prompt .= "- Use smart pricing with market rates\n";
            $prompt .= "- Include material costs and labor costs separately\n";
            $prompt .= "- Provide realistic estimates based on UK construction market\n";
            $prompt .= "- Consider project scope and complexity\n";
        }

        $prompt .= "\nPlease provide a JSON response with the following structure:\n";
        $prompt .= "{\n";
        $prompt .= '  "suggestions": [\n';
        $prompt .= '    {\n';
        $prompt .= '      "id": "unique-id",\n';
        $prompt .= '      "title": "Package Name",\n';
        $prompt .= '      "description": "Detailed description",\n';
        $prompt .= '      "confidence": 85,\n';
        $prompt .= '      "items": [\n';
        $prompt .= '        {\n';
        $prompt .= '          "name": "Item Name",\n';
        $prompt .= '          "description": "Item description",\n';
        $prompt .= '          "price": 1000.00,\n';
        $prompt .= '          "reason": "Why this item is needed",\n';
        $prompt .= '          "category": "Labor" or "Materials",\n';
        $prompt .= '          "estimatedHours": 8,\n';
        $prompt .= '          "materialCost": 500.00,\n';
        $prompt .= '          "laborCost": 500.00\n';
        $prompt .= '        }\n';
        $prompt .= '      ],\n';
        $prompt .= '      "totalEstimate": 1000.00,\n';
        $prompt .= '      "profitMargin": 25.0\n';
        $prompt .= '    }\n';
        $prompt .= '  ]\n';
        $prompt .= "}\n\n";
        $prompt .= "Generate 1-3 comprehensive quote suggestions based on the project description. ";
        $prompt .= "Ensure all prices are in GBP (£) and realistic for UK construction market.";

        return $prompt;
    }

    /**
     * Get system prompt for GROQ
     */
    protected function getSystemPrompt(): string
    {
        return "You are an expert construction estimator and quote generator. " .
               "You specialize in creating accurate, detailed quotes for construction and renovation projects in the UK. " .
               "You understand construction materials, labor costs, project timelines, and market pricing. " .
               "Always provide realistic estimates based on current UK construction market rates. " .
               "Break down costs into materials and labor when possible. " .
               "Your responses must be valid JSON only.";
    }

    /**
     * Format GROQ response to our internal format
     */
    protected function formatGroqResponse(array $groqResponse): array
    {
        $formatted = [];
        
        if (isset($groqResponse['suggestions']) && is_array($groqResponse['suggestions'])) {
            foreach ($groqResponse['suggestions'] as $suggestion) {
                $formatted[] = [
                    'id' => $suggestion['id'] ?? uniqid('groq-'),
                    'title' => $suggestion['title'] ?? 'AI Generated Quote',
                    'description' => $suggestion['description'] ?? '',
                    'confidence' => $suggestion['confidence'] ?? 80,
                    'items' => $this->formatGroqItems($suggestion['items'] ?? []),
                    'totalEstimate' => $suggestion['totalEstimate'] ?? 0,
                    'profitMargin' => $suggestion['profitMargin'] ?? 25.0,
                ];
            }
        }

        return $formatted;
    }

    /**
     * Format GROQ items to our format
     */
    protected function formatGroqItems(array $items): array
    {
        $formatted = [];
        
        foreach ($items as $item) {
            $formatted[] = [
                'name' => $item['name'] ?? 'Unnamed Item',
                'description' => $item['description'] ?? '',
                'price' => floatval($item['price'] ?? 0),
                'reason' => $item['reason'] ?? 'Recommended for this project',
                'category' => $item['category'] ?? 'Labor',
                'estimatedHours' => isset($item['estimatedHours']) ? intval($item['estimatedHours']) : null,
                'materialCost' => floatval($item['materialCost'] ?? 0),
                'laborCost' => floatval($item['laborCost'] ?? 0),
            ];
        }

        return $formatted;
    }

    /**
     * Generate quote using GROQ AI (alternative method)
     */
    public function generateQuote(array $data): array
    {
        try {
            if (empty($this->apiKey)) {
                throw new \Exception('GROQ API key not configured');
            }

            $prompt = "Generate a quote for:\n";
            $prompt .= "Description: {$data['description']}\n";
            if (isset($data['job_type'])) {
                $prompt .= "Job Type: {$data['job_type']}\n";
            }
            if (isset($data['location'])) {
                $prompt .= "Location: {$data['location']}\n";
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->apiUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('GROQ API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Failed to generate quote');
        } catch (\Exception $e) {
            Log::error('GROQ Service Error', ['message' => $e->getMessage()]);
            throw $e;
        }
    }
}


