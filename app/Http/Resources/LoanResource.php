<?php

namespace App\Http\Resources;

use App\Models\Loan;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Loan */
class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lenderName' => $this->lender_name,
            'loanType' => $this->loan_type,
            'principalRupees' => Money::paiseToRupees($this->principal_paise),
            'outstandingRupees' => Money::paiseToRupees($this->outstanding_paise),
            'interestRatePercent' => Money::bpsToPercent($this->interest_rate_bps),
            'emiRupees' => Money::paiseToRupees($this->emi_paise),
            'tenureMonths' => $this->tenure_months,
            'tenureRemainingMonths' => $this->tenure_remaining_months,
            'nextDueDate' => $this->next_due_date?->format('Y-m-d'),
            'status' => $this->status,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
