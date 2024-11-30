<?php

namespace App\Services;

use Illuminate\Http\Response;

class OrderService
{
    public function myOrders()
    {

        return [
            'data'   => [

            ],
            'status' => Response::HTTP_CREATED
        ];
    }

    public function createOrder($request) :array
    {


        return [
            'data'   => [

            ],
            'status' => Response::HTTP_CREATED
        ];
    }
}
