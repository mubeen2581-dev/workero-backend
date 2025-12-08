<?php

namespace App\Http\Controllers;

use App\Services\AIQuoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AIQuoteController extends Controller
{
    protected $aiQuoteService;

    public function __construct(AIQuoteService $aiQuoteService)
    {
        $this->aiQuoteService = $aiQuoteService;
    }

    /**
     * Generate AI quote suggestions
     * 
     * POST /api/quotes/ai/generate
     */
    public function generateSuggestions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'required|string|min:10|max:2000',
            'smart_pricing' => 'nullable|boolean',
            'use_groq' => 'nullable|boolean', // Option to use GROQ AI
            'use_xe_ai' => 'nullable|boolean', // Option to force XE AI or use fallback
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        try {
            $companyId = $this->getCompanyId();
            $description = $request->input('description');
            $smartPricing = $request->input('smart_pricing', true);
            $useGroq = $request->input('use_groq', true); // Default to GROQ if available
            $useXEAIService = $request->input('use_xe_ai', false);

            $suggestions = [];
            $source = 'rule_based';

            $context = [
                'company_id' => $companyId,
                'smart_pricing' => $smartPricing,
            ];

            if ($useGroq) {
                // Try GROQ AI first (primary AI service)
                $suggestions = $this->aiQuoteService->generateWithGroqAI($description, $context);
                $source = 'groq_ai';
            } elseif ($useXEAIService) {
                // Try XE AI Workspace
                $suggestions = $this->aiQuoteService->generateWithXEAIService($description, $context);
                $source = 'xe_ai';
            } else {
                // Use rule-based AI with historical pricing
                $suggestions = $this->aiQuoteService->generateQuoteSuggestions(
                    $description,
                    $smartPricing,
                    $companyId
                );
                $source = 'rule_based';
            }

            return $this->success([
                'suggestions' => $suggestions,
                'count' => count($suggestions),
                'source' => $source,
            ], 'AI suggestions generated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to generate AI suggestions: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get historical pricing analysis for similar projects
     * 
     * GET /api/quotes/ai/historical-pricing
     */
    public function getHistoricalPricing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_type' => 'required|string',
            'item_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        try {
            $companyId = $this->getCompanyId();
            $projectType = $request->input('project_type');
            $itemName = $request->input('item_name');

            // Get historical quotes for analysis (extended to 12 months for better data)
            $historicalQuotes = \App\Models\Quote::where('company_id', $companyId)
                ->where('status', 'accepted')
                ->where('created_at', '>=', now()->subMonths(12))
                ->with('items')
                ->get();

            // Enhanced analysis with trend detection
            $analysis = [
                'total_quotes' => $historicalQuotes->count(),
                'average_total' => $historicalQuotes->avg('total'),
                'average_profit_margin' => $historicalQuotes->avg('profit_margin'),
                'project_type' => $projectType,
                'price_trend' => $this->calculatePriceTrend($historicalQuotes),
                'seasonal_patterns' => $this->detectSeasonalPatterns($historicalQuotes),
            ];

            // Project type specific analysis
            $projectTypeQuotes = $historicalQuotes->filter(function ($quote) use ($projectType) {
                $quoteDescription = strtolower($quote->notes ?? '');
                return stripos($quoteDescription, $projectType) !== false;
            });

            if ($projectTypeQuotes->count() > 0) {
                $analysis['project_type_specific'] = [
                    'count' => $projectTypeQuotes->count(),
                    'average_total' => $projectTypeQuotes->avg('total'),
                    'average_margin' => $projectTypeQuotes->avg('profit_margin'),
                ];
            }

            if ($itemName) {
                // Enhanced item analysis with similarity matching
                $itemPrices = [];
                $itemQuantities = [];
                
                foreach ($historicalQuotes as $quote) {
                    foreach ($quote->items as $item) {
                        $similarity = $this->calculateStringSimilarity(
                            strtolower($item->description),
                            strtolower($itemName)
                        );
                        
                        // Include items with > 60% similarity
                        if ($similarity > 0.6) {
                            $itemPrices[] = $item->unit_price;
                            $itemQuantities[] = $item->quantity;
                        }
                    }
                }

                if (!empty($itemPrices)) {
                    $analysis['item_analysis'] = [
                        'item_name' => $itemName,
                        'average_price' => array_sum($itemPrices) / count($itemPrices),
                        'median_price' => $this->calculateMedian($itemPrices),
                        'min_price' => min($itemPrices),
                        'max_price' => max($itemPrices),
                        'count' => count($itemPrices),
                        'price_std_dev' => $this->calculateStandardDeviation($itemPrices),
                        'average_quantity' => !empty($itemQuantities) ? array_sum($itemQuantities) / count($itemQuantities) : 1,
                    ];
                }
            }

            return $this->success($analysis, 'Historical pricing analysis retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to get historical pricing: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Calculate price trend from historical quotes
     */
    protected function calculatePriceTrend($quotes): string
    {
        if ($quotes->count() < 2) {
            return 'insufficient_data';
        }

        // Split into two time periods
        $midpoint = $quotes->count() / 2;
        $recent = $quotes->take($midpoint)->avg('total');
        $older = $quotes->skip($midpoint)->avg('total');

        if ($recent > $older * 1.05) {
            return 'increasing';
        } elseif ($recent < $older * 0.95) {
            return 'decreasing';
        }

        return 'stable';
    }

    /**
     * Detect seasonal patterns in pricing
     */
    protected function detectSeasonalPatterns($quotes): array
    {
        $monthlyAverages = [];
        
        foreach ($quotes as $quote) {
            $month = $quote->created_at->format('F');
            if (!isset($monthlyAverages[$month])) {
                $monthlyAverages[$month] = [];
            }
            $monthlyAverages[$month][] = $quote->total;
        }

        $patterns = [];
        foreach ($monthlyAverages as $month => $prices) {
            $patterns[$month] = [
                'average' => array_sum($prices) / count($prices),
                'count' => count($prices),
            ];
        }

        return $patterns;
    }

    /**
     * Calculate string similarity (simple Levenshtein-based)
     */
    protected function calculateStringSimilarity(string $str1, string $str2): float
    {
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        
        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }

        $maxLen = max($len1, $len2);
        $distance = levenshtein($str1, $str2);
        
        return 1 - ($distance / $maxLen);
    }

    /**
     * Calculate median
     */
    protected function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = floor(($count - 1) / 2);
        
        if ($count % 2) {
            return $values[$middle];
        }
        
        return ($values[$middle] + $values[$middle + 1]) / 2;
    }

    /**
     * Calculate standard deviation
     */
    protected function calculateStandardDeviation(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $variance = 0.0;
        
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        return sqrt($variance / $count);
    }

    /**
     * Get material recommendations
     * 
     * POST /api/quotes/ai/material-recommendations
     */
    public function getMaterialRecommendations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_type' => 'required|string',
            'budget_tier' => 'nullable|in:budget,standard,premium',
            'materials' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        try {
            $companyId = $this->getCompanyId();
            $projectType = $request->input('project_type');
            $budgetTier = $request->input('budget_tier', 'standard');
            $materials = $request->input('materials', []);

            // Get material recommendations based on project type and budget
            $recommendations = $this->getMaterialRecommendationsForProject(
                $projectType,
                $budgetTier,
                $materials,
                $companyId
            );

            return $this->success([
                'recommendations' => $recommendations,
                'project_type' => $projectType,
                'budget_tier' => $budgetTier,
            ], 'Material recommendations retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to get material recommendations: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get material recommendations for project
     */
    protected function getMaterialRecommendationsForProject(
        string $projectType,
        string $budgetTier,
        array $materials,
        string $companyId
    ): array {
        $recommendations = [];

        $materialDatabase = [
            'kitchen' => [
                'countertops' => [
                    'budget' => ['laminate', 'tile'],
                    'standard' => ['quartz', 'granite'],
                    'premium' => ['marble', 'quartzite', 'custom'],
                ],
                'cabinets' => [
                    'budget' => ['particle board', 'MDF'],
                    'standard' => ['plywood', 'solid wood'],
                    'premium' => ['custom solid wood', 'exotic wood'],
                ],
            ],
            'bathroom' => [
                'fixtures' => [
                    'budget' => ['standard porcelain'],
                    'standard' => ['premium porcelain', 'ceramic'],
                    'premium' => ['designer fixtures', 'luxury brands'],
                ],
                'tiles' => [
                    'budget' => ['ceramic tile'],
                    'standard' => ['porcelain tile'],
                    'premium' => ['natural stone', 'mosaic'],
                ],
            ],
        ];

        if (isset($materialDatabase[$projectType])) {
            foreach ($materialDatabase[$projectType] as $category => $options) {
                if (isset($options[$budgetTier])) {
                    $recommendations[$category] = $options[$budgetTier];
                }
            }
        }

        return $recommendations;
    }

    /**
     * AI Chat interface for quote creation
     * 
     * POST /api/quotes/ai/chat
     */
    public function chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|min:1|max:2000',
            'conversation_history' => 'nullable|array',
            'use_groq' => 'nullable|boolean',
            'use_xe_ai' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        try {
            $companyId = $this->getCompanyId();
            $message = $request->input('message');
            $conversationHistory = $request->input('conversation_history', []);
            $useGroq = $request->input('use_groq', true);
            $useXEAIService = $request->input('use_xe_ai', false);

            $context = [
                'company_id' => $companyId,
                'conversation_history' => $conversationHistory,
                'smart_pricing' => true,
            ];

            $response = null;
            $source = 'rule_based';

            if ($useGroq) {
                try {
                    $groqService = app(\App\Services\GroqAIService::class);
                    $response = $groqService->chatQuoteGeneration($message, $context);
                    if ($response) {
                        $source = 'groq_ai';
                    }
                } catch (\Exception $e) {
                    \Log::warning('GROQ Chat unavailable, using fallback: ' . $e->getMessage());
                }
            }

            if (!$response && $useXEAIService) {
                try {
                    $xeService = app(\App\Services\XEAIService::class);
                    $response = $xeService->chatQuoteGeneration($message, $context);
                    if ($response) {
                        $source = 'xe_ai';
                    }
                } catch (\Exception $e) {
                    \Log::warning('XE AI Chat unavailable, using fallback: ' . $e->getMessage());
                }
            }

            // Fallback to rule-based response
            if (!$response) {
                $response = $this->generateRuleBasedChatResponse($message, $conversationHistory);
                $source = 'rule_based';
            }

            return $this->success([
                'response' => $response['message'],
                'suggestions' => $response['suggestions'] ?? [],
                'source' => $source,
                'needs_clarification' => $response['needs_clarification'] ?? false,
            ], 'AI chat response generated');
        } catch (\Exception $e) {
            return $this->error('Failed to generate chat response: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Generate rule-based chat response
     */
    protected function generateRuleBasedChatResponse(string $message, array $history): array
    {
        $messageLower = strtolower($message);
        
        // Check if user is asking for quote generation
        if (strpos($messageLower, 'quote') !== false || strpos($messageLower, 'estimate') !== false || 
            strpos($messageLower, 'price') !== false || strpos($messageLower, 'cost') !== false) {
            
            // Generate suggestions based on message
            $suggestions = $this->aiQuoteService->generateQuoteSuggestions($message, true, $this->getCompanyId());
            
            return [
                'message' => "I've analyzed your project description and generated quote suggestions. Would you like me to add these items to your quote?",
                'suggestions' => $suggestions,
                'needs_clarification' => false,
            ];
        }
        
        // Default helpful response
        return [
            'message' => "I can help you create a quote! Please describe your project in detail, including:\n\n" .
                        "• Project type (kitchen, bathroom, electrical, etc.)\n" .
                        "• Scope and size\n" .
                        "• Materials or specific requirements\n" .
                        "• Budget preferences\n\n" .
                        "Once you provide the details, I'll generate accurate quote suggestions for you.",
            'suggestions' => [],
            'needs_clarification' => true,
        ];
    }

    /**
     * Optimize quote pricing
     * 
     * POST /api/quotes/ai/optimize-pricing
     */
    public function optimizePricing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.description' => 'required|string',
            'items.*.unit_price' => 'required|numeric',
            'target_margin' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        try {
            $companyId = $this->getCompanyId();
            $items = $request->input('items');
            $targetMargin = $request->input('target_margin', 25.0);

            $optimizedItems = [];
            $totalCost = 0;
            $totalPrice = 0;

            foreach ($items as $item) {
                // Estimate cost (simplified - in real app, use actual cost data)
                $estimatedCost = $item['unit_price'] * 0.7; // Assume 30% margin
                $totalCost += $estimatedCost;
                $totalPrice += $item['unit_price'];
            }

            // Calculate adjustment needed
            $currentMargin = $totalPrice > 0 ? (($totalPrice - $totalCost) / $totalPrice) * 100 : 0;
            $adjustment = ($targetMargin - $currentMargin) / 100;

            foreach ($items as $item) {
                $optimizedPrice = $item['unit_price'] * (1 + $adjustment);
                
                $optimizedItems[] = [
                    'description' => $item['description'],
                    'original_price' => $item['unit_price'],
                    'optimized_price' => round($optimizedPrice, 2),
                    'adjustment' => round($adjustment * 100, 2),
                ];
            }

            return $this->success([
                'items' => $optimizedItems,
                'original_total' => $totalPrice,
                'optimized_total' => array_sum(array_column($optimizedItems, 'optimized_price')),
                'target_margin' => $targetMargin,
                'current_margin' => round($currentMargin, 2),
            ], 'Pricing optimized successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to optimize pricing: ' . $e->getMessage(), null, 500);
        }
    }
}



