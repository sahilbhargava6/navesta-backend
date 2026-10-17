<?php

namespace App\Http\Requests;

class UpdateLoanRequest extends StoreLoanRequest
{
    /**
     * Same rules as create, but every field is optional (nextDueDate may be
     * cleared with null). Cross-field limits (outstanding <= principal,
     * remaining <= tenure) are checked against the merged loan instead,
     * since a partial update may send only one side.
     */
    public function rules(): array
    {
        return array_map(
            fn (array $rules) => ['sometimes', ...array_values(array_filter(
                $rules,
                fn ($rule) => ! is_string($rule) || ($rule !== 'required' && ! str_starts_with($rule, 'lte:')),
            ))],
            parent::rules(),
        );
    }
}
