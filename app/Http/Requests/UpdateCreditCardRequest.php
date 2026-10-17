<?php

namespace App\Http\Requests;

class UpdateCreditCardRequest extends StoreCreditCardRequest
{
    /**
     * Same rules as create, but every field is optional.
     */
    public function rules(): array
    {
        return array_map(
            fn (array $rules) => ['sometimes', ...array_values(array_filter(
                $rules,
                fn ($rule) => ! is_string($rule) || ! in_array($rule, ['required', 'nullable'], true),
            ))],
            parent::rules(),
        );
    }
}
