<?php

namespace App\Http\Controllers\API\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController
{
    public function login(Request $request): JsonResponse
    {
        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();
            return response()->json([
                'success' => true,
                'token'   => $user->createToken('api-app')->plainTextToken,
            ]);
        }

        return response()->json([
            'success' => false,
            'error'   => 'Invalid credentials',
        ], 400);
    }
}
