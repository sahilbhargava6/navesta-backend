<?php

namespace App\Models;

use App\Enums\BtStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cast attributes (column types come from the migrations).
 *
 * @property BtStatus $status
 */
class BtApplication extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'lender_id',
        'source_loan_id',
        'source_cc_id',
        'outstanding_paise',
        'current_rate_bps',
        'new_rate_bps',
        'requested_tenure_months',
        'crif_score',
        'status',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'outstanding_paise' => 'integer',
            'current_rate_bps' => 'integer',
            'new_rate_bps' => 'integer',
            'requested_tenure_months' => 'integer',
            'crif_score' => 'integer',
            'status' => BtStatus::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Lender, $this> */
    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }

    /** @return BelongsTo<Loan, $this> */
    public function sourceLoan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'source_loan_id');
    }

    /** @return BelongsTo<CreditCard, $this> */
    public function sourceCreditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class, 'source_cc_id');
    }
}
