<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A "step reached" event from a website journey (see App\Support\JourneyCatalog).
 *
 * @property int $id
 * @property string|null $user_id
 * @property string|null $visitor_id
 * @property string $journey
 * @property string $step
 * @property string|null $detail
 * @property Carbon $created_at
 */
class JourneyEvent extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'visitor_id',
        'journey',
        'step',
        'detail',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Kept for navesta.journeys.retention_days, then removed by `model:prune`. */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays((int) config('navesta.journeys.retention_days')));
    }
}
