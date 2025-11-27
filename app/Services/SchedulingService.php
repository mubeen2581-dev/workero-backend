<?php

namespace App\Services;

use App\Models\ScheduleEvent;
use App\Models\RecurringSchedule;
use App\Models\TechnicianAvailability;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class SchedulingService
{
    /**
     * Calculate available slots for a technician between two dates.
     */
    public function getAvailableSlots(
        string $companyId,
        string $technicianId,
        Carbon $windowStart,
        Carbon $windowEnd,
        int $durationMinutes = 60,
        int $bufferMinutes = 15
    ): array {
        $availabilities = $this->getAvailabilityRules($companyId, $technicianId, $windowStart, $windowEnd);
        $events = $this->getEventsWithinWindow($companyId, $technicianId, $windowStart, $windowEnd);

        $slots = [];

        $period = CarbonPeriod::create($windowStart->copy()->startOfDay(), $windowEnd->copy()->endOfDay());
        foreach ($period as $date) {
            $dayAvailability = $availabilities->where('day_of_week', $date->dayOfWeek)->first();

            if (!$dayAvailability || !$dayAvailability->is_available) {
                continue;
            }

            $slotStart = $this->buildDateTime($date, $dayAvailability->start_time ?? '08:00');
            $availabilityEnd = $this->buildDateTime($date, $dayAvailability->end_time ?? '17:00');

            while ($slotStart->copy()->addMinutes($durationMinutes) <= $availabilityEnd) {
                $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);
                $conflictingEvents = $this->findConflicts($events, $slotStart->copy()->subMinutes($bufferMinutes), $slotEnd->copy()->addMinutes($bufferMinutes));

                if ($conflictingEvents->isEmpty()) {
                    $slots[] = [
                        'start' => $slotStart->toIso8601String(),
                        'end' => $slotEnd->toIso8601String(),
                        'duration_minutes' => $durationMinutes,
                        'day_of_week' => $date->dayOfWeek,
                        'timezone' => $dayAvailability->timezone ?? $windowStart->getTimezone()->getName(),
                    ];
                }

                $slotStart = $slotStart->copy()->addMinutes(max($durationMinutes, $bufferMinutes));
            }
        }

        return $slots;
    }

    /**
     * Detect overlaps and workload issues for one or more technicians.
     */
    public function detectConflicts(string $companyId, array $technicianIds, Carbon $windowStart, Carbon $windowEnd): array
    {
        $events = ScheduleEvent::query()
            ->where('company_id', $companyId)
            ->whereIn('technician_id', $technicianIds)
            ->where(function ($query) use ($windowStart, $windowEnd) {
                $query->whereBetween('start', [$windowStart, $windowEnd])
                    ->orWhereBetween('end', [$windowStart, $windowEnd])
                    ->orWhere(function ($q) use ($windowStart, $windowEnd) {
                        $q->where('start', '<=', $windowStart)
                            ->where('end', '>=', $windowEnd);
                    });
            })
            ->orderBy('technician_id')
            ->orderBy('start')
            ->get();

        $conflicts = [];
        $grouped = $events->groupBy('technician_id');

        foreach ($grouped as $technicianId => $technicianEvents) {
            $previous = null;
            $dailyCounts = [];

            foreach ($technicianEvents as $event) {
                $dayKey = $event->start->toDateString();
                $dailyCounts[$dayKey] = ($dailyCounts[$dayKey] ?? 0) + 1;

                if ($previous && $previous->end->greaterThan($event->start)) {
                    $conflicts[] = [
                        'technician_id' => $technicianId,
                        'type' => 'overlap',
                        'events' => [
                            $this->eventSummary($previous),
                            $this->eventSummary($event),
                        ],
                    ];
                }

                $previous = $event;
            }

            foreach ($dailyCounts as $date => $count) {
                if ($count > 6) {
                    $conflicts[] = [
                        'technician_id' => $technicianId,
                        'type' => 'workload',
                        'date' => $date,
                        'scheduled_jobs' => $count,
                        'message' => 'Technician has more than 6 assignments on this day',
                    ];
                }
            }
        }

        return [
            'conflicts' => $conflicts,
            'window' => [
                'start' => $windowStart->toIso8601String(),
                'end' => $windowEnd->toIso8601String(),
            ],
            'technicians' => $technicianIds,
            'total_events' => $events->count(),
        ];
    }

    /**
     * Determine whether a technician has availability for a specific slot.
     */
    public function isSlotAvailable(
        string $companyId,
        ?string $technicianId,
        Carbon $start,
        Carbon $end,
        ?string $ignoreEventId = null
    ): bool {
        if (!$technicianId) {
            return true;
        }

        return !ScheduleEvent::query()
            ->where('company_id', $companyId)
            ->where('technician_id', $technicianId)
            ->when($ignoreEventId, fn ($q) => $q->where('id', '!=', $ignoreEventId))
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start', [$start, $end])
                    ->orWhereBetween('end', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->where('start', '<', $start)
                            ->where('end', '>', $end);
                    });
            })
            ->exists();
    }

    protected function getAvailabilityRules(string $companyId, string $technicianId, Carbon $windowStart, Carbon $windowEnd): Collection
    {
        $availabilities = TechnicianAvailability::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($technicianId) {
                $query->whereNull('technician_id')
                    ->orWhere('technician_id', $technicianId);
            })
            ->where(function ($query) use ($windowStart, $windowEnd) {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $windowEnd);
            })
            ->where(function ($query) use ($windowStart, $windowEnd) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $windowStart);
            })
            ->orderByRaw('technician_id IS NULL ASC')
            ->get()
            ->keyBy('day_of_week');

        if ($availabilities->isEmpty()) {
            foreach (range(0, 6) as $day) {
                $availabilities->put($day, (object) [
                    'day_of_week' => $day,
                    'is_available' => in_array($day, [0, 6], true) ? false : true,
                    'start_time' => '08:00',
                    'end_time' => '17:00',
                    'timezone' => $windowStart->getTimezone()->getName(),
                ]);
            }
        }

        return $availabilities;
    }

    protected function getEventsWithinWindow(string $companyId, string $technicianId, Carbon $windowStart, Carbon $windowEnd): Collection
    {
        return ScheduleEvent::query()
            ->where('company_id', $companyId)
            ->where('technician_id', $technicianId)
            ->where(function ($query) use ($windowStart, $windowEnd) {
                $query->whereBetween('start', [$windowStart, $windowEnd])
                    ->orWhereBetween('end', [$windowStart, $windowEnd])
                    ->orWhere(function ($q) use ($windowStart, $windowEnd) {
                        $q->where('start', '<=', $windowStart)
                            ->where('end', '>=', $windowEnd);
                    });
            })
            ->get();
    }

    protected function findConflicts(Collection $events, Carbon $slotStart, Carbon $slotEnd): Collection
    {
        return $events->filter(function ($event) use ($slotStart, $slotEnd) {
            return $event->start->lt($slotEnd) && $event->end->gt($slotStart);
        });
    }

    protected function buildDateTime(Carbon $date, string $time): Carbon
    {
        [$hours, $minutes] = explode(':', $time);

        return $date->copy()->setTime((int) $hours, (int) $minutes, 0);
    }

    protected function eventSummary(ScheduleEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'start' => $event->start->toIso8601String(),
            'end' => $event->end->toIso8601String(),
            'job_id' => $event->job_id,
            'status' => $event->status,
        ];
    }

    /**
     * Generate schedule events for a recurring schedule within a given window.
     *
     * @return array<ScheduleEvent>
     */
    public function generateEventsForRecurringSchedule(
        RecurringSchedule $schedule,
        Carbon $windowStart,
        Carbon $windowEnd
    ): array {
        if ($schedule->status !== 'active') {
            return [];
        }

        $timezone = $schedule->timezone ?? config('app.timezone');
        $scheduleStart = $schedule->start_date->copy()->setTimezone($timezone);
        $start = $windowStart->copy()->setTimezone($timezone);
        if ($start->lt($scheduleStart)) {
            $start = $scheduleStart->copy();
        }

        $end = $windowEnd->copy()->setTimezone($timezone);
        if ($schedule->end_date) {
            $scheduleEnd = $schedule->end_date->copy()->setTimezone($timezone);
            if ($scheduleEnd->lt($end)) {
                $end = $scheduleEnd;
            }
        }

        if ($end->lt($start)) {
            return [];
        }

        $createdEvents = [];
        $period = CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->endOfDay());
        foreach ($period as $date) {
            if ($this->matchesRecurringPattern($schedule, $date)) {
                $eventStart = $this->buildOccurrenceStart($schedule, $date);
                if ($this->eventExists($schedule, $eventStart)) {
                    continue;
                }

                $duration = (int) data_get($schedule->constraints, 'duration_minutes', 60);
                $eventEnd = $eventStart->copy()->addMinutes($duration);

                $event = ScheduleEvent::create([
                    'company_id' => $schedule->company_id,
                    'job_id' => $schedule->job_id,
                    'technician_id' => $schedule->technician_id,
                    'recurring_schedule_id' => $schedule->id,
                    'title' => data_get($schedule->constraints, 'title', 'Recurring Job'),
                    'description' => data_get($schedule->constraints, 'description'),
                    'start' => $eventStart,
                    'end' => $eventEnd,
                    'status' => 'scheduled',
                    'type' => 'job',
                    'priority' => data_get($schedule->constraints, 'priority', 'medium'),
                    'location' => data_get($schedule->constraints, 'location'),
                    'color' => data_get($schedule->constraints, 'color'),
                    'metadata' => [
                        'generated_from_recurring' => true,
                    ],
                ]);

                $createdEvents[] = $event;
            }
        }

        $schedule->next_occurrence = $this->calculateNextOccurrence($schedule, $end->copy()->addDay());
        $schedule->save();

        return $createdEvents;
    }

    public function calculateNextOccurrence(RecurringSchedule $schedule, Carbon $fromDate): ?Carbon
    {
        $timezone = $schedule->timezone ?? config('app.timezone');
        $pointer = $fromDate->copy()->setTimezone($timezone)->startOfDay();
        $limit = $pointer->copy()->addMonths(12);

        while ($pointer->lte($limit)) {
            if ($schedule->end_date && $pointer->gt($schedule->end_date->copy()->setTimezone($timezone))) {
                return null;
            }

            if ($pointer->gte($schedule->start_date->copy()->setTimezone($timezone))
                && $this->matchesRecurringPattern($schedule, $pointer)) {
                return $pointer->copy()->setTimezone(config('app.timezone'));
            }

            $pointer->addDay();
        }

        return null;
    }

    protected function matchesRecurringPattern(RecurringSchedule $schedule, Carbon $date): bool
    {
        $interval = max(1, (int) $schedule->interval);
        $startDate = $schedule->start_date->copy()->startOfDay();
        $targetDate = $date->copy()->setTimezone(config('app.timezone'))->startOfDay();

        if ($targetDate->lt($startDate)) {
            return false;
        }

        switch ($schedule->frequency) {
            case 'daily':
                $daysDiff = $startDate->diffInDays($targetDate);
                return $daysDiff % $interval === 0;

            case 'weekly':
                $weekdays = collect($schedule->weekdays ?? [])
                    ->map(fn ($day) => (int) $day);

                if ($weekdays->isNotEmpty() && !$weekdays->contains($targetDate->dayOfWeek)) {
                    return false;
                }

                $weeksDiff = intdiv($startDate->diffInDays($targetDate), 7);
                return $weeksDiff % $interval === 0;

            case 'monthly':
                if ($schedule->month_day && (int) $schedule->month_day !== (int) $targetDate->day) {
                    return false;
                }

                $monthsDiff = $startDate->diffInMonths($targetDate);
                return $monthsDiff % $interval === 0;

            case 'custom':
                $customDates = collect(data_get($schedule->constraints, 'custom_dates', []));
                return $customDates->contains($targetDate->toDateString());

            default:
                return false;
        }
    }

    protected function buildOccurrenceStart(RecurringSchedule $schedule, Carbon $date): Carbon
    {
        $timezone = $schedule->timezone ?? config('app.timezone');
        $baseTime = $schedule->start_date->copy()->setTimezone($timezone);

        $occurrence = $date->copy()->setTimezone($timezone)
            ->setTime($baseTime->hour, $baseTime->minute, $baseTime->second);

        return $occurrence->copy()->setTimezone(config('app.timezone'));
    }

    protected function eventExists(RecurringSchedule $schedule, Carbon $start): bool
    {
        return ScheduleEvent::where('recurring_schedule_id', $schedule->id)
            ->where('start', $start)
            ->exists();
    }

    /**
     * Balance workload across technicians for a given date range
     * Returns recommended assignments based on current workload
     */
    public function balanceWorkload(
        string $companyId,
        array $technicianIds,
        Carbon $windowStart,
        Carbon $windowEnd
    ): array {
        $technicians = \App\Models\User::where('company_id', $companyId)
            ->whereIn('id', $technicianIds)
            ->get();

        $workloads = [];
        $availabilities = [];

        foreach ($technicians as $technician) {
            $events = $this->getEventsWithinWindow($companyId, $technician->id, $windowStart, $windowEnd);
            $totalHours = $events->sum(fn($e) => $e->start->diffInHours($e->end));
            $totalJobs = $events->where('type', 'job')->count();

            $availabilityRules = $this->getAvailabilityRules($companyId, $technician->id, $windowStart, $windowEnd);
            $maxHours = $availabilityRules->sum(fn($a) => $a->max_hours_per_day ?? 8) * $windowStart->diffInDays($windowEnd);

            $workloads[$technician->id] = [
                'technician_id' => $technician->id,
                'technician_name' => trim("{$technician->first_name} {$technician->last_name}"),
                'current_hours' => $totalHours,
                'current_jobs' => $totalJobs,
                'max_hours' => $maxHours,
                'utilization' => $maxHours > 0 ? ($totalHours / $maxHours) * 100 : 0,
                'available_hours' => max(0, $maxHours - $totalHours),
            ];

            $availabilities[$technician->id] = $availabilityRules;
        }

        // Sort by utilization (ascending) - least utilized first
        uasort($workloads, fn($a, $b) => $a['utilization'] <=> $b['utilization']);

        return [
            'technicians' => array_values($workloads),
            'total_technicians' => count($workloads),
            'average_utilization' => count($workloads) > 0
                ? array_sum(array_column($workloads, 'utilization')) / count($workloads)
                : 0,
            'recommendations' => $this->generateWorkloadRecommendations($workloads, $availabilities),
        ];
    }

    /**
     * Generate workload balancing recommendations
     */
    protected function generateWorkloadRecommendations(array $workloads, array $availabilities): array
    {
        $recommendations = [];
        $sorted = $workloads;
        uasort($sorted, fn($a, $b) => $a['utilization'] <=> $b['utilization']);

        $underutilized = array_filter($sorted, fn($w) => $w['utilization'] < 70);
        $overutilized = array_filter($sorted, fn($w) => $w['utilization'] > 90);

        if (!empty($overutilized) && !empty($underutilized)) {
            foreach ($overutilized as $overTech) {
                $bestCandidate = null;
                $maxAvailable = 0;

                foreach ($underutilized as $underTech) {
                    if ($underTech['available_hours'] > $maxAvailable) {
                        $maxAvailable = $underTech['available_hours'];
                        $bestCandidate = $underTech;
                    }
                }

                if ($bestCandidate) {
                    $hoursToTransfer = min(
                        ($overTech['current_hours'] - ($overTech['max_hours'] * 0.9)),
                        $bestCandidate['available_hours']
                    );

                    if ($hoursToTransfer > 0) {
                        $recommendations[] = [
                            'type' => 'redistribute',
                            'from_technician_id' => $overTech['technician_id'],
                            'from_technician_name' => $overTech['technician_name'],
                            'to_technician_id' => $bestCandidate['technician_id'],
                            'to_technician_name' => $bestCandidate['technician_name'],
                            'hours_to_transfer' => round($hoursToTransfer, 1),
                            'reason' => "{$overTech['technician_name']} is over-utilized ({$overTech['utilization']}%), {$bestCandidate['technician_name']} has capacity",
                        ];
                    }
                }
            }
        }

        // Recommend best technician for new jobs
        if (!empty($sorted)) {
            $bestForNewJob = reset($sorted);
            $recommendations[] = [
                'type' => 'assign_new',
                'technician_id' => $bestForNewJob['technician_id'],
                'technician_name' => $bestForNewJob['technician_name'],
                'utilization' => $bestForNewJob['utilization'],
                'available_hours' => $bestForNewJob['available_hours'],
                'reason' => "Best candidate for new assignments (lowest utilization: {$bestForNewJob['utilization']}%)",
            ];
        }

        return $recommendations;
    }

    /**
     * Auto-assign job to best available technician based on workload
     */
    public function autoAssignJob(
        string $companyId,
        string $jobId,
        Carbon $startTime,
        Carbon $endTime,
        array $preferredTechnicianIds = []
    ): ?string {
        $durationHours = $startTime->diffInHours($endTime);

        // Get all technicians or preferred ones
        $query = \App\Models\User::where('company_id', $companyId)
            ->where('role', 'technician')
            ->where('is_active', true);

        if (!empty($preferredTechnicianIds)) {
            $query->whereIn('id', $preferredTechnicianIds);
        }

        $technicians = $query->get();

        if ($technicians->isEmpty()) {
            return null;
        }

        $scores = [];

        foreach ($technicians as $technician) {
            // Check availability
            if (!$this->isSlotAvailable($companyId, $technician->id, $startTime, $endTime)) {
                continue;
            }

            // Calculate workload
            $windowStart = $startTime->copy()->startOfDay();
            $windowEnd = $startTime->copy()->endOfDay();
            $events = $this->getEventsWithinWindow($companyId, $technician->id, $windowStart, $windowEnd);
            $currentHours = $events->sum(fn($e) => $e->start->diffInHours($e->end));

            $availabilityRules = $this->getAvailabilityRules($companyId, $technician->id, $windowStart, $windowEnd);
            $maxHours = $availabilityRules->sum(fn($a) => $a->max_hours_per_day ?? 8);

            $utilization = $maxHours > 0 ? ($currentHours / $maxHours) * 100 : 0;
            $availableHours = max(0, $maxHours - $currentHours);

            // Score: lower utilization and more available hours = better
            $score = (100 - $utilization) + ($availableHours * 10);

            $scores[$technician->id] = [
                'technician_id' => $technician->id,
                'score' => $score,
                'utilization' => $utilization,
                'available_hours' => $availableHours,
            ];
        }

        if (empty($scores)) {
            return null;
        }

        // Sort by score (descending)
        uasort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_key_first($scores);
    }
}