<?php

namespace App\Http\Resources;

use App\Models\BtApplication;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BtApplication */
class BtApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lenderName' => $this->lender->name,
            'outstandingRupees' => Money::paiseToRupees($this->outstanding_paise),
            'currentRatePercent' => Money::bpsToPercent($this->current_rate_bps),
            'newRatePercent' => $this->new_rate_bps ? Money::bpsToPercent($this->new_rate_bps) : null,
            'requestedTenureMonths' => $this->requested_tenure_months,
            'status' => $this->status,
            'createdAt' => $this->created_at,
        ];
    }
}
