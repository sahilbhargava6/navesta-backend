<?php

namespace App\Services;

use App\Support\PincodeLookup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifies an Indian PIN code and returns the place it belongs to, for
 * address autofill. MapmyIndia (Mappls) geocoding first, India Post's public
 * directory as the backup. Nothing is guessed: a pincode is either confirmed
 * by a provider, reported as non-existent, or "can't check right now".
 */
class PincodeService
{
    private const VERIFIED_TTL = 86400 * 30;  // pincodes change rarely

    private const NOT_FOUND_TTL = 86400;      // retry unknown pincodes daily

    public function lookup(string $pincode): PincodeLookup
    {
        $pin = trim($pincode);
        if (! preg_match('/^[1-9][0-9]{5}$/', $pin)) {
            return PincodeLookup::notFound($pin);
        }

        $cached = PincodeLookup::fromCache(Cache::get("pincode:v2:{$pin}"));
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->fromMappls($pin);
        if ($result->status === PincodeLookup::UNAVAILABLE) {
            $result = $this->fromIndiaPost($pin);
        }

        // Only definite answers are cached; an outage is retried next time
        if ($result->status !== PincodeLookup::UNAVAILABLE) {
            Cache::put("pincode:v2:{$pin}", $result->toCache(), $result->isVerified() ? self::VERIFIED_TTL : self::NOT_FOUND_TTL);
        }

        return $result;
    }

    /**
     * Mappls Geocoding API (static key), restricted to pincode-level matches.
     * 204 = no such pincode. https://developer.mappls.com (Geocoding API)
     */
    private function fromMappls(string $pin): PincodeLookup
    {
        $key = (string) config('services.mappls.static_key');
        if ($key === '') {
            return PincodeLookup::unavailable($pin);
        }

        try {
            $response = Http::timeout((int) config('services.mappls.timeout', 3))
                ->acceptJson()
                ->get((string) config('services.mappls.geocode_url'), [
                    'address' => $pin,
                    'podFilter' => 'pincode',
                    'itemCount' => 1,
                    'access_token' => $key,
                ]);
        } catch (Throwable $e) {
            Log::warning('Mappls pincode lookup failed', ['error' => $e::class]);

            return PincodeLookup::unavailable($pin);
        }

        if ($response->status() === 204) {
            return PincodeLookup::notFound($pin, 'mappls');
        }
        if (! $response->successful()) {
            // 401/403: wrong key or quota used up; never log the key (it is in the URL)
            Log::warning('Mappls pincode lookup refused', ['status' => $response->status()]);

            return PincodeLookup::unavailable($pin);
        }

        $results = $response->json('copResults');
        $hit = is_array($results) && array_is_list($results) ? ($results[0] ?? null) : $results;

        // A match must be the pincode itself, not a nearby one
        if (! is_array($hit) || (string) ($hit['pincode'] ?? '') !== $pin) {
            return PincodeLookup::notFound($pin, 'mappls');
        }

        $district = $this->clean($hit['district'] ?? null, ' District');

        return PincodeLookup::verified(
            $pin,
            city: $this->clean($hit['city'] ?? null) ?: $district ?: $this->clean($hit['subDistrict'] ?? null),
            district: $district,
            state: $this->clean($hit['state'] ?? null),
            locality: $this->clean($hit['locality'] ?? null) ?: $this->clean($hit['subDistrict'] ?? null) ?: null,
            source: 'mappls',
        );
    }

    /**
     * India Post's public pincode directory (no key).
     */
    private function fromIndiaPost(string $pin): PincodeLookup
    {
        try {
            $response = Http::timeout(4)->acceptJson()->get((string) config('services.india_post.pincode_url').$pin);
        } catch (Throwable $e) {
            Log::warning('India Post pincode lookup failed', ['error' => $e::class]);

            return PincodeLookup::unavailable($pin);
        }

        $data = $response->json();
        if (! $response->successful() || ! is_array($data) || ! isset($data[0]['Status'])) {
            return PincodeLookup::unavailable($pin);
        }

        $offices = $data[0]['PostOffice'] ?? null;
        if ($data[0]['Status'] !== 'Success' || ! is_array($offices) || $offices === []) {
            return PincodeLookup::notFound($pin, 'india_post');
        }

        $first = $offices[0];
        $district = $this->clean($first['District'] ?? null);
        $block = $this->clean($first['Block'] ?? null);

        return PincodeLookup::verified(
            $pin,
            city: $block !== '' && strtoupper($block) !== 'NA' ? $block : $district,
            district: $district,
            state: $this->clean($first['State'] ?? null),
            locality: $this->clean($first['Name'] ?? null) ?: null,
            source: 'india_post',
        );
    }

    private function clean(mixed $value, string $stripSuffix = ''): string
    {
        $s = trim((string) $value);
        if ($stripSuffix !== '' && str_ends_with($s, $stripSuffix)) {
            $s = trim(substr($s, 0, -strlen($stripSuffix)));
        }

        return $s;
    }
}
