<?php

namespace App\Http\Controllers;

use App\Models\GoogleCalendarConnection;
use App\Models\ScheduleEvent;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CalendarSyncController extends Controller
{
    public function __construct(private GoogleCalendarService $googleCalendarService)
    {
    }

    /**
     * Get OAuth authorization URL
     */
    public function connect(Request $request)
    {
        $companyId = $this->getCompanyId();
        $userId = $request->user()->id;

        $state = base64_encode(json_encode([
            'company_id' => $companyId,
            'user_id' => $userId,
        ]));

        $authUrl = $this->googleCalendarService->getAuthUrl($state);

        return $this->success([
            'auth_url' => $authUrl,
        ], 'Authorization URL generated');
    }

    /**
     * Handle OAuth callback
     */
    public function callback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        if ($validator->fails()) {
            // Redirect to frontend with error
            $frontendUrl = config('app.frontend_url', config('app.url'));
            return redirect($frontendUrl . '/scheduling?calendar_error=' . urlencode('Validation error'));
        }

        try {
            $state = json_decode(base64_decode($request->input('state')), true);
            
            if (!$state || !isset($state['company_id']) || !isset($state['user_id'])) {
                throw new \RuntimeException('Invalid state parameter');
            }

            $companyId = $state['company_id'];
            $userId = $state['user_id'];

            $tokens = $this->googleCalendarService->exchangeCodeForTokens($request->input('code'));
            $userInfo = $this->googleCalendarService->getUserInfo($tokens['access_token']);

            $connection = GoogleCalendarConnection::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'google_email' => $userInfo['email'],
                ],
                [
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'token_expires_at' => Carbon::now()->addSeconds($tokens['expires_in']),
                    'is_active' => true,
                ]
            );

            // Redirect to frontend with success
            $frontendUrl = config('app.frontend_url', config('app.url'));
            return redirect($frontendUrl . '/scheduling?calendar_connected=true');
        } catch (\Exception $e) {
            // Redirect to frontend with error
            $frontendUrl = config('app.frontend_url', config('app.url'));
            return redirect($frontendUrl . '/scheduling?calendar_error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Get connection status
     */
    public function status(Request $request)
    {
        $companyId = $this->getCompanyId();
        $userId = $request->input('user_id', $request->user()->id);

        $connection = GoogleCalendarConnection::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        if (!$connection) {
            return $this->success([
                'connected' => false,
            ], 'No active Google Calendar connection');
        }

        return $this->success([
            'connected' => true,
            'connection' => [
                'id' => $connection->id,
                'google_email' => $connection->google_email,
                'calendar_id' => $connection->calendar_id,
                'last_sync_at' => $connection->last_sync_at,
                'last_sync_status' => $connection->last_sync_status,
                'is_token_valid' => !$connection->isTokenExpired(),
            ],
        ]);
    }

    /**
     * Push Workero events to Google Calendar
     */
    public function syncPush(Request $request)
    {
        $companyId = $this->getCompanyId();
        $userId = $request->input('user_id', $request->user()->id);

        $validator = Validator::make($request->all(), [
            'start' => 'nullable|date',
            'end' => 'nullable|date|after:start',
            'event_ids' => 'nullable|array',
            'event_ids.*' => 'uuid|exists:schedule_events,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $connection = GoogleCalendarConnection::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        if (!$connection) {
            return $this->error('No active Google Calendar connection found', null, 404);
        }

        try {
            $query = ScheduleEvent::where('company_id', $companyId)
                ->with('job', 'technician');

            if ($request->filled('event_ids')) {
                $query->whereIn('id', $request->input('event_ids'));
            } elseif ($request->filled('start') && $request->filled('end')) {
                $query->whereBetween('start', [
                    $request->input('start'),
                    $request->input('end'),
                ]);
            } else {
                // Default: next 30 days
                $query->whereBetween('start', [
                    Carbon::now(),
                    Carbon::now()->addDays(30),
                ]);
            }

            $events = $query->get()->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'location' => $event->location,
                    'start' => $event->start->toIso8601String(),
                    'end' => $event->end->toIso8601String(),
                    'company_id' => $event->company_id,
                    'google_event_id' => $event->metadata['google_event_id'] ?? null,
                ];
            })->toArray();

            $result = $this->googleCalendarService->pushEvents($connection, $events);

            $connection->update([
                'last_sync_at' => Carbon::now(),
                'last_sync_status' => empty($result['errors']) ? 'success' : 'partial',
                'last_sync_error' => !empty($result['errors']) ? json_encode($result['errors']) : null,
            ]);

            return $this->success([
                'created' => $result['created'],
                'updated' => $result['updated'],
                'errors' => $result['errors'],
            ], 'Events synced to Google Calendar');
        } catch (\Exception $e) {
            $connection->update([
                'last_sync_at' => Carbon::now(),
                'last_sync_status' => 'error',
                'last_sync_error' => $e->getMessage(),
            ]);

            return $this->error('Failed to sync events: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Pull events from Google Calendar
     */
    public function syncPull(Request $request)
    {
        $companyId = $this->getCompanyId();
        $userId = $request->input('user_id', $request->user()->id);

        $validator = Validator::make($request->all(), [
            'start' => 'nullable|date',
            'end' => 'nullable|date|after:start',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', $validator->errors(), 422);
        }

        $connection = GoogleCalendarConnection::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        if (!$connection) {
            return $this->error('No active Google Calendar connection found', null, 404);
        }

        try {
            $timeMin = $request->filled('start')
                ? Carbon::parse($request->input('start'))
                : Carbon::now();
            $timeMax = $request->filled('end')
                ? Carbon::parse($request->input('end'))
                : Carbon::now()->addDays(30);

            $googleEvents = $this->googleCalendarService->pullEvents($connection, $timeMin, $timeMax);

            // Detect conflicts with existing Workero events
            $conflicts = [];
            $created = 0;
            $skipped = 0;

            foreach ($googleEvents as $googleEvent) {
                // Check for time overlap with existing events
                $overlapping = ScheduleEvent::where('company_id', $companyId)
                    ->where(function ($query) use ($googleEvent) {
                        $query->whereBetween('start', [
                            Carbon::parse($googleEvent['start']),
                            Carbon::parse($googleEvent['end']),
                        ])->orWhereBetween('end', [
                            Carbon::parse($googleEvent['start']),
                            Carbon::parse($googleEvent['end']),
                        ]);
                    })
                    ->exists();

                if ($overlapping) {
                    $conflicts[] = $googleEvent;
                    $skipped++;
                } else {
                    // Optionally create external event markers (or skip)
                    // For now, we'll just track them as conflicts
                    $skipped++;
                }
            }

            $connection->update([
                'last_sync_at' => Carbon::now(),
                'last_sync_status' => empty($conflicts) ? 'success' : 'partial',
                'last_sync_error' => !empty($conflicts) ? json_encode(['conflicts' => count($conflicts)]) : null,
            ]);

            return $this->success([
                'pulled' => count($googleEvents),
                'conflicts' => count($conflicts),
                'skipped' => $skipped,
                'conflict_details' => $conflicts,
            ], 'Events pulled from Google Calendar');
        } catch (\Exception $e) {
            $connection->update([
                'last_sync_at' => Carbon::now(),
                'last_sync_status' => 'error',
                'last_sync_error' => $e->getMessage(),
            ]);

            return $this->error('Failed to pull events: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Disconnect Google Calendar
     */
    public function disconnect(Request $request)
    {
        $companyId = $this->getCompanyId();
        $userId = $request->input('user_id', $request->user()->id);

        $connection = GoogleCalendarConnection::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        if (!$connection) {
            return $this->error('No active Google Calendar connection found', null, 404);
        }

        $connection->update(['is_active' => false]);

        return $this->success(null, 'Google Calendar disconnected successfully');
    }
}
