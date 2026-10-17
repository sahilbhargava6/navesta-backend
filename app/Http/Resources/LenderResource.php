<?php

namespace App\Http\Resources;

use App\Models\Lender;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lender */
class LenderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'initials' => $this->initials,
            'brandColor' => $this->brand_color,
            'minRatePercent' => Money::bpsToPercent($this->min_rate_bps),
            'maxRatePercent' => Money::bpsToPercent($this->max_rate_bps),
            'maxLoanAmountRupees' => Money::paiseToRupees($this->max_loan_amount_paise),
            'processingFeePercent' => Money::bpsToPercent($this->processing_fee_bps),
            'minCreditScore' => $this->min_credit_score,
            'disbursalDaysMin' => $this->disbursal_days_min,
            'disbursalDaysMax' => $this->disbursal_days_max,
            'isActive' => $this->is_active,
            'updatedAt' => $this->updated_at,
        ];
    }
}
