<?php

namespace App\Http\Resources;

use App\Models\CreditCard;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CreditCard */
class CreditCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bankName' => $this->bank_name,
            'cardName' => $this->card_name,
            'lastFour' => $this->last_four,
            'creditLimitRupees' => Money::paiseToRupees($this->credit_limit_paise),
            'outstandingRupees' => Money::paiseToRupees($this->outstanding_paise),
            'aprPercent' => Money::bpsToPercent($this->apr_bps),
            'minPaymentRupees' => $this->min_payment_paise ? Money::paiseToRupees($this->min_payment_paise) : null,
            'dueDay' => $this->due_day,
            'status' => $this->status,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
