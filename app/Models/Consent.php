<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One grant or withdrawal of consent. Rows are never updated: the latest
 * row per purpose is the current state.
 *
 * @property bool $granted
 * @property Carbon $created_at
 */
class Consent extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'purpose',
        'notice_version',
        'granted',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
