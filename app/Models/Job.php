<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Job extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'client_id',
        'quote_id',
        'title',
        'description',
        'status',
        'priority',
        'estimated_duration',
        'actual_duration',
        'estimated_cost',
        'actual_cost',
        'labor_cost',
        'material_cost',
        'profit_margin',
        'assigned_technician',
        'scheduled_date',
        'completed_date',
        'location',
        'materials',
        'photos',
        'notes',
        'signature',
    ];

    protected $casts = [
        'location' => 'array',
        'materials' => 'array',
        'photos' => 'array',
        'estimated_duration' => 'decimal:2',
        'actual_duration' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'material_cost' => 'decimal:2',
        'profit_margin' => 'decimal:2',
        'scheduled_date' => 'datetime',
        'completed_date' => 'datetime',
    ];

    /**
     * Get the company that owns the job.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the client that owns the job.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the quote that created this job.
     */
    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * Get the technician assigned to the job.
     */
    public function technician()
    {
        return $this->belongsTo(User::class, 'assigned_technician');
    }

    /**
     * Get the invoices for the job.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the schedule events for the job.
     */
    public function scheduleEvents()
    {
        return $this->hasMany(ScheduleEvent::class);
    }

    /**
     * Get the activities for the job.
     */
    public function activities()
    {
        return $this->hasMany(JobActivity::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the materials used in this job.
     */
    public function jobMaterials()
    {
        return $this->hasMany(JobMaterial::class);
    }

    /**
     * Calculate material cost from materials array
     */
    public function calculateMaterialCost(): float
    {
        if (!$this->materials || !is_array($this->materials)) {
            return 0;
        }

        $total = 0;
        foreach ($this->materials as $material) {
            $quantity = $material['quantity'] ?? 1;
            $unitPrice = $material['unit_price'] ?? $material['cost'] ?? 0;
            $total += $quantity * $unitPrice;
        }

        return round($total, 2);
    }

    /**
     * Calculate labor cost based on actual duration and hourly rate
     * Default hourly rate: £50/hour (can be configured per technician)
     */
    public function calculateLaborCost(float $hourlyRate = 50.00): float
    {
        if (!$this->actual_duration) {
            return 0;
        }

        return round($this->actual_duration * $hourlyRate, 2);
    }

    /**
     * Calculate total actual cost (materials + labor)
     */
    public function calculateActualCost(float $hourlyRate = 50.00): float
    {
        $materialCost = $this->calculateMaterialCost();
        $laborCost = $this->calculateLaborCost($hourlyRate);
        
        return round($materialCost + $laborCost, 2);
    }

    /**
     * Calculate profit margin if quote exists
     */
    public function calculateProfitMargin(): ?float
    {
        if (!$this->quote) {
            return null;
        }

        $quoteTotal = $this->quote->total ?? 0;
        $actualCost = $this->actual_cost ?? $this->calculateActualCost();
        
        if ($quoteTotal <= 0) {
            return null;
        }

        $profit = $quoteTotal - $actualCost;
        $margin = ($profit / $quoteTotal) * 100;
        
        return round($margin, 2);
    }
}

