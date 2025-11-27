<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TravelTimeService
{
    private function geocodeAddress(string $address): ?array
    {
        // Use Nominatim (OpenStreetMap) for free geocoding
        $response = Http::get('https://nominatim.openstreetmap.org/search', [
            'q' => $address,
            'format' => 'json',
            'limit' => 1,
        ]);

        if ($response->failed() || empty($response->json())) {
            return null;
        }

        $data = $response->json();
        if (empty($data[0])) {
            return null;
        }

        return [
            'lat' => (float) $data[0]['lat'],
            'lon' => (float) $data[0]['lon'],
        ];
    }

    public function calculate(string $origin, string $destination, string $mode = 'driving'): array
    {
        // Geocode addresses to coordinates
        $originCoords = $this->geocodeAddress($origin);
        $destCoords = $this->geocodeAddress($destination);

        if (!$originCoords || !$destCoords) {
            throw new \RuntimeException('Could not geocode one or both addresses.');
        }

        // Map mode to OpenRouteService profile
        $profileMap = [
            'driving' => 'driving-car',
            'walking' => 'foot-walking',
            'bicycling' => 'cycling-regular',
            'transit' => 'driving-car', // OpenRouteService doesn't support transit, fallback to driving
        ];
        $profile = $profileMap[$mode] ?? 'driving-car';

        // Use OpenRouteService (free, no API key required for basic usage)
        // But you can get a free API key at https://openrouteservice.org/ for higher limits
        $apiKey = config('services.openroute.api_key');
        $headers = [];
        if ($apiKey) {
            $headers['Authorization'] = $apiKey;
        }

        $response = Http::withHeaders($headers)->post('https://api.openrouteservice.org/v2/directions/' . $profile, [
            'coordinates' => [
                [$originCoords['lon'], $originCoords['lat']],
                [$destCoords['lon'], $destCoords['lat']],
            ],
            'units' => 'km',
        ]);

        if ($response->failed()) {
            Log::error('TravelTimeService::calculate failed', [
                'origin' => $origin,
                'destination' => $destination,
                'mode' => $mode,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Failed to retrieve travel time data.');
        }

        $data = $response->json();

        if (empty($data['routes']) || empty($data['routes'][0]['summary'])) {
            throw new \RuntimeException('No route found for the provided locations.');
        }

        $summary = $data['routes'][0]['summary'];
        $distanceMeters = $summary['distance'] ?? 0;
        $durationSeconds = $summary['duration'] ?? 0;

        return [
            'origin' => $origin,
            'destination' => $destination,
            'mode' => $mode,
            'distance_text' => $this->formatDistance($distanceMeters),
            'distance_km' => round($distanceMeters / 1000, 2),
            'duration_text' => $this->formatDuration($durationSeconds),
            'duration_minutes' => round($durationSeconds / 60, 1),
        ];
    }

    private function formatDistance(float $meters): string
    {
        if ($meters < 1000) {
            return round($meters) . ' m';
        }
        return round($meters / 1000, 2) . ' km';
    }

    private function formatDuration(float $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        return $minutes . ' min';
    }

    /**
     * Calculate distance matrix for multiple locations
     */
    public function calculateDistanceMatrix(array $locations, string $mode = 'driving'): array
    {
        // Geocode all locations
        $coordinates = [];
        foreach ($locations as $location) {
            $coords = $this->geocodeAddress($location);
            if ($coords) {
                $coordinates[] = [$coords['lon'], $coords['lat']];
            } else {
                throw new \RuntimeException("Could not geocode location: {$location}");
            }
        }

        if (count($coordinates) < 2) {
            throw new \RuntimeException('At least 2 locations required for distance matrix.');
        }

        // Map mode to OpenRouteService profile
        $profileMap = [
            'driving' => 'driving-car',
            'walking' => 'foot-walking',
            'bicycling' => 'cycling-regular',
            'transit' => 'driving-car',
        ];
        $profile = $profileMap[$mode] ?? 'driving-car';

        $apiKey = config('services.openroute.api_key');
        $headers = [];
        if ($apiKey) {
            $headers['Authorization'] = $apiKey;
        }

        // OpenRouteService Matrix API
        $response = Http::withHeaders($headers)->post('https://api.openrouteservice.org/v2/matrix/' . $profile, [
            'locations' => $coordinates,
            'units' => 'km',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to retrieve distance matrix.');
        }

        $data = $response->json();

        if (empty($data['distances']) || empty($data['durations'])) {
            throw new \RuntimeException('Invalid response from OpenRouteService Matrix API.');
        }

        $matrix = [];
        $distances = $data['distances'] ?? [];
        $durations = $data['durations'] ?? [];

        foreach ($distances as $i => $row) {
            $matrix[$i] = [];
            foreach ($row as $j => $distance) {
                if ($distance !== null && isset($durations[$i][$j])) {
                    $matrix[$i][$j] = [
                        'distance_km' => round($distance / 1000, 2),
                        'duration_minutes' => round($durations[$i][$j] / 60, 1),
                    ];
                } else {
                    $matrix[$i][$j] = null;
                }
            }
        }

        return $matrix;
    }

    /**
     * Optimize route using Nearest Neighbor heuristic (simplified TSP)
     * Returns optimized order of locations
     */
    public function optimizeRoute(array $locations, string $startLocation = null, string $mode = 'driving'): array
    {
        if (count($locations) < 2) {
            return $locations;
        }

        // If start location specified, use it; otherwise use first location
        $start = $startLocation ?? $locations[0];
        $remaining = array_values(array_filter($locations, fn($loc) => $loc !== $start));
        $route = [$start];
        $current = $start;

        // Nearest Neighbor algorithm
        while (!empty($remaining)) {
            $nearest = null;
            $nearestDistance = PHP_FLOAT_MAX;

            foreach ($remaining as $location) {
                try {
                    $result = $this->calculate($current, $location, $mode);
                    $distance = $result['distance_km'];

                    if ($distance < $nearestDistance) {
                        $nearestDistance = $distance;
                        $nearest = $location;
                    }
                } catch (\Exception $e) {
                    // Skip locations that can't be reached
                    continue;
                }
            }

            if ($nearest === null) {
                // If no reachable location found, add remaining in order
                $route = array_merge($route, $remaining);
                break;
            }

            $route[] = $nearest;
            $remaining = array_values(array_filter($remaining, fn($loc) => $loc !== $nearest));
            $current = $nearest;
        }

        return $route;
    }

    /**
     * Get detailed route with waypoints using OpenRouteService Directions API
     */
    public function getRouteWithWaypoints(array $waypoints, string $mode = 'driving'): array
    {
        if (count($waypoints) < 2) {
            throw new \RuntimeException('At least 2 waypoints required for route.');
        }

        // Geocode all waypoints
        $coordinates = [];
        foreach ($waypoints as $waypoint) {
            $coords = $this->geocodeAddress($waypoint);
            if ($coords) {
                $coordinates[] = [$coords['lon'], $coords['lat']];
            } else {
                throw new \RuntimeException("Could not geocode waypoint: {$waypoint}");
            }
        }

        // Map mode to OpenRouteService profile
        $profileMap = [
            'driving' => 'driving-car',
            'walking' => 'foot-walking',
            'bicycling' => 'cycling-regular',
            'transit' => 'driving-car',
        ];
        $profile = $profileMap[$mode] ?? 'driving-car';

        $apiKey = config('services.openroute.api_key');
        $headers = [];
        if ($apiKey) {
            $headers['Authorization'] = $apiKey;
        }

        $response = Http::withHeaders($headers)->post('https://api.openrouteservice.org/v2/directions/' . $profile, [
            'coordinates' => $coordinates,
            'instructions' => true,
            'geometry' => true,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to retrieve route directions.');
        }

        $data = $response->json();

        if (empty($data['routes']) || empty($data['routes'][0])) {
            throw new \RuntimeException('No route found.');
        }

        $route = $data['routes'][0];
        $segments = $route['segments'] ?? [];
        $geometry = $route['geometry'] ?? null;

        $totalDistance = 0;
        $totalDuration = 0;
        $steps = [];
        $legs = [];

        foreach ($segments as $segment) {
            $distance = $segment['distance'] ?? 0;
            $duration = $segment['duration'] ?? 0;
            $totalDistance += $distance;
            $totalDuration += $duration;

            $legs[] = [
                'distance_km' => round($distance / 1000, 2),
                'duration_minutes' => round($duration / 60, 1),
                'start_address' => '', // OpenRouteService doesn't provide addresses
                'end_address' => '',
            ];

            foreach ($segment['steps'] ?? [] as $step) {
                $steps[] = [
                    'instruction' => $step['instruction'] ?? '',
                    'distance' => $this->formatDistance($step['distance'] ?? 0),
                    'duration' => $this->formatDuration($step['duration'] ?? 0),
                    'start_location' => isset($step['way_points'][0]) ? [
                        'lat' => $coordinates[$step['way_points'][0]][1],
                        'lng' => $coordinates[$step['way_points'][0]][0],
                    ] : null,
                    'end_location' => isset($step['way_points'][1]) ? [
                        'lat' => $coordinates[$step['way_points'][1]][1],
                        'lng' => $coordinates[$step['way_points'][1]][0],
                    ] : null,
                ];
            }
        }

        return [
            'waypoints' => $waypoints,
            'total_distance_km' => round($totalDistance / 1000, 2),
            'total_duration_minutes' => round($totalDuration / 60, 1),
            'total_duration_text' => $this->formatDuration($totalDuration),
            'polyline' => $geometry, // Encoded polyline from OpenRouteService
            'bounds' => null, // Can be calculated from coordinates if needed
            'steps' => $steps,
            'legs' => $legs,
        ];
    }
}


