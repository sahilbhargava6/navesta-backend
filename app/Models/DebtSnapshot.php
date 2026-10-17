<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cast attributes (column types come from the migrations).
 *
 * @property Carbon $snapshot_month
 */
class DebtSnapshot extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'snapshot_month',
        'cc_outstanding_paise',
        'loan_outstanding_paise',
        'total_emi_paise',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_month' => 'date',
            'cc_outstanding_paise' => 'integer',
            'loan_outstanding_paise' => 'integer',
            'total_emi_paise' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
