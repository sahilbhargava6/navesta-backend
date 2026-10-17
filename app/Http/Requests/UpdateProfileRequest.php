<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[0-9 ]{10,15}$/'],
            'propertyStatus' => ['sometimes', 'nullable', 'in:owned,rented'],
            'employmentSector' => ['sometimes', 'nullable', 'in:govt,pvt,self'],
            'remindersEnabled' => ['sometimes', 'boolean'],
            'twoFactorEnabled' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Enter a valid mobile number'];
    }
}
