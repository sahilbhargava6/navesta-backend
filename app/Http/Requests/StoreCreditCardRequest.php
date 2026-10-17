<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCreditCardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'bankName' => ['required', 'string', 'min:2', 'max:80'],
            'cardName' => ['required', 'string', 'min:2', 'max:120'],
            'lastFour' => ['nullable', 'string', 'size:4'],
            'creditLimitRupees' => ['required', 'numeric', 'gt:0', 'max:100000000', 'decimal:0,2'],
            'outstandingRupees' => ['nullable', 'numeric', 'min:0', 'max:100000000', 'decimal:0,2'],
            'aprPercent' => ['required', 'numeric', 'gt:0', 'max:100', 'decimal:0,2'],
            'minPaymentRupees' => ['nullable', 'numeric', 'min:0', 'max:100000000', 'decimal:0,2'],
            'dueDay' => ['nullable', 'integer', 'between:1,31'],
        ];
    }

    public function messages(): array
    {
        return [
            'bankName.required' => 'Bank name is required',
            'cardName.required' => 'Card name is required',
            'lastFour.size' => 'Last 4 digits must be 4 characters',
            'creditLimitRupees.gt' => 'Credit limit must be positive',
            'outstandingRupees.min' => 'Outstanding cannot be negative',
            'aprPercent.gt' => 'APR percentage must be positive',
        ];
    }
}
