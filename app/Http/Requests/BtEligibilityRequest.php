<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BtEligibilityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'outstandingRupees' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'currentRatePercent' => ['required', 'numeric', 'gt:0', 'max:100', 'decimal:0,2'],
            'propertyStatus' => ['nullable', 'in:owned,rented'],
            'employmentSector' => ['nullable', 'in:govt,pvt,self'],
            'crifScore' => ['nullable', 'integer', 'between:300,900'],
        ];
    }
}
