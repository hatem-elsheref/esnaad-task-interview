<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\OrderRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class OrderController
{
    public function __construct(private OrderService $orderService){}

    public function index(Response $request): JsonResponse
    {
        $resource = $this->orderService->myOrders($request);

        return response()->json($resource['data'], $resource['status']);
    }

    public function store(OrderRequest $request): JsonResponse
    {
        $resource = $this->orderService->process($request);

        return response()->json($resource['data'], $resource['status']);
    }
}
