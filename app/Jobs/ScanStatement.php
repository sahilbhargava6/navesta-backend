<?php

namespace App\Jobs;

use App\Contracts\VirusScanner;
use App\Enums\StatementStatus;
use App\Models\Statement;
use App\Services\StatementVault;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Virus-scans an uploaded statement. An infected file is deleted at once;
 * the record stays (status "infected") so the customer sees what happened.
 */
class ScanStatement implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    public function __construct(public Statement $statement)
    {
        $this->onQueue('scans');
    }

    public function handle(VirusScanner $scanner, StatementVault $vault): void
    {
        $statement = $this->statement;
        if ($statement->status !== StatementStatus::PendingScan || ! $statement->path) {
            return;
        }

        $result = $scanner->scan($vault->contents($statement));

        if ($result->clean === false) {
            Storage::disk($statement->disk)->delete($statement->path);
            Log::warning('Infected statement upload removed', [
                'statement' => $statement->id,
                'user' => $statement->user_id,
                'signature' => $result->signature,
            ]);
        }

        $statement->update([
            'status' => match ($result->clean) {
                true => StatementStatus::Clean,
                false => StatementStatus::Infected,
                null => StatementStatus::Unscanned,
            },
            'scan_signature' => $result->signature,
            'path' => $result->clean === false ? null : $statement->path,
            'scanned_at' => now(),
        ]);
    }

    /**
     * Scanner unreachable after every retry: keep the file locked away.
     */
    public function failed(?Throwable $e): void
    {
        $this->statement->update(['status' => StatementStatus::ScanFailed]);
    }
}
