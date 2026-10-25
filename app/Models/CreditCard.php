<?php

namespace App\Models;

use App\Enums\CardStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cast attributes (column types come from the migrations).
 *
 * @property CardStatus $status
 */
class CreditCard extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'bank_name',
        'card_name',
        'last_four',
        'credit_limit_paise',
        'outstanding_paise',
        'apr_bps',
        'min_payment_paise',
        'due_day',
        'status',
    ];

    protected $attributes = [
        'outstanding_paise' => 0,
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit_paise' => 'integer',
            'outstanding_paise' => 'integer',
            'apr_bps' => 'integer',
            'min_payment_paise' => 'integer',
            'due_day' => 'integer',
            'status' => CardStatus::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', CardStatus::Active);
    }
}
