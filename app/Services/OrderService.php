<?php

namespace App\Services;

use App\Events\ChangingInIngredientAmount;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Exception;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    const MAX_RETRIES = 3;

    public function createOrderWithRetries($request) :array
    {
        $attempts = 1;

        do{
            try {

                $total = 0;
                $items = [];

                DB::beginTransaction();

                foreach ($request->products as $productArray) {
                    $product = Product::query()->with('ingredients')->find($productArray['product_id']);

                    $quantity = $productArray['quantity'];

                    $total += $product->price * $quantity;

                    foreach ($product->ingredients as $productIngredient) {

                        $updated = $this->deductIngredientAmount($productIngredient, $quantity);

                        if (!$updated) {
                            throw new Exception('Failed to update ingredient');
                        }
                    }

                    $items[] = [
                        'product_id' => $product->id,
                        'quantity'   => $quantity,
                        'price'      => $product->price * $quantity,
                    ];
                }

                $latestOrder = Order::query()->latest('id')->lockForUpdate()->first();

                $order = Order::query()->create([
                    'customer_id'  => $request->user()->id,
                    'order_number' => $latestOrder ? $latestOrder->order_number + 1 : 1000,
                    'total_price'  => $total,
                ]);

                data_set($items, '*.order_id', $order->id);

                OrderItem::query()->insert($items);

                DB::commit();

                return [
                    'status'   => Response::HTTP_CREATED,
                    'data'     => [
                        'order'   => $order->order_number,
                        'message' => 'order created successfully',
                    ],
                ];
            }catch (Exception $exception) {
                DB::rollBack();
                $attempts += 1;
            }
        } while ($attempts <= self::MAX_RETRIES);

        Log::error('Failed to create order ', $request->all());

        return [
            'status'   => Response::HTTP_BAD_REQUEST,
            'data'     => [
                'error' => 'failed to create order'
            ],
        ];
    }
    public function createOrder($request) :array
    {
        try {

            $total = 0;
            $items = [];

            DB::beginTransaction();

            foreach ($request->products as $productArray) {
                $product = Product::query()->with('ingredients')->find($productArray['product_id']);

                $quantity = $productArray['quantity'];

                $total += $product->price * $quantity;

                foreach ($product->ingredients as $productIngredient) {

                    $isUpdated = $this->deductIngredientAmount($productIngredient, $quantity);

                    if (!$isUpdated) {
                        throw new Exception('Failed to update ingredient according to dirty reads');
                    }
                }

                $items[] = [
                    'product_id' => $product->id,
                    'quantity'   => $quantity,
                    'price'      => $product->price * $quantity,
                ];
            }

            $latestOrder = Order::query()->latest('id')->lockForUpdate()->first();

            $order = Order::query()->create([
                //'customer_id'  => $request->user()->id,
                'customer_id'  => 1,
                'order_number' => $latestOrder ? $latestOrder->order_number + 1 : 1000,
                'total_price'  => $total,
            ]);

            data_set($items, '*.order_id', $order->id);

            OrderItem::query()->insert($items);

            DB::commit();

            return [
                'status'   => Response::HTTP_CREATED,
                'data'     => [
                    'order'   => $order->order_number,
                    'message' => 'order created successfully',
                ],
            ];
        }catch (Exception $exception) {
            Log::error('Failed to create order ', $request->all());

            DB::rollBack();
        }

        return [
            'status'   => Response::HTTP_BAD_REQUEST,
            'data'     => [
                'error' => 'failed to create order'
            ],
        ];
    }


    private function deductIngredientAmount($productIngredient, $quantity) :bool
    {
        try {
            $amountToDeduct = $productIngredient->pivot->amount * $quantity;

            $ingredient = Ingredient::query()->where('id', $productIngredient->id)->lockForUpdate()->first();

            if (!$ingredient) {
                throw new Exception("Ingredient $ingredient->name not found.");
            }

            $currentVersion = $ingredient->version;

            if ($ingredient->remaining_quantity < $amountToDeduct) {
                throw new Exception('Insufficient amount in stock.');
            }

            $ingredientState = Ingredient::query()->where('id', $ingredient->id)
                ->where('version', $currentVersion)
                ->update([
                    'consumed_quantity'  => $ingredient->consumed_quantity + $amountToDeduct,
                    'remaining_quantity' => $ingredient->stock_quantity - ($ingredient->consumed_quantity + $amountToDeduct),
                    'version'            => $currentVersion + 1,
                ]);

            if ($ingredientState === 0) {
                throw new Exception('Version conflict detected. Retrying...');
            }

            $ingredient = $ingredient->fresh();

            $minLimitPercentage = min(100, max(0, config('esnaad.min_stock', 50))) / 100;

            if (!$ingredient->is_notified && ($ingredient->remaining_quantity < ($ingredient->stock_quantity * $minLimitPercentage))){
                ChangingInIngredientAmount::dispatch($ingredient);
            }

        }catch (Exception $exception) {
            Log::error('Failed to deduct ingredient ', [
                'name' => $ingredient->name,
                'id'   => $ingredient->id,
                'reason' => $exception->getMessage(),
            ]);
            return false;
        }

        return true;
    }
}
