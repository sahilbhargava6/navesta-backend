<?php

namespace App\Models;

use App\Enums\CreditScoreSource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cast attributes (column types come from the migrations).
 *
 * @property CreditScoreSource $source
 * @property Carbon $fetched_at
 */
class CreditScore extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'score',
        'source',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'fetched_at' => 'datetime',
            'source' => CreditScoreSource::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
