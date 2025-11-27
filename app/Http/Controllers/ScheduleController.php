<?php

namespace App\Http\Controllers;

use App\Models\RecurringSchedule;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\SchedulingService;
use App\Services\TravelTimeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ScheduleController extends Controller
{
    public function __construct(
        private SchedulingService $schedulingService,
        private TravelTimeService $travelTimeService
    )
    {
    }

    public function events(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $query = ScheduleEvent::where('company_id', $companyId)
            ->with('job', 'technician', 'recurringSchedule');

        if ($request->has('start') && $request->has('end')) {
            $query->whereBetween('start', [
                $request->input('start'),
                $request->input('end'),
            ]);
        }

        if ($request->has('technician_id')) {
            $query->where('technician_id', $request->input('technician_id'));
        }

        if ($request->filled('status')) {
            $query->whereIn('status', (array) $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->whereIn('type', (array) $request->input('type'));
        }

        $events = $query->get();

        return $this->success($events->toArray());
    }

    public function availability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'technician_id' => 'required|uuid|exists:users,id',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'duration_minutes' => 'sometimes|integer|min:15|max:480',
            'buffer_minutes' => 'sometimes|integer|min:0|max:180',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $companyId = $this->getCompanyId();
        $technician = User::where('company_id', $companyId)
            ->where('id', $request->input('technician_id'))
            ->first();

        if (!$technician) {
            return $this->error('Technician not found for this company', null, 404);
        }

        $start = Carbon::parse($request->input('start'))->setTimezone(config('app.timezone'));
        $end = Carbon::parse($request->input('end'))->setTimezone(config('app.timezone'));

        $slots = $this->schedulingService->getAvailableSlots(
            $companyId,
            $technician->id,
            $start,
            $end,
            (int) $request->input('duration_minutes', 60),
            (int) $request->input('buffer_minutes', 15)
        );

        return $this->success([
            'technician_id' => $technician->id,
            'technician_name' => trim("{$technician->first_name} {$technician->last_name}"),
            'window' => [
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
            ],
            'slots' => $slots,
            'total_slots' => count($slots),
        ]);
    }

    public function conflicts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'technician_ids' => 'sometimes|array|min:1',
            'technician_ids.*' => 'uuid|exists:users,id',
            'technician_id' => 'required_without:technician_ids|uuid|exists:users,id',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $companyId = $this->getCompanyId();
        $requestedTechnicians = $request->filled('technician_ids')
            ? $request->input('technician_ids')
            : [$request->input('technician_id')];

        $technicianIds = User::where('company_id', $companyId)
            ->whereIn('id', $requestedTechnicians)
            ->pluck('id')
            ->all();

        if (empty($technicianIds)) {
            return $this->error('Technicians not found for this company', null, 404);
        }

        $start = Carbon::parse($request->input('start'))->setTimezone(config('app.timezone'));
        $end = Carbon::parse($request->input('end'))->setTimezone(config('app.timezone'));

        $result = $this->schedulingService->detectConflicts(
            $companyId,
            $technicianIds,
            $start,
            $end
        );

        return $this->success($result);
    }

    public function travelTime(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'origin' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'mode' => 'sometimes|in:driving,bicycling,walking,transit',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        try {
            $result = $this->travelTimeService->calculate(
                $request->input('origin'),
                $request->input('destination'),
                $request->input('mode', 'driving')
            );

            return $this->success($result);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), null, 400);
        }
    }

    public function optimizeRoute(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'locations' => 'required|array|min:2',
            'locations.*' => 'required|string|max:255',
            'start_location' => 'nullable|string|max:255',
            'mode' => 'sometimes|in:driving,bicycling,walking,transit',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        try {
            $locations = $request->input('locations');
            $startLocation = $request->input('start_location');
            $mode = $request->input('mode', 'driving');

            $optimizedRoute = $this->travelTimeService->optimizeRoute($locations, $startLocation, $mode);
            $routeDetails = $this->travelTimeService->getRouteWithWaypoints($optimizedRoute, $mode);

            return $this->success($routeDetails);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), null, 400);
        }
    }

    public function workloadBalance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'technician_ids' => 'sometimes|array|min:1',
            'technician_ids.*' => 'uuid|exists:users,id',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $companyId = $this->getCompanyId();
        $technicianIds = $request->input('technician_ids');

        if (empty($technicianIds)) {
            // Get all technicians for the company
            $technicianIds = \App\Models\User::where('company_id', $companyId)
                ->where('role', 'technician')
                ->pluck('id')
                ->toArray();
        }

        $start = Carbon::parse($request->input('start'))->setTimezone(config('app.timezone'));
        $end = Carbon::parse($request->input('end'))->setTimezone(config('app.timezone'));

        $result = $this->schedulingService->balanceWorkload($companyId, $technicianIds, $start, $end);

        return $this->success($result);
    }

    public function autoAssign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'job_id' => 'required|uuid|exists:jobs,id',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'technician_ids' => 'sometimes|array',
            'technician_ids.*' => 'uuid|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $companyId = $this->getCompanyId();
        $jobId = $request->input('job_id');
        $start = Carbon::parse($request->input('start'))->setTimezone(config('app.timezone'));
        $end = Carbon::parse($request->input('end'))->setTimezone(config('app.timezone'));
        $preferredTechnicianIds = $request->input('technician_ids', []);

        $assignedTechnicianId = $this->schedulingService->autoAssignJob(
            $companyId,
            $jobId,
            $start,
            $end,
            $preferredTechnicianIds
        );

        if (!$assignedTechnicianId) {
            return $this->error('No available technician found for the specified time slot', null, 404);
        }

        return $this->success([
            'technician_id' => $assignedTechnicianId,
            'technician_name' => \App\Models\User::find($assignedTechnicianId)->full_name ?? 'Unknown',
        ], 'Job auto-assigned successfully');
    }

    public function store(Request $request)
    {
        $companyId = $this->getCompanyId();

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled',
            'type' => 'sometimes|in:job,break,training,maintenance,meeting',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'technician_id' => 'nullable|uuid|exists:users,id',
            'job_id' => 'nullable|uuid|exists:jobs,id',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'travel_time_minutes' => 'nullable|integer|min:0|max:480',
            'buffer_minutes' => 'nullable|integer|min:0|max:120',
            'flexibility_minutes' => 'nullable|integer|min:0|max:240',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $technicianId = $request->input('technician_id');
        $jobId = $request->input('job_id');

        $technician = null;
        if ($technicianId) {
            $technician = User::where('company_id', $companyId)
                ->where('id', $technicianId)
                ->first();

            if (!$technician) {
                return $this->error('Technician not found for this company', null, 404);
            }
        }

        if ($jobId) {
            $jobExists = \App\Models\Job::where('company_id', $companyId)
                ->where('id', $jobId)
                ->exists();

            if (!$jobExists) {
                return $this->error('Job not found for this company', null, 404);
            }
        }

        $start = Carbon::parse($request->input('start'));
        $end = Carbon::parse($request->input('end'));

        if ($technicianId && !$this->schedulingService->isSlotAvailable($companyId, $technicianId, $start, $end)) {
            return $this->error('Technician is not available for the selected time slot', null, 409);
        }

        $event = ScheduleEvent::create([
            'company_id' => $companyId,
            'job_id' => $jobId,
            'technician_id' => $technicianId,
            'title' => $request->input('title'),
            'start' => $start,
            'end' => $end,
            'status' => $request->input('status', 'scheduled'),
            'type' => $request->input('type', 'job'),
            'priority' => $request->input('priority', 'medium'),
            'description' => $request->input('description'),
            'location' => $request->input('location'),
            'color' => $request->input('color'),
            'travel_time_minutes' => $request->input('travel_time_minutes'),
            'buffer_minutes' => $request->input('buffer_minutes'),
            'flexibility_minutes' => $request->input('flexibility_minutes', 0),
        ]);

        return $this->success(
            $event->load('job', 'technician')->toArray(),
            'Event scheduled successfully',
            201
        );
    }

    public function show(Request $request, string $id)
    {
        $companyId = $this->getCompanyId();
        $event = ScheduleEvent::where('company_id', $companyId)
            ->with('job', 'technician', 'recurringSchedule')
            ->findOrFail($id);

        return $this->success($event->toArray());
    }

    public function update(Request $request, string $id)
    {
        $companyId = $this->getCompanyId();
        $event = ScheduleEvent::where('company_id', $companyId)
            ->with('job', 'technician')
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'start' => 'sometimes|date',
            'end' => 'required_with:start|date|after:start',
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled',
            'type' => 'sometimes|in:job,break,training,maintenance,meeting',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'technician_id' => 'nullable|uuid|exists:users,id',
            'job_id' => 'nullable|uuid|exists:jobs,id',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'travel_time_minutes' => 'nullable|integer|min:0|max:480',
            'buffer_minutes' => 'nullable|integer|min:0|max:120',
            'flexibility_minutes' => 'nullable|integer|min:0|max:240',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $technicianId = $request->input('technician_id', $event->technician_id);
        $jobId = $request->input('job_id', $event->job_id);

        if ($technicianId) {
            $technician = User::where('company_id', $companyId)
                ->where('id', $technicianId)
                ->first();

            if (!$technician) {
                return $this->error('Technician not found for this company', null, 404);
            }
        }

        if ($jobId) {
            $jobExists = \App\Models\Job::where('company_id', $companyId)
                ->where('id', $jobId)
                ->exists();

            if (!$jobExists) {
                return $this->error('Job not found for this company', null, 404);
            }
        }

        $start = $request->filled('start') ? Carbon::parse($request->input('start')) : $event->start;
        $end = $request->filled('end') ? Carbon::parse($request->input('end')) : $event->end;

        if ($technicianId && !$this->schedulingService->isSlotAvailable($companyId, $technicianId, $start, $end, $event->id)) {
            return $this->error('Technician is not available for the selected time slot', null, 409);
        }

        $event->fill([
            'title' => $request->input('title', $event->title),
            'job_id' => $jobId,
            'technician_id' => $technicianId,
            'start' => $start,
            'end' => $end,
            'status' => $request->input('status', $event->status),
            'type' => $request->input('type', $event->type),
            'priority' => $request->input('priority', $event->priority),
            'description' => $request->input('description', $event->description),
            'location' => $request->input('location', $event->location),
            'color' => $request->input('color', $event->color),
            'travel_time_minutes' => $request->input('travel_time_minutes', $event->travel_time_minutes),
            'buffer_minutes' => $request->input('buffer_minutes', $event->buffer_minutes),
            'flexibility_minutes' => $request->input('flexibility_minutes', $event->flexibility_minutes),
        ]);

        $event->save();

        return $this->success(
            $event->fresh()->load('job', 'technician')->toArray(),
            'Event updated successfully'
        );
    }

    public function destroy(string $id)
    {
        $companyId = $this->getCompanyId();
        $event = ScheduleEvent::where('company_id', $companyId)->findOrFail($id);

        if (in_array($event->status, ['in_progress', 'completed'])) {
            return $this->error('Cannot delete events that are in progress or completed', null, 422);
        }

        $event->delete();

        return $this->success(null, 'Event deleted successfully');
    }

    public function recurringIndex(Request $request)
    {
        $companyId = $this->getCompanyId();

        $query = RecurringSchedule::where('company_id', $companyId)
            ->with('job', 'technician');

        if ($request->filled('status')) {
            $query->whereIn('status', (array) $request->input('status'));
        }

        return $this->success($query->get()->toArray());
    }

    public function storeRecurring(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'job_id' => 'nullable|uuid|exists:jobs,id',
            'technician_id' => 'nullable|uuid|exists:users,id',
            'frequency' => 'required|in:daily,weekly,monthly,custom',
            'interval' => 'sometimes|integer|min:1|max:12',
            'weekdays' => 'nullable|array|min:1',
            'weekdays.*' => 'integer|min:0|max:6',
            'month_day' => 'nullable|integer|min:1|max:31',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'timezone' => 'nullable|string',
            'status' => 'sometimes|in:active,paused,completed,cancelled',
            'duration_minutes' => 'nullable|integer|min:15|max:720',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'location' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'custom_dates' => 'nullable|array|min:1',
            'custom_dates.*' => 'date',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $companyId = $this->getCompanyId();
        $frequency = $request->input('frequency');

        if ($frequency === 'weekly' && empty($request->input('weekdays'))) {
            return $this->error('Weekdays are required for weekly schedules', null, 422);
        }

        if ($frequency === 'monthly' && !$request->filled('month_day')) {
            return $this->error('Month day is required for monthly schedules', null, 422);
        }

        if ($frequency === 'custom' && empty($request->input('custom_dates'))) {
            return $this->error('Custom dates are required for custom schedules', null, 422);
        }

        $jobId = $request->input('job_id');
        if ($jobId && !\App\Models\Job::where('company_id', $companyId)->where('id', $jobId)->exists()) {
            return $this->error('Job not found for this company', null, 404);
        }

        $technicianId = $request->input('technician_id');
        if ($technicianId && !User::where('company_id', $companyId)->where('id', $technicianId)->exists()) {
            return $this->error('Technician not found for this company', null, 404);
        }

        $timezone = $request->input('timezone', config('app.timezone'));
        $startDate = Carbon::parse($request->input('start_date'), $timezone)->setTimezone(config('app.timezone'));
        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'), $timezone)->setTimezone(config('app.timezone'))
            : null;

        $constraints = $this->buildRecurringConstraints($request);

        $schedule = RecurringSchedule::create([
            'company_id' => $companyId,
            'job_id' => $jobId,
            'technician_id' => $technicianId,
            'frequency' => $frequency,
            'interval' => $request->input('interval', 1),
            'weekdays' => $request->input('weekdays'),
            'month_day' => $request->input('month_day'),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'timezone' => $timezone,
            'status' => $request->input('status', 'active'),
            'constraints' => $constraints,
        ]);

        $generationWindowEnd = Carbon::now()->addDays(30);
        $events = $this->schedulingService->generateEventsForRecurringSchedule(
            $schedule,
            Carbon::now(),
            $generationWindowEnd
        );

        return $this->success([
            'schedule' => $schedule->fresh()->load('job', 'technician'),
            'generated_events' => count($events),
        ], 'Recurring schedule created', 201);
    }

    public function updateRecurring(Request $request, string $id)
    {
        $companyId = $this->getCompanyId();
        $schedule = RecurringSchedule::where('company_id', $companyId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'job_id' => 'nullable|uuid|exists:jobs,id',
            'technician_id' => 'nullable|uuid|exists:users,id',
            'frequency' => 'sometimes|in:daily,weekly,monthly,custom',
            'interval' => 'sometimes|integer|min:1|max:12',
            'weekdays' => 'nullable|array|min:1',
            'weekdays.*' => 'integer|min:0|max:6',
            'month_day' => 'nullable|integer|min:1|max:31',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'timezone' => 'nullable|string',
            'status' => 'sometimes|in:active,paused,completed,cancelled',
            'duration_minutes' => 'nullable|integer|min:15|max:720',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'location' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'custom_dates' => 'nullable|array|min:1',
            'custom_dates.*' => 'date',
            'regenerate_days' => 'nullable|integer|min:1|max:120',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $data = $request->only([
            'job_id',
            'technician_id',
            'frequency',
            'interval',
            'weekdays',
            'month_day',
            'timezone',
            'status',
        ]);

        if (array_key_exists('job_id', $data) && $data['job_id'] !== null) {
            if (!\App\Models\Job::where('company_id', $companyId)->where('id', $data['job_id'])->exists()) {
                return $this->error('Job not found for this company', null, 404);
            }
        }

        if (array_key_exists('technician_id', $data) && $data['technician_id'] !== null) {
            if (!User::where('company_id', $companyId)->where('id', $data['technician_id'])->exists()) {
                return $this->error('Technician not found for this company', null, 404);
            }
        }

        if ($request->filled('start_date')) {
            $timezone = $request->input('timezone', $schedule->timezone ?? config('app.timezone'));
            $data['start_date'] = Carbon::parse($request->input('start_date'), $timezone)->setTimezone(config('app.timezone'));
        }

        if ($request->filled('end_date')) {
            $timezone = $request->input('timezone', $schedule->timezone ?? config('app.timezone'));
            $data['end_date'] = Carbon::parse($request->input('end_date'), $timezone)->setTimezone(config('app.timezone'));
        }

        $schedule->fill(array_filter($data, fn ($value) => !is_null($value)));

        $currentConstraints = $schedule->constraints ?? [];
        $constraintUpdates = $this->buildRecurringConstraints($request, $currentConstraints);
        $schedule->constraints = array_merge($currentConstraints, $constraintUpdates);

        $schedule->save();

        $generated = 0;
        if ($request->boolean('regenerate', false)) {
            $days = (int) $request->input('regenerate_days', 30);
            ScheduleEvent::where('recurring_schedule_id', $schedule->id)
                ->where('start', '>=', Carbon::now())
                ->delete();

            $generatedEvents = $this->schedulingService->generateEventsForRecurringSchedule(
                $schedule,
                Carbon::now(),
                Carbon::now()->addDays($days)
            );
            $generated = count($generatedEvents);
        }

        return $this->success([
            'schedule' => $schedule->fresh()->load('job', 'technician'),
            'regenerated_events' => $generated,
        ], 'Recurring schedule updated');
    }

    public function destroyRecurring(Request $request, string $id)
    {
        $companyId = $this->getCompanyId();
        $schedule = RecurringSchedule::where('company_id', $companyId)->findOrFail($id);

        if ($request->boolean('delete_events', true)) {
            ScheduleEvent::where('recurring_schedule_id', $schedule->id)->delete();
        } else {
            ScheduleEvent::where('recurring_schedule_id', $schedule->id)
                ->update(['recurring_schedule_id' => null]);
        }

        $schedule->delete();

        return $this->success(null, 'Recurring schedule deleted successfully');
    }

    public function generateRecurring(Request $request, string $id)
    {
        $companyId = $this->getCompanyId();
        $schedule = RecurringSchedule::where('company_id', $companyId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'days' => 'nullable|integer|min:1|max:180',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $days = (int) $request->input('days', 30);
        $generatedEvents = $this->schedulingService->generateEventsForRecurringSchedule(
            $schedule,
            Carbon::now(),
            Carbon::now()->addDays($days)
        );

        return $this->success([
            'generated_events' => count($generatedEvents),
            'next_occurrence' => $schedule->fresh()->next_occurrence,
        ], 'Upcoming events generated');
    }

    public function exportIcal(Request $request)
    {
        $companyId = $this->getCompanyId();

        $query = ScheduleEvent::where('company_id', $companyId)->with('technician', 'job.client');

        if ($request->filled('start') && $request->filled('end')) {
            $query->whereBetween('start', [
                $request->input('start'),
                $request->input('end'),
            ]);
        }

        if ($request->filled('technician_id')) {
            $query->where('technician_id', $request->input('technician_id'));
        }

        $events = $query->orderBy('start')->get();

        $ical = $this->buildICalFeed($events);

        return response($ical, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="workero-schedule.ics"',
        ]);
    }

    private function buildICalFeed($events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Workero//Scheduling//EN',
            'CALSCALE:GREGORIAN',
        ];

        foreach ($events as $event) {
            $start = Carbon::parse($event->start)->utc()->format('Ymd\THis\Z');
            $end = Carbon::parse($event->end)->utc()->format('Ymd\THis\Z');
            $uid = "{$event->id}@workero";
            $summary = $this->escapeIcalText($event->title ?? 'Scheduled Event');

            $descriptionParts = [];
            if ($event->job?->description) {
                $descriptionParts[] = $event->job->description;
            }
            if ($event->notes ?? false) {
                $descriptionParts[] = $event->notes;
            }
            $description = $this->escapeIcalText(implode("\n", $descriptionParts));
            $location = $this->escapeIcalText($event->location ?? ($event->job->client->address['street'] ?? ''));

            $lines = array_merge($lines, [
                'BEGIN:VEVENT',
                "UID:{$uid}",
                "DTSTAMP:" . now()->utc()->format('Ymd\THis\Z'),
                "DTSTART:{$start}",
                "DTEND:{$end}",
                "SUMMARY:{$summary}",
                "DESCRIPTION:{$description}",
                "LOCATION:{$location}",
                "STATUS:" . strtoupper($event->status ?? 'confirmed'),
                'END:VEVENT',
            ]);
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines);
    }

    private function escapeIcalText(string $text): string
    {
        return addcslashes($text, ",;\\");
    }

    protected function buildRecurringConstraints(Request $request, array $existing = []): array
    {
        $constraints = array_filter([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'priority' => $request->input('priority'),
            'location' => $request->input('location'),
            'color' => $request->input('color'),
        ], fn ($value) => !is_null($value));

        if ($request->has('duration_minutes')) {
            $constraints['duration_minutes'] = (int) $request->input('duration_minutes');
        } elseif (empty($existing)) {
            $constraints['duration_minutes'] = 60;
        }

        if ($request->filled('custom_dates')) {
            $constraints['custom_dates'] = $request->input('custom_dates');
        }

        return $constraints;
    }
}

