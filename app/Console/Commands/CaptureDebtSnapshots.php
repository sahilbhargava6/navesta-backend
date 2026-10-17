<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Jobs\CaptureSnapshotsBatch;
use App\Models\User;
use Illuminate\Console\Command;

class CaptureDebtSnapshots extends Command
{
    protected $signature = 'navesta:capture-snapshots';

    protected $description = "Queue this month's debt snapshot for every customer, in batches";

    private const BATCH_SIZE = 500;

    public function handle(): int
    {
        $batches = 0;
        $customers = 0;

        User::where('role', UserRole::Customer)->select('id')->chunkById(self::BATCH_SIZE, function ($users) use (&$batches, &$customers) {
            CaptureSnapshotsBatch::dispatch($users->pluck('id')->all());
            $batches++;
            $customers += $users->count();
        });

        $this->info("Queued snapshots for {$customers} customers in {$batches} batches.");

        return self::SUCCESS;
    }
}
