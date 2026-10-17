<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBtApplicationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lenderId' => ['required', 'uuid'],
            'sourceLoanId' => ['nullable', 'uuid', 'required_without:sourceCcId'],
            'sourceCcId' => ['nullable', 'uuid'],
            'requestedTenureMonths' => ['required', 'integer', 'min:1', 'max:600'],
            'crifScore' => ['nullable', 'integer', 'between:300,900'],
        ];
    }

    public function messages(): array
    {
        return [
            'lenderId.uuid' => 'Invalid lender ID',
            'sourceLoanId.required_without' => 'Either sourceLoanId or sourceCcId must be provided',
        ];
    }
}
