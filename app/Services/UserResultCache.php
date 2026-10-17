<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Caches computed results per customer (dashboard, payoff plans, lender
 * matches). Each customer has a version token; changing their cards, loans,
 * scores or profile replaces the token, so stale entries are never read
 * and simply expire.
 */
class UserResultCache
{
    public function __construct(private LenderCatalog $lenders) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $compute
     * @return T
     */
    public function remember(string $userId, string $name, Closure $compute, bool $dependsOnLenders = false): mixed
    {
        $key = "user:{$userId}:".self::version($userId).':'.$name
            .($dependsOnLenders ? ':l'.$this->lenders->version() : '');

        return Cache::remember($key, now()->addMinutes((int) config('navesta.cache.user_results_minutes')), $compute);
    }

    public static function forget(string $userId): void
    {
        Cache::forever("user-version:{$userId}", Str::random(8));
    }

    private static function version(string $userId): string
    {
        return Cache::rememberForever("user-version:{$userId}", fn () => Str::random(8));
    }
}
