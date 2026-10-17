<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    private function loanPayload(array $overrides = []): array
    {
        return [
            'lenderName' => 'Bajaj Finserv',
            'loanType' => 'personal',
            'principalRupees' => 500000,
            'outstandingRupees' => 380000,
            'interestRatePercent' => 14.5,
            'emiRupees' => 17250,
            'tenureMonths' => 36,
            'tenureRemainingMonths' => 24,
            'nextDueDate' => '2026-11-10',
            ...$overrides,
        ];
    }

    public function test_create_show_and_list(): void
    {
        $user = $this->createUser();
        $headers = $this->authHeaders($user);

        $id = $this->postJson('/api/v1/loans', $this->loanPayload(), $headers)
            ->assertCreated()
            ->assertJsonPath('data.interestRatePercent', 14.5)
            ->assertJsonPath('data.nextDueDate', '2026-11-10')
            ->json('data.id');

        $this->getJson("/api/v1/loans/{$id}", $headers)->assertOk()->assertJsonPath('data.emiRupees', 17250);
        $this->getJson('/api/v1/loans', $headers)->assertJsonCount(1, 'data');
    }

    public function test_analysis_compares_against_benchmark_rate(): void
    {
        $user = $this->createUser();
        $headers = $this->authHeaders($user);
        $id = $this->postJson('/api/v1/loans', $this->loanPayload(), $headers)->json('data.id');

        $data = $this->getJson("/api/v1/loans/{$id}/analysis", $headers)->assertOk()->json('data');

        $this->assertSame(8.5, $data['benchmarkRatePercent']);
        $this->assertSame(34000, $data['totalRemainingInterestRupees']); // 17250 x 24 - 380000
        $this->assertSame(17273, $data['newEmiRupees']);
        $this->assertSame(0, $data['monthlySavingsRupees']);
    }

    public function test_delete_closes_the_loan(): void
    {
        $user = $this->createUser();
        $headers = $this->authHeaders($user);
        $id = $this->postJson('/api/v1/loans', $this->loanPayload(), $headers)->json('data.id');

        $this->deleteJson("/api/v1/loans/{$id}", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->getJson('/api/v1/loans', $headers)->assertJsonCount(0, 'data');
    }

    public function test_update_can_clear_next_due_date(): void
    {
        $user = $this->createUser();
        $headers = $this->authHeaders($user);
        $id = $this->postJson('/api/v1/loans', $this->loanPayload(), $headers)->json('data.id');

        $this->patchJson("/api/v1/loans/{$id}", ['nextDueDate' => null, 'tenureRemainingMonths' => 23], $headers)
            ->assertOk()
            ->assertJsonPath('data.nextDueDate', null)
            ->assertJsonPath('data.tenureRemainingMonths', 23);
    }

    public function test_amortization_schedule(): void
    {
        $user = $this->createUser();
        $headers = $this->authHeaders($user);
        $id = $this->postJson('/api/v1/loans', $this->loanPayload(), $headers)->json('data.id');

        $this->getJson("/api/v1/solutions/amortization/{$id}", $headers)
            ->assertOk()
            ->assertJsonPath('data.emiRupees', 17210)
            ->assertJsonCount(36, 'data.schedule');
    }
}
