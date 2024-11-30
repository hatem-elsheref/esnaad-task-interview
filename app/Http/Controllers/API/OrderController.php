<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\OrderRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController
{
    public function __construct(private OrderService $orderService){}

    public function store(OrderRequest $request): JsonResponse
    {
        $resource = $this->orderService->createOrder($request);

        return response()->json($resource['data'], $resource['status']);
    }
}
