<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PincodeService
{
    /**
     * Standard major postal zone fallback directory for instantaneous offline resolution.
     */
    private const ZONE_MAP = [
        '11' => ['state' => 'Delhi', 'district' => 'Central Delhi', 'city' => 'New Delhi'],
        '12' => ['state' => 'Haryana', 'district' => 'Gurugram', 'city' => 'Gurugram'],
        '20' => ['state' => 'Uttar Pradesh', 'district' => 'Gautam Buddha Nagar', 'city' => 'Noida'],
        '40' => ['state' => 'Maharashtra', 'district' => 'Mumbai', 'city' => 'Mumbai'],
        '41' => ['state' => 'Maharashtra', 'district' => 'Pune', 'city' => 'Pune'],
        '50' => ['state' => 'Telangana', 'district' => 'Hyderabad', 'city' => 'Hyderabad'],
        '56' => ['state' => 'Karnataka', 'district' => 'Bengaluru Urban', 'city' => 'Bengaluru'],
        '60' => ['state' => 'Tamil Nadu', 'district' => 'Chennai', 'city' => 'Chennai'],
        '70' => ['state' => 'West Bengal', 'district' => 'Kolkata', 'city' => 'Kolkata'],
    ];

    /**
     * Resolve 6-digit Indian Postal PIN code with Redis caching.
     */
    public function lookup(string $pincode): ?array
    {
        $clean = trim($pincode);

        if (! preg_match('/^[1-9][0-9]{5}$/', $clean)) {
            return null;
        }

        return Cache::remember("pincode:{$clean}", 86400 * 30, function () use ($clean) {
            return $this->fetchFromProvider($clean) ?? $this->resolveFallback($clean);
        });
    }

    private function fetchFromProvider(string $clean): ?array
    {
        try {
            $response = Http::timeout(3)->get("https://api.postalpincode.in/pincode/{$clean}");

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if (! is_array($data) || empty($data[0]) || ($data[0]['Status'] ?? '') !== 'Success') {
                return null;
            }

            $postOffices = $data[0]['PostOffice'] ?? [];
            if (empty($postOffices)) {
                return null;
            }

            $primary = $postOffices[0];

            return [
                'pincode' => $clean,
                'state' => $primary['State'] ?? '',
                'district' => $primary['District'] ?? '',
                'city' => $primary['Block'] && $primary['Block'] !== 'NA' ? $primary['Block'] : ($primary['District'] ?? ''),
                'division' => $primary['Division'] ?? '',
                'post_offices' => array_column($postOffices, 'Name'),
                'source' => 'india_post',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveFallback(string $clean): ?array
    {
        $prefix = substr($clean, 0, 2);

        if (isset(self::ZONE_MAP[$prefix])) {
            $info = self::ZONE_MAP[$prefix];
            return [
                'pincode' => $clean,
                'state' => $info['state'],
                'district' => $info['district'],
                'city' => $info['city'],
                'division' => $info['district'],
                'post_offices' => [$info['city']],
                'source' => 'zone_heuristics',
            ];
        }

        return null;
    }
}
