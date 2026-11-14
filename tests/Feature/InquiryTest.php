<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contact form and the landing page's "Apply for a Personal Loan" callback.
 */
class InquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_with_name_and_email_is_saved(): void
    {
        $this->postJson('/api/v1/inquiries', [
            'name' => 'Asha Rao',
            'email' => 'asha@example.com',
            'inquiryType' => 'General Inquiry',
            'message' => 'Please help me plan my repayments.',
        ])->assertCreated();

        $this->assertDatabaseHas('inquiries', ['email' => 'asha@example.com', 'name' => 'Asha Rao']);
    }

    public function test_phone_only_callback_lead_is_saved_without_placeholder_contact_details(): void
    {
        $this->postJson('/api/v1/inquiries', [
            'phone' => '+919876543210',
            'inquiryType' => 'Personal Loan Callback',
            'message' => 'Please call back on +91 9876543210.',
        ])->assertCreated();

        $inquiry = Inquiry::firstOrFail();
        $this->assertSame('+919876543210', $inquiry->phone);
        $this->assertNull($inquiry->email);
        $this->assertNull($inquiry->name);
    }

    public function test_an_inquiry_needs_an_email_or_a_phone(): void
    {
        $this->postJson('/api/v1/inquiries', [
            'name' => 'No Contact',
            'message' => 'How do I reach you?',
        ])->assertStatus(400);

        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_a_malformed_phone_is_rejected(): void
    {
        $this->postJson('/api/v1/inquiries', [
            'phone' => 'call me maybe',
            'message' => 'Please call back.',
        ])->assertStatus(400);
    }
}
