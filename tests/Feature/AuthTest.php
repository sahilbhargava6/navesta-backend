<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_token_and_user(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Asha Rao',
            'email' => 'Asha@Example.com',
            'password' => 'secret123',
            'propertyStatus' => 'rented',
            'employmentSector' => 'govt',
            'acceptTerms' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'asha@example.com')
            ->assertJsonPath('data.user.propertyStatus', 'rented')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'createdAt']]]);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $this->createUser(['email' => 'asha@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Asha Rao',
            'email' => 'asha@example.com',
            'password' => 'secret123',
            'acceptTerms' => true,
        ])->assertStatus(400)->assertExactJson([
            'success' => false,
            'error' => 'User already exists with this email address',
        ]);
    }

    public function test_validation_errors_use_the_api_error_shape(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'x@example.com', 'password' => 'secret123', 'acceptTerms' => true])
            ->assertStatus(400)
            ->assertExactJson(['success' => false, 'error' => 'Name must be at least 2 characters']);
    }

    public function test_login_with_valid_credentials(): void
    {
        $this->createUser(['email' => 'asha@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'ASHA@example.com', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('message', 'Logged in successfully')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email']]]);
    }

    public function test_login_accepts_password_hashes_created_by_the_node_backend(): void
    {
        // bcryptjs writes $2b$ hashes; the algorithm is identical to PHP's $2y$
        $nodeHash = '$2b$'.substr(password_hash('password123', PASSWORD_BCRYPT, ['cost' => 4]), 4);
        $this->createUser(['email' => 'legacy@example.com', 'password_hash' => $nodeHash]);

        $this->postJson('/api/v1/auth/login', ['email' => 'legacy@example.com', 'password' => 'password123'])
            ->assertOk();
    }

    public function test_login_with_wrong_password(): void
    {
        $this->createUser(['email' => 'asha@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'asha@example.com', 'password' => 'wrong'])
            ->assertStatus(401)
            ->assertExactJson(['success' => false, 'error' => 'Invalid email or password']);
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/v1/users/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = $this->createUser();
        $headers = $this->authHeaders($user);

        $this->postJson('/api/v1/auth/logout', [], $headers)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/users/me', $headers)->assertStatus(401);
    }

    public function test_expired_tokens_are_rejected(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('test', ['*'], now()->subMinute())->plainTextToken;

        $this->getJson('/api/v1/users/me', ['Authorization' => "Bearer {$token}"])->assertStatus(401);
    }

    public function test_profile_includes_latest_credit_score(): void
    {
        $user = $this->createUser();
        $user->creditScores()->create(['score' => 690, 'fetched_at' => now()->subMonth()]);
        $user->creditScores()->create(['score' => 755, 'source' => 'cibil_api', 'fetched_at' => now()]);

        $this->getJson('/api/v1/users/me', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.creditScore', 755)
            ->assertJsonCount(1, 'data.creditScores');
    }

    public function test_profile_update_ignores_empty_values(): void
    {
        $user = $this->createUser(['name' => 'Asha Rao']);

        $this->patchJson('/api/v1/users/me', ['phone' => '+919811111111', 'name' => ''], $this->authHeaders($user))
            ->assertStatus(400); // name present but empty fails validation, like the original

        $this->patchJson('/api/v1/users/me', ['phone' => '+919811111111'], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.phone', '+919811111111')
            ->assertJsonPath('data.name', 'Asha Rao');

        $this->assertSame('+919811111111', User::find($user->id)->phone);
    }
}
