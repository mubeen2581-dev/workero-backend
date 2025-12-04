<?php

namespace App\Services;

use App\Models\GoogleCalendarConnection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->clientId = config('services.google_calendar.client_id');
        $this->clientSecret = config('services.google_calendar.client_secret');
        $this->redirectUri = config('app.url') . '/api/calendar/callback';
    }

    /**
     * Get OAuth authorization URL
     */
    public function getAuthUrl(string $state = null): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens
     */
    public function exchangeCodeForTokens(string $code): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
        ]);

        if ($response->failed()) {
            Log::error('GoogleCalendarService::exchangeCodeForTokens failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to exchange authorization code for tokens.');
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => $data['expires_in'] ?? 3600,
        ];
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            Log::error('GoogleCalendarService::refreshAccessToken failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to refresh access token.');
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'expires_in' => $data['expires_in'] ?? 3600,
        ];
    }

    /**
     * Get user info from Google
     */
    public function getUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if ($response->failed()) {
            throw new \RuntimeException('Failed to retrieve user info from Google.');
        }

        return $response->json();
    }

    /**
     * Get or refresh access token for a connection
     */
    public function getValidAccessToken(GoogleCalendarConnection $connection): string
    {
        if ($connection->token_expires_at && $connection->token_expires_at->isFuture()) {
            return $connection->access_token;
        }

        if (!$connection->refresh_token) {
            throw new \RuntimeException('No refresh token available. Please reconnect your Google Calendar.');
        }

        $tokens = $this->refreshAccessToken($connection->refresh_token);
        $connection->update([
            'access_token' => $tokens['access_token'],
            'token_expires_at' => Carbon::now()->addSeconds($tokens['expires_in']),
        ]);

        return $tokens['access_token'];
    }

    /**
     * Push Workero events to Google Calendar
     */
    public function pushEvents(GoogleCalendarConnection $connection, array $events): array
    {
        $accessToken = $this->getValidAccessToken($connection);
        $calendarId = $connection->calendar_id;
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($events as $event) {
            try {
                $googleEvent = $this->convertToGoogleEvent($event);
                $googleEventId = $event['google_event_id'] ?? null;

                if ($googleEventId) {
                    // Update existing event
                    $response = Http::withToken($accessToken)
                        ->put("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events/{$googleEventId}", $googleEvent);
                } else {
                    // Create new event
                    $response = Http::withToken($accessToken)
                        ->post("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events", $googleEvent);
                }

                if ($response->successful()) {
                    $googleEventId ? $updated++ : $created++;
                } else {
                    $errors[] = [
                        'event_id' => $event['id'] ?? null,
                        'error' => $response->json()['error']['message'] ?? 'Unknown error',
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'event_id' => $event['id'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Pull events from Google Calendar
     */
    public function pullEvents(GoogleCalendarConnection $connection, Carbon $timeMin, Carbon $timeMax): array
    {
        $accessToken = $this->getValidAccessToken($connection);
        $calendarId = $connection->calendar_id;

        $response = Http::withToken($accessToken)->get("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events", [
            'timeMin' => $timeMin->toRfc3339String(),
            'timeMax' => $timeMax->toRfc3339String(),
            'singleEvents' => true,
            'orderBy' => 'startTime',
            'maxResults' => 2500,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to retrieve events from Google Calendar.');
        }

        $data = $response->json();
        $googleEvents = $data['items'] ?? [];

        return array_map(function ($googleEvent) {
            return $this->convertFromGoogleEvent($googleEvent);
        }, $googleEvents);
    }

    /**
     * Convert Workero event to Google Calendar event format
     */
    private function convertToGoogleEvent(array $event): array
    {
        $start = Carbon::parse($event['start']);
        $end = Carbon::parse($event['end']);

        return [
            'summary' => $event['title'] ?? 'Scheduled Event',
            'description' => $event['description'] ?? '',
            'location' => $event['location'] ?? '',
            'start' => [
                'dateTime' => $start->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ],
            'end' => [
                'dateTime' => $end->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ],
            'extendedProperties' => [
                'private' => [
                    'workero_event_id' => $event['id'] ?? null,
                    'workero_company_id' => $event['company_id'] ?? null,
                ],
            ],
        ];
    }

    /**
     * Convert Google Calendar event to Workero format
     */
    private function convertFromGoogleEvent(array $googleEvent): array
    {
        $start = $googleEvent['start']['dateTime'] ?? $googleEvent['start']['date'];
        $end = $googleEvent['end']['dateTime'] ?? $googleEvent['end']['date'];

        return [
            'google_event_id' => $googleEvent['id'],
            'title' => $googleEvent['summary'] ?? 'Untitled Event',
            'description' => $googleEvent['description'] ?? '',
            'location' => $googleEvent['location'] ?? '',
            'start' => $start,
            'end' => $end,
            'html_link' => $googleEvent['htmlLink'] ?? null,
            'status' => $googleEvent['status'] ?? 'confirmed',
        ];
    }

    /**
     * Delete event from Google Calendar
     */
    public function deleteEvent(GoogleCalendarConnection $connection, string $googleEventId): bool
    {
        $accessToken = $this->getValidAccessToken($connection);
        $calendarId = $connection->calendar_id;

        $response = Http::withToken($accessToken)
            ->delete("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events/{$googleEventId}");

        return $response->successful();
    }
}

