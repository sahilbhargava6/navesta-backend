<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InquiryController extends Controller
{
    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $validated = $request->validate([
            // Callback leads send only a phone number; everything else needs a
            // way to reply, so at least one of email / phone is required.
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:120'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'inquiryType' => ['nullable', 'string', 'max:80'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $inquiry = Inquiry::create([
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'inquiry_type' => $validated['inquiryType'] ?? 'General Inquiry',
            'message' => $validated['message'],
            'status' => 'pending',
            'ip_address' => $request->ip(),
        ]);

        $audit->record('inquiry.submitted', $inquiry, ['email' => $inquiry->email, 'type' => $inquiry->inquiry_type]);

        return $this->ok(
            ['id' => $inquiry->id],
            'Your message has been received. Our advisory team will contact you within 24 business hours.',
            201
        );
    }
}
