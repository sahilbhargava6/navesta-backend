<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pincode verification for address autofill: MapmyIndia (Mappls) first,
 * India Post as the backup, and no guessing when neither can answer.
 */
class PincodeTest extends TestCase
{
    private const MAPPLS = 'search.mappls.com/*';

    private const INDIA_POST = 'api.postalpincode.in/*';

    private function mapplsHit(string $pin = '110020'): array
    {
        return ['copResults' => [
            'houseNumber' => '', 'houseName' => '', 'poi' => '', 'street' => '', 'subSubLocality' => '', 'subLocality' => '',
            'locality' => 'Okhla Industrial Estate Phase 3', 'village' => '', 'subDistrict' => 'Kalkaji',
            'district' => 'South East Delhi District', 'city' => 'New Delhi', 'state' => 'Delhi', 'pincode' => $pin,
            'formattedAddress' => "Kalkaji, South East Delhi District, New Delhi, Delhi, {$pin}", 'eLoc' => 'X1', 'geocodeLevel' => 'pincode', 'confidenceScore' => 0.9,
        ]];
    }

    public function test_mappls_verifies_and_autofills(): void
    {
        config(['services.mappls.static_key' => 'test-key']);
        Http::fake([self::MAPPLS => Http::response($this->mapplsHit()), self::INDIA_POST => Http::response([], 500)]);

        $this->getJson('/api/v1/utilities/pincode/110020')
            ->assertOk()
            ->assertJsonPath('data.city', 'New Delhi')
            ->assertJsonPath('data.district', 'South East Delhi')
            ->assertJsonPath('data.state', 'Delhi')
            ->assertJsonPath('data.locality', 'Okhla Industrial Estate Phase 3')
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.source', 'mappls');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'search.mappls.com')
            && $r['address'] === '110020' && $r['podFilter'] === 'pincode' && $r['access_token'] === 'test-key');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'postalpincode'));
    }

    public function test_results_are_cached(): void
    {
        config(['services.mappls.static_key' => 'test-key']);
        Http::fake([self::MAPPLS => Http::response($this->mapplsHit())]);

        $this->getJson('/api/v1/utilities/pincode/110020')->assertOk();
        $this->getJson('/api/v1/utilities/pincode/110020')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_a_pincode_mappls_does_not_know_is_not_found(): void
    {
        config(['services.mappls.static_key' => 'test-key']);
        Http::fake([self::MAPPLS => Http::response(null, 204)]);

        $this->getJson('/api/v1/utilities/pincode/999999')->assertNotFound();
    }

    public function test_a_nearby_pincode_is_not_accepted_as_a_match(): void
    {
        config(['services.mappls.static_key' => 'test-key']);
        Http::fake([self::MAPPLS => Http::response($this->mapplsHit('110019'))]);

        $this->getJson('/api/v1/utilities/pincode/110020')->assertNotFound();
    }

    public function test_india_post_is_used_without_a_mappls_key(): void
    {
        config(['services.mappls.static_key' => null]);
        Http::fake([self::INDIA_POST => Http::response([[
            'Status' => 'Success',
            'PostOffice' => [['Name' => 'Koramangala', 'District' => 'Bangalore', 'State' => 'Karnataka', 'Block' => 'Bangalore South']],
        ]])]);

        $this->getJson('/api/v1/utilities/pincode/560034')
            ->assertOk()
            ->assertJsonPath('data.city', 'Bangalore South')
            ->assertJsonPath('data.state', 'Karnataka')
            ->assertJsonPath('data.source', 'india_post');
    }

    public function test_india_post_is_the_backup_when_mappls_refuses(): void
    {
        config(['services.mappls.static_key' => 'over-quota']);
        Http::fake([
            self::MAPPLS => Http::response(['error' => 'limit'], 403),
            self::INDIA_POST => Http::response([['Status' => 'Error', 'PostOffice' => null]]),
        ]);

        // India Post answers definitively: no such pincode
        $this->getJson('/api/v1/utilities/pincode/123456')->assertNotFound();
    }

    public function test_nothing_is_guessed_when_no_provider_answers(): void
    {
        config(['services.mappls.static_key' => 'test-key']);
        Http::fake([
            self::MAPPLS => Http::sequence()->push(null, 500)->push($this->mapplsHit('110045')),
            self::INDIA_POST => Http::response(null, 502),
        ]);

        $this->getJson('/api/v1/utilities/pincode/110045')->assertStatus(503);

        // The outage is not cached: the next request asks again
        $this->getJson('/api/v1/utilities/pincode/110045')->assertOk()->assertJsonPath('data.city', 'New Delhi');
    }

    public function test_malformed_pincodes_never_reach_a_provider(): void
    {
        Http::fake();

        $this->getJson('/api/v1/utilities/pincode/012345')->assertNotFound();
        $this->getJson('/api/v1/utilities/pincode/12345a')->assertNotFound();

        Http::assertNothingSent();
    }
}
