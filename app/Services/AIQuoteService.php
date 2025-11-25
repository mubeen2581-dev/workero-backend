<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AIQuoteService
{
    protected $xeAIService;
    protected $groqAIService;

    public function __construct(XEAIService $xeAIService, GroqAIService $groqAIService)
    {
        $this->xeAIService = $xeAIService;
        $this->groqAIService = $groqAIService;
    }

    /**
     * Generate AI suggestions for quote items based on project description
     */
    public function generateQuoteSuggestions(string $description, bool $smartPricing = true, ?string $companyId = null): array
    {
        try {
            // Step 1: Process natural language description
            $processedDescription = $this->processNaturalLanguage($description);
            
            // Step 2: Extract project requirements
            $requirements = $this->extractRequirements($processedDescription);
            
            // Step 3: Generate intelligent line item suggestions
            $suggestions = $this->generateLineItemSuggestions($requirements, $smartPricing, $companyId);
            
            // Step 4: Apply historical pricing analysis if available
            if ($companyId) {
                $suggestions = $this->applyHistoricalPricing($suggestions, $requirements, $companyId);
            }
            
            // Step 5: Optimize pricing
            $suggestions = $this->optimizePricing($suggestions, $smartPricing);
            
            // Step 6: Add material recommendations
            $suggestions = $this->addMaterialRecommendations($suggestions, $requirements);
            
            return $suggestions;
        } catch (\Exception $e) {
            Log::error('AI Quote Generation Error: ' . $e->getMessage());
            // Fallback to rule-based suggestions
            return $this->generateFallbackSuggestions($description, $smartPricing);
        }
    }

    /**
     * Process natural language description using NLP
     */
    protected function processNaturalLanguage(string $description): array
    {
        $description = strtolower(trim($description));
        
        // Extract keywords and context
        $keywords = $this->extractKeywords($description);
        $projectType = $this->identifyProjectType($description);
        $scope = $this->identifyScope($description);
        $materials = $this->extractMaterials($description);
        $budget = $this->extractBudgetIndicators($description);
        
        return [
            'keywords' => $keywords,
            'project_type' => $projectType,
            'scope' => $scope,
            'materials' => $materials,
            'budget_indicator' => $budget,
            'original_description' => $description,
        ];
    }

    /**
     * Extract keywords from description
     */
    protected function extractKeywords(string $description): array
    {
        // Common construction keywords
        $constructionKeywords = [
            'kitchen', 'bathroom', 'electrical', 'plumbing', 'hvac', 'roofing',
            'flooring', 'painting', 'renovation', 'remodel', 'installation',
            'repair', 'maintenance', 'upgrade', 'replace', 'install', 'remove'
        ];
        
        $foundKeywords = [];
        foreach ($constructionKeywords as $keyword) {
            if (strpos($description, $keyword) !== false) {
                $foundKeywords[] = $keyword;
            }
        }
        
        return $foundKeywords;
    }

    /**
     * Identify project type
     */
    protected function identifyProjectType(string $description): string
    {
        $types = [
            'kitchen' => ['kitchen', 'cabinet', 'countertop', 'appliance'],
            'bathroom' => ['bathroom', 'toilet', 'shower', 'bathtub', 'sink'],
            'electrical' => ['electrical', 'wiring', 'outlet', 'panel', 'circuit'],
            'plumbing' => ['plumbing', 'pipe', 'fixture', 'drain', 'water'],
            'hvac' => ['hvac', 'heating', 'cooling', 'air conditioning', 'furnace'],
            'roofing' => ['roof', 'roofing', 'shingle', 'gutter'],
            'general' => ['renovation', 'remodel', 'construction', 'work'],
        ];
        
        foreach ($types as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($description, $keyword) !== false) {
                    return $type;
                }
            }
        }
        
        return 'general';
    }

    /**
     * Identify project scope (small, medium, large)
     */
    protected function identifyScope(string $description): string
    {
        $largeIndicators = ['complete', 'full', 'entire', 'whole', 'extensive', 'major'];
        $smallIndicators = ['small', 'minor', 'quick', 'simple', 'basic'];
        
        foreach ($largeIndicators as $indicator) {
            if (strpos($description, $indicator) !== false) {
                return 'large';
            }
        }
        
        foreach ($smallIndicators as $indicator) {
            if (strpos($description, $indicator) !== false) {
                return 'small';
            }
        }
        
        return 'medium';
    }

    /**
     * Extract materials mentioned
     */
    protected function extractMaterials(string $description): array
    {
        $materials = [
            'granite', 'marble', 'quartz', 'tile', 'wood', 'vinyl', 'laminate',
            'stainless steel', 'copper', 'brass', 'ceramic', 'porcelain',
            'concrete', 'brick', 'stone', 'glass', 'aluminum'
        ];
        
        $foundMaterials = [];
        foreach ($materials as $material) {
            if (strpos($description, $material) !== false) {
                $foundMaterials[] = $material;
            }
        }
        
        return $foundMaterials;
    }

    /**
     * Extract budget indicators
     */
    protected function extractBudgetIndicators(string $description): string
    {
        $premiumIndicators = ['premium', 'luxury', 'high-end', 'quality', 'custom', 'designer'];
        $budgetIndicators = ['budget', 'affordable', 'economical', 'cost-effective', 'cheap'];
        
        foreach ($premiumIndicators as $indicator) {
            if (strpos($description, $indicator) !== false) {
                return 'premium';
            }
        }
        
        foreach ($budgetIndicators as $indicator) {
            if (strpos($description, $indicator) !== false) {
                return 'budget';
            }
        }
        
        return 'standard';
    }

    /**
     * Extract requirements from processed description
     */
    protected function extractRequirements(array $processed): array
    {
        return [
            'project_type' => $processed['project_type'],
            'scope' => $processed['scope'],
            'materials' => $processed['materials'],
            'budget_tier' => $processed['budget_indicator'],
            'keywords' => $processed['keywords'],
        ];
    }

    /**
     * Generate intelligent line item suggestions
     */
    protected function generateLineItemSuggestions(array $requirements, bool $smartPricing, ?string $companyId): array
    {
        $suggestions = [];
        $projectType = $requirements['project_type'];
        $scope = $requirements['scope'];
        
        // Get base items for project type
        $baseItems = $this->getBaseItemsForProjectType($projectType, $scope);
        
        // Create suggestion packages
        foreach ($baseItems as $packageName => $items) {
            $suggestion = [
                'id' => uniqid('suggestion-'),
                'title' => $packageName,
                'description' => $this->generatePackageDescription($packageName, $requirements),
                'confidence' => $this->calculateConfidence($requirements, $packageName),
                'items' => [],
                'totalEstimate' => 0,
                'profitMargin' => $this->calculateProfitMargin($requirements),
            ];
            
            foreach ($items as $item) {
                $price = $this->calculateItemPrice($item, $requirements, $smartPricing, $companyId);
                
                $suggestion['items'][] = [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'price' => $price,
                    'reason' => $item['reason'],
                    'category' => $item['category'] ?? 'Labor',
                    'estimatedHours' => $item['hours'] ?? null,
                    'materialCost' => $item['material_cost'] ?? 0,
                    'laborCost' => $item['labor_cost'] ?? ($item['hours'] ?? 0) * 75, // £75/hour
                ];
                
                $suggestion['totalEstimate'] += $price;
            }
            
            $suggestions[] = $suggestion;
        }
        
        return $suggestions;
    }

    /**
     * Get base items for project type
     */
    protected function getBaseItemsForProjectType(string $projectType, string $scope): array
    {
        $items = [
            'kitchen' => [
                'Complete Kitchen Package' => [
                    [
                        'name' => 'Custom Cabinet Installation',
                        'description' => 'Custom-built cabinets with soft-close hinges',
                        'hours' => 24,
                        'material_cost' => 2500,
                        'labor_cost' => 1800,
                        'reason' => 'Based on average kitchen size and quality materials',
                        'category' => 'Labor',
                    ],
                    [
                        'name' => 'Granite Countertops',
                        'description' => 'Premium granite with edge finishing',
                        'hours' => 8,
                        'material_cost' => 2000,
                        'labor_cost' => 800,
                        'reason' => 'Standard kitchen countertop area',
                        'category' => 'Materials',
                    ],
                    [
                        'name' => 'Stainless Steel Appliances',
                        'description' => 'Complete appliance package',
                        'hours' => 4,
                        'material_cost' => 3000,
                        'labor_cost' => 200,
                        'reason' => 'Mid-range appliance package',
                        'category' => 'Materials',
                    ],
                    [
                        'name' => 'Electrical Upgrades',
                        'description' => 'New outlets, lighting, and GFCI protection',
                        'hours' => 6,
                        'material_cost' => 200,
                        'labor_cost' => 450,
                        'reason' => 'Standard kitchen electrical requirements',
                        'category' => 'Labor',
                    ],
                ],
            ],
            'bathroom' => [
                'Bathroom Remodel Package' => [
                    [
                        'name' => 'Bathroom Fixtures',
                        'description' => 'Toilet, sink, bathtub/shower combo',
                        'hours' => 8,
                        'material_cost' => 1200,
                        'labor_cost' => 600,
                        'reason' => 'Quality fixtures for standard bathroom',
                        'category' => 'Materials',
                    ],
                    [
                        'name' => 'Tile Installation',
                        'description' => 'Floor and wall tiles with waterproofing',
                        'hours' => 16,
                        'material_cost' => 800,
                        'labor_cost' => 1200,
                        'reason' => 'Standard bathroom tile coverage',
                        'category' => 'Labor',
                    ],
                ],
            ],
            'electrical' => [
                'Electrical Upgrade Package' => [
                    [
                        'name' => 'Panel Upgrade',
                        'description' => 'Electrical panel replacement and upgrade',
                        'hours' => 8,
                        'material_cost' => 500,
                        'labor_cost' => 600,
                        'reason' => 'Standard panel upgrade',
                        'category' => 'Labor',
                    ],
                    [
                        'name' => 'Outlet Installation',
                        'description' => 'New outlets and GFCI protection',
                        'hours' => 4,
                        'material_cost' => 150,
                        'labor_cost' => 300,
                        'reason' => 'Standard outlet installation',
                        'category' => 'Labor',
                    ],
                ],
            ],
        ];
        
        // Default to general if project type not found
        if (!isset($items[$projectType])) {
            return [
                'Project Estimate' => [
                    [
                        'name' => 'Labor - General',
                        'description' => 'Professional installation and work',
                        'hours' => 16,
                        'material_cost' => 0,
                        'labor_cost' => 1200,
                        'reason' => 'Estimated based on project scope',
                        'category' => 'Labor',
                    ],
                    [
                        'name' => 'Materials',
                        'description' => 'Required materials for project',
                        'hours' => 0,
                        'material_cost' => 800,
                        'labor_cost' => 0,
                        'reason' => 'Standard material costs',
                        'category' => 'Materials',
                    ],
                ],
            ];
        }
        
        // Adjust for scope
        $baseItems = $items[$projectType];
        if ($scope === 'small') {
            // Reduce quantities/hours
            foreach ($baseItems as $packageName => &$packageItems) {
                foreach ($packageItems as &$item) {
                    if (isset($item['hours'])) {
                        $item['hours'] = (int)($item['hours'] * 0.7);
                    }
                    if (isset($item['material_cost'])) {
                        $item['material_cost'] = (int)($item['material_cost'] * 0.8);
                    }
                }
            }
        } elseif ($scope === 'large') {
            // Increase quantities/hours
            foreach ($baseItems as $packageName => &$packageItems) {
                foreach ($packageItems as &$item) {
                    if (isset($item['hours'])) {
                        $item['hours'] = (int)($item['hours'] * 1.3);
                    }
                    if (isset($item['material_cost'])) {
                        $item['material_cost'] = (int)($item['material_cost'] * 1.2);
                    }
                }
            }
        }
        
        return $baseItems;
    }

    /**
     * Calculate item price with smart pricing
     */
    protected function calculateItemPrice(array $item, array $requirements, bool $smartPricing, ?string $companyId): float
    {
        $basePrice = ($item['material_cost'] ?? 0) + ($item['labor_cost'] ?? 0);
        
        if (!$smartPricing) {
            return $basePrice;
        }
        
        // Apply budget tier adjustments
        $budgetTier = $requirements['budget_tier'];
        $multiplier = 1.0;
        
        if ($budgetTier === 'premium') {
            $multiplier = 1.15; // 15% premium
        } elseif ($budgetTier === 'budget') {
            $multiplier = 0.90; // 10% discount
        }
        
        // Apply historical pricing if available
        if ($companyId) {
            $historicalMultiplier = $this->getHistoricalPricingMultiplier($item, $companyId);
            $multiplier *= $historicalMultiplier;
        }
        
        return round($basePrice * $multiplier, 2);
    }

    /**
     * Get historical pricing multiplier based on past quotes
     */
    protected function getHistoricalPricingMultiplier(array $item, string $companyId): float
    {
        // Query historical quotes for similar items
        $similarItems = QuoteItem::whereHas('quote', function ($query) use ($companyId) {
            $query->where('company_id', $companyId)
                  ->where('status', 'accepted')
                  ->where('created_at', '>=', now()->subMonths(6));
        })
        ->where('description', 'like', '%' . $item['name'] . '%')
        ->get();
        
        if ($similarItems->isEmpty()) {
            return 1.0; // No historical data
        }
        
        // Calculate average price
        $avgPrice = $similarItems->avg('unit_price');
        $currentPrice = ($item['material_cost'] ?? 0) + ($item['labor_cost'] ?? 0);
        
        if ($currentPrice == 0) {
            return 1.0;
        }
        
        // Return multiplier to adjust current price towards historical average
        return min(max($avgPrice / $currentPrice, 0.9), 1.1); // Clamp between 0.9 and 1.1
    }

    /**
     * Apply historical pricing analysis
     */
    protected function applyHistoricalPricing(array $suggestions, array $requirements, string $companyId): array
    {
        foreach ($suggestions as &$suggestion) {
            foreach ($suggestion['items'] as &$item) {
                $historicalMultiplier = $this->getHistoricalPricingMultiplier($item, $companyId);
                $item['price'] = round($item['price'] * $historicalMultiplier, 2);
            }
            // Recalculate total
            $suggestion['totalEstimate'] = array_sum(array_column($suggestion['items'], 'price'));
        }
        
        return $suggestions;
    }

    /**
     * Optimize pricing based on smart pricing settings
     */
    protected function optimizePricing(array $suggestions, bool $smartPricing): array
    {
        if (!$smartPricing) {
            return $suggestions;
        }
        
        // Apply profit margin optimization
        foreach ($suggestions as &$suggestion) {
            $targetMargin = $suggestion['profitMargin'];
            $currentTotal = $suggestion['totalEstimate'];
            $costTotal = array_sum(array_map(function ($item) {
                return ($item['materialCost'] ?? 0) + ($item['laborCost'] ?? 0);
            }, $suggestion['items']));
            
            if ($costTotal > 0) {
                $currentMargin = (($currentTotal - $costTotal) / $currentTotal) * 100;
                
                if ($currentMargin < $targetMargin) {
                    // Adjust prices to meet target margin
                    $adjustment = ($targetMargin - $currentMargin) / 100;
                    foreach ($suggestion['items'] as &$item) {
                        $item['price'] = round($item['price'] * (1 + $adjustment), 2);
                    }
                    $suggestion['totalEstimate'] = array_sum(array_column($suggestion['items'], 'price'));
                }
            }
        }
        
        return $suggestions;
    }

    /**
     * Add material recommendations
     */
    protected function addMaterialRecommendations(array $suggestions, array $requirements): array
    {
        $materials = $requirements['materials'] ?? [];
        
        foreach ($suggestions as &$suggestion) {
            foreach ($suggestion['items'] as &$item) {
                if (in_array($item['category'], ['Materials', 'Materials & Equipment'])) {
                    // Add material options if materials were mentioned
                    if (!empty($materials)) {
                        $item['materialOptions'] = $this->generateMaterialOptions($item, $materials);
                    }
                }
            }
        }
        
        return $suggestions;
    }

    /**
     * Generate material options
     */
    protected function generateMaterialOptions(array $item, array $materials): array
    {
        $options = [];
        $basePrice = $item['price'] ?? 0;
        
        foreach ($materials as $material) {
            $options[] = [
                'id' => uniqid('material-'),
                'name' => ucfirst($material) . ' Option',
                'description' => 'Premium ' . $material . ' material',
                'price' => round($basePrice * 1.2, 2), // 20% premium
                'priceDifference' => round($basePrice * 0.2, 2),
            ];
        }
        
        return $options;
    }

    /**
     * Generate package description
     */
    protected function generatePackageDescription(string $packageName, array $requirements): string
    {
        $scope = $requirements['scope'];
        $projectType = $requirements['project_type'];
        
        $descriptions = [
            'small' => "A streamlined {$projectType} solution perfect for budget-conscious projects.",
            'medium' => "A comprehensive {$projectType} package with quality materials and professional installation.",
            'large' => "A complete {$projectType} renovation with premium materials and full-service installation.",
        ];
        
        return $descriptions[$scope] ?? $descriptions['medium'];
    }

    /**
     * Calculate confidence score
     */
    protected function calculateConfidence(array $requirements, string $packageName): int
    {
        $confidence = 75; // Base confidence
        
        // Increase confidence if we have specific project type
        if ($requirements['project_type'] !== 'general') {
            $confidence += 10;
        }
        
        // Increase if we have materials mentioned
        if (!empty($requirements['materials'])) {
            $confidence += 10;
        }
        
        // Increase if we have keywords
        if (!empty($requirements['keywords'])) {
            $confidence += 5;
        }
        
        return min($confidence, 95); // Cap at 95%
    }

    /**
     * Calculate profit margin
     */
    protected function calculateProfitMargin(array $requirements): float
    {
        $budgetTier = $requirements['budget_tier'] ?? 'standard';
        
        $margins = [
            'premium' => 30.0,
            'standard' => 25.0,
            'budget' => 20.0,
        ];
        
        return $margins[$budgetTier] ?? 25.0;
    }

    /**
     * Fallback suggestions if AI fails
     */
    protected function generateFallbackSuggestions(string $description, bool $smartPricing): array
    {
        return [
            [
                'id' => uniqid('fallback-'),
                'title' => 'Project Estimate',
                'description' => 'Based on your description, here are recommended items',
                'confidence' => 60,
                'items' => [
                    [
                        'name' => 'Labor - General',
                        'description' => 'Professional installation and work',
                        'price' => $smartPricing ? 1200 : 1500,
                        'reason' => 'Estimated based on project scope',
                        'category' => 'Labor',
                        'estimatedHours' => 16,
                        'materialCost' => 0,
                        'laborCost' => 1200,
                    ],
                    [
                        'name' => 'Materials',
                        'description' => 'Required materials for project',
                        'price' => $smartPricing ? 800 : 1000,
                        'reason' => 'Standard material costs',
                        'category' => 'Materials',
                        'estimatedHours' => 0,
                        'materialCost' => 800,
                        'laborCost' => 0,
                    ],
                ],
                'totalEstimate' => $smartPricing ? 2000 : 2500,
                'profitMargin' => 20.0,
            ],
        ];
    }

    /**
     * Integrate with GROQ AI (primary AI service)
     */
    public function generateWithGroqAI(string $description, array $context = []): array
    {
        try {
            // Attempt to use GROQ AI
            $aiResponse = $this->groqAIService->generateQuoteSuggestions($description, $context);
            
            if ($aiResponse && !empty($aiResponse)) {
                // Apply historical pricing if available
                if (isset($context['company_id']) && $context['company_id']) {
                    $requirements = $this->extractRequirements($this->processNaturalLanguage($description));
                    $aiResponse = $this->applyHistoricalPricing($aiResponse, $requirements, $context['company_id']);
                }
                
                // Optimize pricing
                $smartPricing = $context['smart_pricing'] ?? true;
                $aiResponse = $this->optimizePricing($aiResponse, $smartPricing);
                
                return $aiResponse;
            }
        } catch (\Exception $e) {
            Log::warning('GROQ AI Service unavailable, using fallback: ' . $e->getMessage());
        }
        
        // Fallback to rule-based
        return $this->generateQuoteSuggestions($description, $context['smart_pricing'] ?? true, $context['company_id'] ?? null);
    }

    /**
     * Integrate with XE AI Workspace (if available)
     */
    public function generateWithXEAIService(string $description, array $context = []): array
    {
        try {
            // Attempt to use XE AI Workspace
            $aiResponse = $this->xeAIService->generateQuoteSuggestions($description, $context);
            
            if ($aiResponse && isset($aiResponse['suggestions'])) {
                return $this->formatXEAISuggestions($aiResponse['suggestions']);
            }
        } catch (\Exception $e) {
            Log::warning('XE AI Service unavailable, using fallback: ' . $e->getMessage());
        }
        
        // Fallback to rule-based
        return $this->generateQuoteSuggestions($description, true);
    }

    /**
     * Format XE AI suggestions to our format
     */
    protected function formatXEAISuggestions(array $xeSuggestions): array
    {
        // Transform XE AI format to our internal format
        $formatted = [];
        
        foreach ($xeSuggestions as $suggestion) {
            $formatted[] = [
                'id' => $suggestion['id'] ?? uniqid('xe-'),
                'title' => $suggestion['title'] ?? 'AI Suggestion',
                'description' => $suggestion['description'] ?? '',
                'confidence' => $suggestion['confidence'] ?? 75,
                'items' => $this->formatXEAItems($suggestion['items'] ?? []),
                'totalEstimate' => $suggestion['total'] ?? 0,
                'profitMargin' => $suggestion['margin'] ?? 25.0,
            ];
        }
        
        return $formatted;
    }

    /**
     * Format XE AI items
     */
    protected function formatXEAItems(array $items): array
    {
        $formatted = [];
        
        foreach ($items as $item) {
            $formatted[] = [
                'name' => $item['name'] ?? '',
                'description' => $item['description'] ?? '',
                'price' => $item['price'] ?? 0,
                'reason' => $item['reason'] ?? 'AI recommended',
                'category' => $item['category'] ?? 'Labor',
                'estimatedHours' => $item['hours'] ?? null,
                'materialCost' => $item['material_cost'] ?? 0,
                'laborCost' => $item['labor_cost'] ?? 0,
            ];
        }
        
        return $formatted;
    }
}



