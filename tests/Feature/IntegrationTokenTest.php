<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Perusahaan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IntegrationTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tako-perusahaan')->table('customers_statuses')->delete();
        DB::connection('tako-customer')->table('customer_attaches')->delete();
        DB::connection('tako-customer')->table('customers')->delete();
        DB::connection('tako-perusahaan')->table('users')->delete();
        DB::connection('tako-perusahaan')->table('perusahaan')->delete();
    }

    public function test_can_retrieve_integration_token_with_valid_credentials()
    {
        // 1. Create a user
        $user = User::factory()->create([
            'email' => 'integration@tako.com',
            'password' => Hash::make('secret123'),
        ]);

        // 2. Make post request to endpoint
        $response = $this->postJson('/api/integration/token', [
            'email' => 'integration@tako.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token_type',
                'access_token',
                'expires_at',
            ]);
    }

    public function test_can_retrieve_integration_token_with_seeded_user()
    {
        // 1. Force insert company ID 1 using raw SQL
        DB::connection('tako-perusahaan')->insert(
            "INSERT INTO perusahaan (id, nama_perusahaan, uid, created_at, updated_at) VALUES (1, 'PT Tako', 'TK-123456', now(), now())"
        );

        // 2. Run seeder
        $this->seed(\Database\Seeders\UserSeeder::class);

        // 3. Make post request to endpoint
        $response = $this->postJson('/api/integration/token', [
            'email' => 'marketing@gmail.com',
            'password' => 'CR_tako@2025',
        ]);

        $response->assertStatus(200);
    }

    public function test_integration_token_is_permanent_and_does_not_change()
    {
        // 1. Create a user
        $user = User::factory()->create([
            'email' => 'persistent-integration@tako.com',
            'password' => Hash::make('secret123'),
        ]);

        // 2. Request token for the first time
        $response1 = $this->postJson('/api/integration/token', [
            'email' => 'persistent-integration@tako.com',
            'password' => 'secret123',
        ]);

        $response1->assertStatus(200)
            ->assertJsonStructure([
                'token_type',
                'access_token',
                'expires_at',
            ]);

        $token1 = $response1->json('access_token');
        $expiresAt1 = $response1->json('expires_at');

        // Verify expires_at is null (permanent)
        $this->assertNull($expiresAt1);

        // 3. Request token for the second time with the same credentials
        $response2 = $this->postJson('/api/integration/token', [
            'email' => 'persistent-integration@tako.com',
            'password' => 'secret123',
        ]);

        $response2->assertStatus(200);

        $token2 = $response2->json('access_token');
        $expiresAt2 = $response2->json('expires_at');

        // Verify they are the exact same token and also null expiration
        $this->assertEquals($token1, $token2);
        $this->assertNull($expiresAt2);

        // 4. Verify the token actually works for authenticating requests
        \App\Models\Permission::firstOrCreate(['name' => 'customer.create']);
        $user->givePermissionTo('customer.create');

        $response3 = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/customer/receive', []);

        // It should pass authentication and fail at validation (status 422) instead of 401
        $response3->assertStatus(422);
    }
}
