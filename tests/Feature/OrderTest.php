<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Enums\Role;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderTest extends TestCase
{
  // use DatabaseTransactions;
    const API_URL = '/api/v1/';
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
        $customer = User::query()->latest()->first();

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
                    'product_id' => 10000000000,
                    'quantity'   => 1,
                ]
            ]
        ]);
        $response->assertStatus(422);
        $response->assertJsonPath('message', 'The selected products.0.product_id is invalid. (and 1 more error)');
    }
    public function test_creating_order_with_valid_product_payload_and_sufficient_quantities(): void
    {
        $customer = User::query()->latest()->first();
        $merchant = User::query()->first();

        $product = Product::query()->create([
            'name' => 'Iphone 15 Pro Max',
            'price' => 60000
        ]);

        $ingredient = Ingredient::query()->updateOrCreate([
            'name'        => 'Adapter',
        ],[
            'merchant_id' => $merchant->id,
            'stock_quantity' => 500,
        ]);

        $product->ingredients()->attach([$ingredient->id => ['amount' => 1]]);

        $response = $this->actingAs($customer)->post(self::API_URL . 'orders', [
            'products' => [
                [
                    'product_id' => $product->id,
                    'quantity'   => 20,
                ]
            ]
        ]);

        $latestOrder = Order::latest('id')->first();

        $response->assertStatus(201);
        $response->assertJsonPath('order', (int) $latestOrder->order_number);
        $response->assertJsonPath('message', 'order created successfully');
    }

    public function test_creating_order_with_sufficient_quantities_and_deducting_ingredients(): void
    {
        $customer = User::query()->latest()->first();

        $product = Product::query()->with('ingredients')->first();
        $quantity = rand(1, 5);

        $expectedAmounts = [];
        foreach ($product->ingredients as $ingredient) {
            $expectedAmounts[$ingredient->id] = [
                'name'       =>  $ingredient->name,
                'total'      =>  $ingredient->stock_quantity,
                'remaining'  =>  $ingredient->remaining_quantity - ((float) $ingredient->pivot->amount * $quantity),
                'consumed'   =>  $ingredient->consumed_quantity + ((float) $ingredient->pivot->amount * $quantity),
            ];
        }

        $response = $this->actingAs($customer)->post(self::API_URL . 'orders', [
            'products' => [
                [
                    'product_id' => $product->id,
                    'quantity'   => $quantity,
                ]
            ]
        ]);

        $product = Product::query()->with('ingredients')->first();

        $actualAmounts = [];
        foreach ($product->ingredients as $ingredient) {
            $actualAmounts[$ingredient->id] = [
                'total'      =>  $ingredient->stock_quantity,
                'remaining'  =>  $ingredient->remaining_quantity,
                'consumed'   =>  $ingredient->consumed_quantity,
            ];
        }

        foreach ($actualAmounts as $ingredientId => $actualAmount) {
            $this->assertEquals($expectedAmounts[$ingredientId]['remaining'], $actualAmount['remaining'], $expectedAmounts[$ingredientId]['name']);
            $this->assertEquals($expectedAmounts[$ingredientId]['consumed'], $actualAmount['consumed'], $expectedAmounts[$ingredientId]['name']);
        }

        $latestOrder = Order::latest('id')->first();

        $response->assertStatus(201);
        $response->assertJsonPath('order', (int) $latestOrder->order_number);
        $response->assertJsonPath('message', 'order created successfully');
    }

}
