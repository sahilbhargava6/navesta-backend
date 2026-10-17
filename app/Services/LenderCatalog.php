<?php

namespace App\Services;

use App\Models\Lender;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Active lenders, cached. The list changes only when an admin edits a
 * lender, which clears the cache (see Lender::booted).
 */
class LenderCatalog
{
    private const KEY = 'lenders:active';

    private const VERSION_KEY = 'lenders:version';

    /**
     * Active lenders, cheapest first.
     *
     * @return Collection<int, Lender>
     */
    public function active(): Collection
    {
        return Cache::rememberForever(self::KEY, fn () => Lender::active()->orderBy('min_rate_bps')->get());
    }

    /**
     * Changes whenever the catalogue changes; part of the cache key of any
     * customer result that depends on lenders.
     */
    public function version(): string
    {
        return Cache::rememberForever(self::VERSION_KEY, fn () => Str::random(8));
    }

    public static function flush(): void
    {
        Cache::forget(self::KEY);
        Cache::forget(self::VERSION_KEY);
    }
}
