<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Snapshot for one batch of customers; navesta:capture-snapshots splits the
 * customer base into these so any number of workers can share the work.
 */
class CaptureSnapshotsBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<string>  $userIds
     */
    public function __construct(public array $userIds) {}

    public function handle(DashboardService $dashboard): void
    {
        User::whereIn('id', $this->userIds)->each(fn (User $user) => $dashboard->captureMonthlySnapshot($user));
    }
}
