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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'inquiryType' => ['nullable', 'string', 'max:80'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $inquiry = Inquiry::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
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
