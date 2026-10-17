<?php

namespace App\Models;

use App\Enums\LenderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast attributes (column types come from the migrations).
 *
 * @property LenderType $type
 * @property bool $is_active
 */
class Lender extends Model
{
    use HasUuids;

    const CREATED_AT = null;

    protected $fillable = [
        'name',
        'type',
        'initials',
        'brand_color',
        'min_rate_bps',
        'max_rate_bps',
        'max_loan_amount_paise',
        'processing_fee_bps',
        'min_credit_score',
        'disbursal_days_min',
        'disbursal_days_max',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
        'min_credit_score' => 650,
    ];

    protected function casts(): array
    {
        return [
            'min_rate_bps' => 'integer',
            'max_rate_bps' => 'integer',
            'max_loan_amount_paise' => 'integer',
            'processing_fee_bps' => 'integer',
            'min_credit_score' => 'integer',
            'type' => LenderType::class,
            'disbursal_days_min' => 'integer',
            'disbursal_days_max' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
