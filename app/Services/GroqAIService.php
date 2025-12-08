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
     * Build the prompt for quote generation with enhanced prompt engineering
     */
    protected function buildQuotePrompt(string $description, bool $smartPricing, ?string $companyId): string
    {
        $prompt = "You are an expert construction estimator analyzing a project request. ";
        $prompt .= "Generate detailed, accurate quote suggestions for the following project:\n\n";
        $prompt .= "PROJECT DESCRIPTION:\n{$description}\n\n";
        
        $prompt .= "ANALYSIS REQUIREMENTS:\n";
        $prompt .= "1. Identify the project type (kitchen, bathroom, electrical, plumbing, HVAC, roofing, general renovation)\n";
        $prompt .= "2. Determine project scope (small, medium, large) based on keywords and description\n";
        $prompt .= "3. Extract mentioned materials, fixtures, and specific requirements\n";
        $prompt .= "4. Assess budget tier indicators (budget, standard, premium, luxury)\n";
        $prompt .= "5. Consider UK construction market rates and regional pricing variations\n\n";
        
        if ($smartPricing) {
            $prompt .= "PRICING REQUIREMENTS:\n";
            $prompt .= "- Use current UK construction market rates (2024-2025)\n";
            $prompt .= "- Labor rates: £50-£100/hour depending on trade and skill level\n";
            $prompt .= "- Material costs should reflect current market prices\n";
            $prompt .= "- Include appropriate profit margins (20-30%)\n";
            $prompt .= "- Break down costs into materials and labor components\n";
            $prompt .= "- Consider project complexity and accessibility factors\n";
            $prompt .= "- Account for permits, inspections, and compliance costs where applicable\n\n";
        }

        $prompt .= "OUTPUT FORMAT:\n";
        $prompt .= "Provide a JSON response with this exact structure:\n";
        $prompt .= "{\n";
        $prompt .= '  "suggestions": [\n';
        $prompt .= '    {\n';
        $prompt .= '      "id": "unique-id",\n';
        $prompt .= '      "title": "Descriptive Package Name (e.g., Complete Kitchen Renovation Package)",\n';
        $prompt .= '      "description": "2-3 sentence explanation of what this package includes and why it fits the project",\n';
        $prompt .= '      "confidence": 85, // Confidence score 0-100\n';
        $prompt .= '      "items": [\n';
        $prompt .= '        {\n';
        $prompt .= '          "name": "Specific Item Name (e.g., Custom Cabinet Installation)",\n';
        $prompt .= '          "description": "Detailed description of the work/item including specifications",\n';
        $prompt .= '          "price": 1000.00, // Total price including materials and labor\n';
        $prompt .= '          "reason": "Clear explanation of why this item is needed for the project",\n';
        $prompt .= '          "category": "Labor" or "Materials" or "Materials & Equipment",\n';
        $prompt .= '          "estimatedHours": 8, // Estimated labor hours (null for pure materials)\n';
        $prompt .= '          "materialCost": 500.00, // Cost of materials only\n';
        $prompt .= '          "laborCost": 500.00 // Cost of labor only (hours × hourly rate)\n';
        $prompt .= '        }\n';
        $prompt .= '      ],\n';
        $prompt .= '      "totalEstimate": 1000.00, // Sum of all item prices\n';
        $prompt .= '      "profitMargin": 25.0 // Target profit margin percentage\n';
        $prompt .= '    }\n';
        $prompt .= '  ]\n';
        $prompt .= "}\n\n";
        
        $prompt .= "GUIDELINES:\n";
        $prompt .= "- Generate 1-3 comprehensive quote suggestions\n";
        $prompt .= "- Each suggestion should be a complete, realistic package\n";
        $prompt .= "- Include all necessary items (labor, materials, equipment, permits)\n";
        $prompt .= "- Prices must be in GBP (£) and realistic for UK market\n";
        $prompt .= "- Be specific about materials and work scope\n";
        $prompt .= "- Consider regional variations (London vs. other UK regions)\n";
        $prompt .= "- Account for project complexity and potential challenges\n";
        $prompt .= "- Ensure profit margins are sustainable (20-30%)\n";
        $prompt .= "- Provide clear, professional descriptions\n";
        $prompt .= "- If project description is vague, ask clarifying questions in the description field\n";

        return $prompt;
    }

    /**
     * Get system prompt for GROQ with enhanced context
     */
    protected function getSystemPrompt(): string
    {
        return "You are an expert construction estimator and quote generator specializing in UK construction and renovation projects. " .
               "You have deep knowledge of:\n" .
               "- UK construction market rates and pricing (2024-2025)\n" .
               "- Material costs, labor rates, and trade-specific pricing\n" .
               "- Project scoping, complexity assessment, and risk factors\n" .
               "- Building regulations, permits, and compliance requirements\n" .
               "- Regional pricing variations across the UK\n" .
               "- Profit margins and business sustainability\n\n" .
               "Your expertise includes:\n" .
               "- Kitchen renovations (cabinets, countertops, appliances, electrical, plumbing)\n" .
               "- Bathroom remodels (fixtures, tiling, plumbing, electrical)\n" .
               "- Electrical work (panel upgrades, rewiring, outlets, lighting)\n" .
               "- Plumbing (repairs, installations, upgrades, heating systems)\n" .
               "- HVAC systems (installation, repair, maintenance)\n" .
               "- Roofing (repairs, replacements, gutters)\n" .
               "- General renovations and construction projects\n\n" .
               "When generating quotes:\n" .
               "- Always provide accurate, realistic estimates\n" .
               "- Break down costs into materials and labor components\n" .
               "- Consider project complexity and accessibility\n" .
               "- Include all necessary items (no hidden costs)\n" .
               "- Use current market rates and prices\n" .
               "- Account for regional variations when applicable\n" .
               "- Ensure sustainable profit margins (20-30%)\n" .
               "- Provide clear, professional descriptions\n" .
               "- Your responses must be valid JSON only, following the exact structure provided.";
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
     * Chat-based quote generation for conversational interface
     */
    public function chatQuoteGeneration(string $message, array $context = []): ?array
    {
        try {
            if (empty($this->apiKey)) {
                return null;
            }

            $conversationHistory = $context['conversation_history'] ?? [];
            $companyId = $context['company_id'] ?? null;

            // Build conversation messages
            $messages = [
                [
                    'role' => 'system',
                    'content' => $this->getChatSystemPrompt(),
                ],
            ];

            // Add conversation history
            foreach ($conversationHistory as $historyItem) {
                if (isset($historyItem['role']) && isset($historyItem['content'])) {
                    $messages[] = [
                        'role' => $historyItem['role'],
                        'content' => $historyItem['content'],
                    ];
                }
            }

            // Add current message
            $messages[] = [
                'role' => 'user',
                'content' => $message,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(45)->post("{$this->apiUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 2000,
                'response_format' => ['type' => 'json_object'],
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $content = $responseData['choices'][0]['message']['content'] ?? null;
                
                if ($content) {
                    $parsed = json_decode($content, true);
                    return $this->formatChatResponse($parsed, $message);
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('GROQ Chat Error', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get system prompt for chat interface
     */
    protected function getChatSystemPrompt(): string
    {
        return "You are a helpful AI assistant specializing in construction quote generation. " .
               "You help users create accurate quotes by:\n" .
               "1. Asking clarifying questions about their project\n" .
               "2. Understanding project requirements from natural language\n" .
               "3. Generating detailed quote suggestions when ready\n" .
               "4. Providing helpful guidance about pricing and materials\n\n" .
               "Be conversational, professional, and helpful. " .
               "When you have enough information, generate quote suggestions in JSON format. " .
               "Always respond in JSON with 'message' (your response text) and optionally 'suggestions' (quote items array).";
    }

    /**
     * Format chat response
     */
    protected function formatChatResponse(array $parsed, string $userMessage): array
    {
        $response = [
            'message' => $parsed['message'] ?? 'I understand. Let me help you with that.',
            'suggestions' => [],
            'needs_clarification' => !isset($parsed['suggestions']) || empty($parsed['suggestions']),
        ];

        // If suggestions are provided, format them
        if (isset($parsed['suggestions']) && is_array($parsed['suggestions'])) {
            $response['suggestions'] = $this->formatGroqResponse(['suggestions' => $parsed['suggestions']]);
        } else {
            // Check if user message contains project description - generate suggestions
            $keywords = ['kitchen', 'bathroom', 'electrical', 'plumbing', 'renovation', 'install', 'repair'];
            $hasProjectKeywords = false;
            foreach ($keywords as $keyword) {
                if (stripos($userMessage, $keyword) !== false) {
                    $hasProjectKeywords = true;
                    break;
                }
            }

            if ($hasProjectKeywords && strlen($userMessage) > 20) {
                // Generate suggestions from the message
                $response['suggestions'] = $this->formatGroqResponse([
                    'suggestions' => [
                        [
                            'id' => uniqid('chat-'),
                            'title' => 'Project Estimate',
                            'description' => 'Based on your description',
                            'confidence' => 70,
                            'items' => [],
                            'totalEstimate' => 0,
                            'profitMargin' => 25.0,
                        ],
                    ],
                ]);
            }
        }

        return $response;
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


