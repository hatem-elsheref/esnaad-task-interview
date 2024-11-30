<?php

namespace App\Http\Controllers\API;

use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController
{
    public function __construct(private OrderService $orderService){}

    public function index()
    {
        $resource = $this->orderService->myOrders();

        return response()->json($resource['data'], $resource['status']);
    }
    public function store(Request $request): JsonResponse
    {
        $resource = $this->orderService->createOrder($request);

        return response()->json($resource['data'], $resource['status']);
    }
}
