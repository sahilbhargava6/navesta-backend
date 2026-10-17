<?php

namespace App\Services;

use App\Contracts\MessageGateway;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Six-digit codes sent by SMS. Only a keyed hash of the code is stored
 * (in the cache, which expires it), each code allows a limited number of
 * guesses, and sending is rate limited per customer.
 */
class OtpService
{
    public function __construct(private MessageGateway $gateway) {}

    /**
     * @param  'verify_phone'|'login'  $purpose
     * @return int seconds until the code expires
     */
    public function send(User $user, string $purpose): int
    {
        if (! $user->phone) {
            abort(400, 'Add a phone number to your profile first');
        }

        $limiterKey = "otp-send:{$user->id}";
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            abort(429, 'Too many codes requested. Try again in '.ceil(RateLimiter::availableIn($limiterKey) / 60).' minutes.');
        }
        RateLimiter::hit($limiterKey, 3600);

        $length = (int) config('navesta.otp.length');
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
        $ttl = (int) config('navesta.otp.ttl_minutes') * 60;

        Cache::put($this->key($user, $purpose), ['hash' => $this->hash($code), 'attempts' => 0], $ttl);

        $this->gateway->send('sms', $user->phone, "{$code} is your Navesta verification code. It expires in ".($ttl / 60).' minutes. Never share it with anyone.');

        return $ttl;
    }

    public function verify(User $user, string $purpose, string $code): bool
    {
        $key = $this->key($user, $purpose);
        $entry = Cache::get($key);

        if (! is_array($entry)) {
            return false;
        }

        if ($entry['attempts'] >= (int) config('navesta.otp.max_attempts')) {
            Cache::forget($key);

            return false;
        }

        if (hash_equals($entry['hash'], $this->hash($code))) {
            Cache::forget($key);

            return true;
        }

        $entry['attempts']++;
        Cache::put($key, $entry, (int) config('navesta.otp.ttl_minutes') * 60);

        return false;
    }

    /**
     * Mask a phone number for display: +91******3210.
     */
    public static function maskPhone(string $phone): string
    {
        return Str::substr($phone, 0, 3).str_repeat('*', max(0, Str::length($phone) - 7)).Str::substr($phone, -4);
    }

    private function key(User $user, string $purpose): string
    {
        return "otp:{$purpose}:{$user->id}";
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
