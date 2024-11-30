<?php

namespace App\Services;

use App\Events\ChangingInIngredientAmount;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    const MAX_RETRIES = 3;

    public function process($request) :array
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

            $order = $this->createOrder($total, $items);

            if (!$order) {
                throw new Exception('Failed to create order');
            }

            DB::commit();

            return [
                'status'   => Response::HTTP_CREATED,
                'data'     => [
                    'order'   => $order->order_number,
                    'message' => 'order created successfully',
                ],
            ];
        }catch (Exception $exception) {
            Log::error('Failed to create order ', [
                'reason'   => $exception->getMessage(),
                'payload'  => [
                    'products' => $request->products,
                ]
            ]);

            DB::rollBack();
        }

        return [
            'status'   => Response::HTTP_BAD_REQUEST,
            'data'     => [
                'error' => 'failed to create order'
            ],
        ];
    }

    public function processWithRetries($request) :array
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

                $order = $this->createOrder($total, $items);

                if (!$order) {
                    throw new Exception('Failed to create order');
                }

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

                // we can wait here some micro seconds
            }
        } while ($attempts <= self::MAX_RETRIES);

        Log::error('Failed to create order ', [
            'reason'   => $exception->getMessage(),
            'payload'  => [
                'products' => $request->products,
            ]
        ]);

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

            if ($ingredient->remaining_quantity < $amountToDeduct) {
                throw new Exception("Insufficient amount in stock of $ingredient->name.");
            }

            $ingredient->decrement('remaining_quantity', $amountToDeduct);
            $ingredient->increment('consumed_quantity', $amountToDeduct);

            $minLimitPercentage = min(100, max(0, config('esnaad.min_stock', 50))) / 100;

            if (!$ingredient->is_notified && ($ingredient->remaining_quantity < ($ingredient->stock_quantity * $minLimitPercentage))){
                ChangingInIngredientAmount::dispatch($ingredient);
            }

            return true;
        }catch (Exception $exception) {
            Log::error('Failed to deduct ingredient ', [
                'id'   => $ingredient->id,
                'name' => $ingredient->name,
                'reason' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function createOrder($total, $items) :Model|Order|null
    {
        if (!empty($items)) {
            $order = Order::query()->create([
                'customer_id'  => request()->user()->id,
                'order_number' => $this->generateOrderNumber(),
                'total_price'  => $total,
            ]);

            $this->saveOrderItems($order, $items);

            return $order;
        }

        return null;
    }

    private function generateOrderNumber() :int
    {
        $latestOrder = Order::query()->latest('id')->lockForUpdate()->first();

        return $latestOrder ? $latestOrder->order_number + 1 : config('esnaad.starting_order_number', 1000);
    }

    private function saveOrderItems($order, $items) :void
    {
        data_set($items, '*.order_id', $order->id);

        OrderItem::query()->insert($items);
    }
}
