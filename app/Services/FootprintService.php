<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFootprint;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FootprintService
{
    /**
     * Record a customer digital footprint event (IP, cookies, user-agent, session, device fingerprint).
     */
    public function capture(Request $request, string $eventType, ?User $user = null, array $cookieConsent = []): UserFootprint
    {
        $userAgent = (string) $request->userAgent();
        $ip = (string) $request->ip();

        return UserFootprint::create([
            'user_id' => $user?->getKey() ?? auth()->id(),
            'event_type' => $eventType,
            'ip_address' => $ip,
            'user_agent' => Str::limit($userAgent, 500, ''),
            'device_type' => $this->detectDeviceType($userAgent),
            'browser' => $this->detectBrowser($userAgent),
            'os' => $this->detectOs($userAgent),
            'fingerprint_hash' => $request->header('X-Device-Fingerprint') ?: $request->input('deviceFingerprint'),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'country_code' => $request->header('CF-IPCountry') ?: $request->header('X-Country-Code'),
            'city' => $request->header('CF-IPCity'),
            'cookie_consent' => $cookieConsent ?: ($request->input('cookieConsent') ?: null),
            'headers_snapshot' => [
                'sec_ch_ua' => $request->header('Sec-CH-UA'),
                'sec_ch_ua_mobile' => $request->header('Sec-CH-UA-Mobile'),
                'sec_ch_ua_platform' => $request->header('Sec-CH-UA-Platform'),
                'accept_language' => $request->header('Accept-Language'),
            ],
        ]);
    }

    private function detectDeviceType(string $ua): string
    {
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/(mobile|iphone|ipod|blackberry|opera mini|iemobile|wpdesktop)/i', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    private function detectBrowser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome/') && ! str_contains($ua, 'Edg/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome/') => 'Safari',
            default => 'Other',
        };
    }

    private function detectOs(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown',
        };
    }
}
