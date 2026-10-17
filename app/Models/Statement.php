<?php

namespace App\Models;

use App\Enums\StatementStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Cast attributes (column types come from the migrations).
 *
 * @property StatementStatus $status
 * @property Carbon|null $scanned_at
 */
class Statement extends Model
{
    use HasUuids, Prunable;

    protected $fillable = [
        'original_name',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'sha256',
        'status',
        'scan_signature',
        'scanned_at',
    ];

    protected $attributes = [
        'status' => 'pending_scan',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'status' => StatementStatus::class,
            'scanned_at' => 'datetime',
        ];
    }

    /**
     * Uploaded statements are deleted after the retention period
     * (navesta.retention.statement_days), file included.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays((int) config('navesta.retention.statement_days')));
    }

    protected function pruning(): void
    {
        if ($this->path) {
            Storage::disk($this->disk)->delete($this->path);
        }
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Files are only handed back once a scan found nothing (or scanning is
     * switched off in this environment) and the file still exists.
     */
    public function isDownloadable(): bool
    {
        return $this->path !== null
            && in_array($this->status, [StatementStatus::Clean, StatementStatus::Unscanned], true);
    }
}
