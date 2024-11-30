<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderTest extends TestCase
{
   // use DatabaseTransactions;
    const API_URL = 'http://127.0.0.1:8000/api/v1/';
    /**
     * A basic test example.
     */
    public function test_creating_order_require_auth_user(): void
    {
        $response = $this->post(self::API_URL . 'orders', [
            'products' => [
                [
                    'product_id' => 1,
                    'quantity'   => 2,
                ]
            ]
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Unauthenticated.');
    }
    public function test_use_can_login(): void
    {
        $customer = User::factory()->create();

        $response = $this->post(self::API_URL . 'auth/login', [
            'email'     => $customer->email,
            'password'  => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('user.id', $customer->id);
        $response->assertJsonPath('user.name', $customer->name);
        $this->assertNotEmpty($response->json('token'));
    }
    public function test_creating_order_with_invalid_product_payload(): void
    {
        $customer = User::factory()->create([
            'name'  => 'John Doe',
            'email' => 'john-'.time().'@doe.com',
            'role'  => Role::Customer
        ]);

        $response = $this->actingAs($customer)->post(self::API_URL . 'orders', [
            'products' => [
                [
                    'product_id' => 0,
                    'quantity'   => 1,
                ]
            ]
        ]);
        $response->assertStatus(422);
        $response->assertJsonPath('message', 'The selected products.0.product_id is invalid.');

        $response = $this->actingAs($customer)->post(self::API_URL . 'orders', [
            'products' => [
                [
                    'product_id' => 1,
                    'quantity'   => 0,
                ]
            ]
        ]);
        $response->assertStatus(422);
        $response->assertJsonPath('message', 'The products.0.quantity field must be at least 1.');


        $response = $this->actingAs($customer)->post(self::API_URL . 'orders', [
            'products' => [
                [
                    'product_id' => 0,
                    'quantity'   => 1,
                ],
                [
                    'product_id' => 3,
                    'quantity'   => 1,
                ]
            ]
        ]);
        $response->assertStatus(422);
        $response->assertJsonPath('message', 'The selected products.0.product_id is invalid. (and 1 more error)');
    }
    public function test_creating_order_with_valid_product_payload_and_sufficient_quantities(): void
    {
        $customer = User::factory()->create([
            'name'  => 'John Doe',
            'email' => 'john-'.time().'@doe.com',
            'role'  => Role::Customer
        ]);

        $response = $this->actingAs($customer)->post(self::API_URL . 'orders', [
            'products' => [
                [
                    'product_id' => 1,
                    'quantity'   => 2,
                ]
            ]
        ]);

        $latestOrder = Order::latest()->first();

        $response->assertStatus(201);
        $response->assertJsonPath('order', (int) $latestOrder->order_number);
        $response->assertJsonPath('message', 'order created successfully');
    }

    public function test_creating_order_with_sufficient_quantities_and_deducting_ingredients(): void
    {
        $token = sprintf('Bearer 38|YehsiPUfevM3nd83bG2wpqvAJVfx7VtrGvvW0jlF2a7a586d');

        $payload = ['products' => [ 'product_id' => 1, 'quantity'   => 5]];

        $responses = Http::pool(function ($pool) use ($token, $payload) {
           $pool->withHeaders(['Content-Type' => 'application/json', 'Authorization' => $token])->post(self::API_URL . 'orders', $payload);
           $pool->withHeaders(['Content-Type' => 'application/json', 'Authorization' => $token])->post(self::API_URL . 'orders', $payload);
           $pool->withHeaders(['Content-Type' => 'application/json', 'Authorization' => $token])->post(self::API_URL . 'orders', $payload);
           $pool->withHeaders(['Content-Type' => 'application/json', 'Authorization' => $token])->post(self::API_URL . 'orders', $payload);
           $pool->withHeaders(['Content-Type' => 'application/json', 'Authorization' => $token])->post(self::API_URL . 'orders', $payload);
           $pool->withHeaders(['Content-Type' => 'application/json', 'Authorization' => $token])->post(self::API_URL . 'orders', $payload);
           $pool->withHeaders(['Content-Type' => 'application/json', 'Authorization' => $token])->post(self::API_URL . 'orders', $payload);
           $pool->withHeaders(['Content-Type' => 'application/json', 'Authorization' => $token])->post(self::API_URL . 'orders', $payload);
           $pool->withHeaders(['Content-Type' => 'application/json', 'Authorization' => $token])->post(self::API_URL . 'orders', $payload);
           $pool->withHeaders(['Content-Type' => 'application/json', 'Authorization' => $token])->post(self::API_URL . 'orders', $payload);
        });

        $statuses = [];
        foreach ($responses as $response){
            $statuses[] = $response->json();
        }

        dd($statuses);
        $response = $this->actingAs($customer)->post(self::API_URL . 'orders', [
            'products' => [
                [
                    'product_id' => 1,
                    'quantity'   => 5,
                ]
            ]
        ]);


        $latestOrder = Order::latest()->first();

        $response->assertStatus(201);
        $response->assertJsonPath('order', (int) $latestOrder->order_number);
        $response->assertJsonPath('message', 'order created successfully');
    }

}
