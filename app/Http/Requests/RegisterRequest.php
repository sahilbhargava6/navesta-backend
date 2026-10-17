<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:200'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ]{10,15}$/'],
            'password' => ['required', 'string', 'max:128', Password::defaults()],
            'propertyStatus' => ['nullable', 'in:owned,rented'],
            'employmentSector' => ['nullable', 'in:govt,pvt,self'],
            // Consent must be an explicit choice (DPDP Act); reminders are opt-in
            'acceptTerms' => ['accepted'],
            'acceptReminders' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Name must be at least 2 characters',
            'email.email' => 'Invalid email address',
            'phone.regex' => 'Enter a valid mobile number',
            'acceptTerms.accepted' => 'Please accept the terms and privacy policy to continue',
        ];
    }
}
