<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendReminderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'channel' => ['nullable', 'in:whatsapp,sms'],
            'phone' => ['nullable', 'string', 'max:20'],
            'accountName' => ['required', 'string', 'max:150'],
            'amountRupees' => ['required', 'numeric', 'min:0', 'max:100000000', 'decimal:0,2'],
            'dueDate' => ['required', 'string', 'max:50'],
        ];
    }
}
