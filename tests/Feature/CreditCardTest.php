<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditCardTest extends TestCase
{
    use RefreshDatabase;

    private function cardPayload(array $overrides = []): array
    {
        return [
            'bankName' => 'HDFC Bank',
            'cardName' => 'Millennia',
            'lastFour' => '4321',
            'creditLimitRupees' => 250000,
            'outstandingRupees' => 125000.5,
            'aprPercent' => 42,
            'minPaymentRupees' => 6250,
            'dueDay' => 15,
            ...$overrides,
        ];
    }

    public function test_create_and_list_cards_in_rupees(): void
    {
        $user = $this->createUser();
        $headers = $this->authHeaders($user);

        $this->postJson('/api/v1/credit-cards', $this->cardPayload(), $headers)
            ->assertCreated()
            ->assertJsonPath('message', 'Credit card added')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.outstandingRupees', 125000.5)
            ->assertJsonPath('data.aprPercent', 42);

        $this->assertDatabaseHas('credit_cards', [
            'user_id' => $user->id,
            'outstanding_paise' => 12500050,
            'apr_bps' => 4200,
        ]);

        $this->getJson('/api/v1/credit-cards', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.creditLimitRupees', 250000);
    }

    public function test_zero_min_payment_is_stored_as_null(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/v1/credit-cards', $this->cardPayload(['minPaymentRupees' => 0]), $this->authHeaders($user))
            ->assertCreated()
            ->assertJsonPath('data.minPaymentRupees', null);
    }

    public function test_partial_update(): void
    {
        $user = $this->createUser();
        $headers = $this->authHeaders($user);
        $id = $this->postJson('/api/v1/credit-cards', $this->cardPayload(), $headers)->json('data.id');

        $this->patchJson("/api/v1/credit-cards/{$id}", ['outstandingRupees' => 90000], $headers)
            ->assertOk()
            ->assertJsonPath('data.outstandingRupees', 90000)
            ->assertJsonPath('data.bankName', 'HDFC Bank');
    }

    public function test_delete_closes_the_card(): void
    {
        $user = $this->createUser();
        $headers = $this->authHeaders($user);
        $id = $this->postJson('/api/v1/credit-cards', $this->cardPayload(), $headers)->json('data.id');

        $this->deleteJson("/api/v1/credit-cards/{$id}", [], $headers)
            ->assertOk()
            ->assertJsonPath('data', ['id' => $id, 'status' => 'closed']);

        $this->getJson('/api/v1/credit-cards', $headers)->assertJsonCount(0, 'data');
    }

    public function test_users_cannot_see_other_users_cards(): void
    {
        $owner = $this->createUser();
        $id = $this->postJson('/api/v1/credit-cards', $this->cardPayload(), $this->authHeaders($owner))->json('data.id');

        $this->app['auth']->forgetGuards();
        $intruder = $this->createUser();

        $this->getJson("/api/v1/credit-cards/{$id}", $this->authHeaders($intruder))
            ->assertNotFound()
            ->assertExactJson(['success' => false, 'error' => 'Credit card not found']);
    }

    public function test_non_uuid_ids_return_404(): void
    {
        $user = $this->createUser();

        $this->getJson('/api/v1/credit-cards/not-a-uuid', $this->authHeaders($user))->assertNotFound();
    }
}
