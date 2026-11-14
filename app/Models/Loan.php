<?php

namespace App\Models;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cast attributes (column types come from the migrations).
 *
 * @property LoanStatus $status
 * @property LoanType $loan_type
 * @property Carbon|null $next_due_date
 */
class Loan extends Model
{
    use HasUuids;

    protected $fillable = [
        'lender_name',
        'loan_type',
        'principal_paise',
        'outstanding_paise',
        'interest_rate_bps',
        'emi_paise',
        'tenure_months',
        'tenure_remaining_months',
        'next_due_date',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'principal_paise' => 'integer',
            'outstanding_paise' => 'integer',
            'interest_rate_bps' => 'integer',
            'emi_paise' => 'integer',
            'tenure_months' => 'integer',
            'tenure_remaining_months' => 'integer',
            'next_due_date' => 'date',
            'status' => LoanStatus::class,
            'loan_type' => LoanType::class,
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
        $query->where('status', LoanStatus::Active);
    }
}
