<?php

namespace App\Observers;

use App\Models\Lender;
use App\Models\User;
use App\Services\LenderCatalog;
use App\Services\UserResultCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Drops cached results when the data behind them changes: a customer's
 * cards, loans, scores or profile, or the lender catalogue.
 */
class CacheInvalidationObserver
{
    public function saved(Model $model): void
    {
        $this->flush($model);
    }

    public function deleted(Model $model): void
    {
        $this->flush($model);
    }

    private function flush(Model $model): void
    {
        match (true) {
            $model instanceof Lender => LenderCatalog::flush(),
            $model instanceof User => UserResultCache::forget($model->id),
            default => UserResultCache::forget((string) $model->getAttribute('user_id')),
        };
    }
}
