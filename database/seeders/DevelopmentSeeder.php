<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Merchant', 'email' => 'merchant@esnaad.com', 'role' => Role::Merchant],
            ['name' => 'Customer', 'email' => 'customer@esnaad.com', 'role' => Role::Customer],
        ];

        foreach ($users as $user) {
            User::factory()->create([
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ]);
        }

        $ingredients = ['Beef' => 20, 'Cheese' => 5, 'Onion' => 1];
        $burgerIngredients = ['Beef' => 150, 'Cheese' => 30, 'Onion' => 20]; // as sequence of inserting

        $burgerIngredientsItems = [];
        foreach ($ingredients as $ingredient => $stock) {
            $item = Ingredient::query()->create([
                'name'           => $ingredient,
                'stock_quantity' => $stock * 1000
            ]);

            $burgerIngredientsItems[$item->id] = ['amount' => $burgerIngredients[$ingredient]];
        }

        $product = Product::query()->create([
            'name' => 'Burger',
            'price' => 200,
        ]);

        $product->ingredients()->attach($burgerIngredientsItems);
    }
}
