<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiConventionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_its_checks(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks', ['database' => 'ok', 'cache' => 'ok']);
    }

    public function test_lists_are_paginated(): void
    {
        $user = $this->createUser();
        foreach (range(1, 3) as $i) {
            $user->creditCards()->create(['bank_name' => "Bank {$i}", 'card_name' => 'Card', 'credit_limit_paise' => 100000, 'outstanding_paise' => 0, 'apr_bps' => 3600]);
        }

        $this->getJson('/api/v1/credit-cards?perPage=2&page=2', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta', ['currentPage' => 2, 'perPage' => 2, 'total' => 3, 'lastPage' => 2]);

        $this->getJson('/api/v1/credit-cards?perPage=500', $this->authHeaders($user))->assertStatus(400);
    }

    public function test_amounts_accept_at_most_two_decimals(): void
    {
        $headers = $this->authHeaders($this->createUser());
        $card = ['bankName' => 'HDFC Bank', 'cardName' => 'Millennia', 'creditLimitRupees' => 100000, 'aprPercent' => 42];

        $this->postJson('/api/v1/credit-cards', [...$card, 'outstandingRupees' => 1000.555], $headers)->assertStatus(400);
        $this->postJson('/api/v1/credit-cards', [...$card, 'outstandingRupees' => 1000.55], $headers)->assertCreated();
    }

    public function test_loan_type_and_cross_field_rules(): void
    {
        $headers = $this->authHeaders($this->createUser());
        $loan = ['lenderName' => 'Tata Capital', 'loanType' => 'personal', 'principalRupees' => 500000, 'outstandingRupees' => 400000, 'interestRatePercent' => 12, 'emiRupees' => 15000, 'tenureMonths' => 36, 'tenureRemainingMonths' => 30];

        $this->postJson('/api/v1/loans', [...$loan, 'loanType' => 'gold'], $headers)->assertStatus(400);
        $this->postJson('/api/v1/loans', [...$loan, 'outstandingRupees' => 600000], $headers)->assertStatus(400);
        $this->postJson('/api/v1/loans', [...$loan, 'tenureRemainingMonths' => 40], $headers)->assertStatus(400);

        $id = $this->postJson('/api/v1/loans', $loan, $headers)->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/loans/{$id}", ['outstandingRupees' => 450000], $headers)->assertOk();
        $this->patchJson("/api/v1/loans/{$id}", ['outstandingRupees' => 550000], $headers)
            ->assertStatus(400)
            ->assertJsonPath('error', 'Outstanding amount cannot exceed the principal');
    }

    public function test_api_docs_are_admin_only_outside_local(): void
    {
        $this->assertFalse(app()->isLocal());

        $this->get('/docs/api')->assertForbidden();
        $this->actingAs($this->createAdmin())->get('/docs/api')->assertOk();
    }
}
