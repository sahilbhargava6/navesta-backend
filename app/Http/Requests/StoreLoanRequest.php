<?php

namespace App\Http\Requests;

use App\Enums\LoanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lenderName' => ['required', 'string', 'min:2', 'max:100'],
            'loanType' => ['required', Rule::enum(LoanType::class)],
            'principalRupees' => ['required', 'numeric', 'gt:0', 'max:1000000000', 'decimal:0,2'],
            'outstandingRupees' => ['required', 'numeric', 'gt:0', 'max:1000000000', 'decimal:0,2', 'lte:principalRupees'],
            'interestRatePercent' => ['required', 'numeric', 'gt:0', 'max:100', 'decimal:0,2'],
            'emiRupees' => ['required', 'numeric', 'gt:0', 'max:1000000000', 'decimal:0,2'],
            'tenureMonths' => ['required', 'integer', 'min:1', 'max:600'],
            'tenureRemainingMonths' => ['required', 'integer', 'min:0', 'max:600', 'lte:tenureMonths'],
            'nextDueDate' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'lenderName.required' => 'Lender name is required',
            'principalRupees.gt' => 'Principal amount must be positive',
            'outstandingRupees.gt' => 'Outstanding amount must be positive',
            'interestRatePercent.gt' => 'Interest rate percentage must be positive',
            'emiRupees.gt' => 'EMI amount must be positive',
            'tenureMonths.min' => 'Tenure months must be positive',
            'tenureRemainingMonths.min' => 'Tenure remaining cannot be negative',
        ];
    }
}
