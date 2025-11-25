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

            // Get historical quotes for analysis
            $historicalQuotes = \App\Models\Quote::where('company_id', $companyId)
                ->where('status', 'accepted')
                ->where('created_at', '>=', now()->subMonths(6))
                ->with('items')
                ->get();

            $analysis = [
                'total_quotes' => $historicalQuotes->count(),
                'average_total' => $historicalQuotes->avg('total'),
                'average_profit_margin' => $historicalQuotes->avg('profit_margin'),
                'project_type' => $projectType,
            ];

            if ($itemName) {
                // Analyze specific item pricing
                $itemPrices = [];
                foreach ($historicalQuotes as $quote) {
                    foreach ($quote->items as $item) {
                        if (stripos($item->description, $itemName) !== false) {
                            $itemPrices[] = $item->unit_price;
                        }
                    }
                }

                if (!empty($itemPrices)) {
                    $analysis['item_analysis'] = [
                        'item_name' => $itemName,
                        'average_price' => array_sum($itemPrices) / count($itemPrices),
                        'min_price' => min($itemPrices),
                        'max_price' => max($itemPrices),
                        'count' => count($itemPrices),
                    ];
                }
            }

            return $this->success($analysis, 'Historical pricing analysis retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to get historical pricing: ' . $e->getMessage(), null, 500);
        }
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



