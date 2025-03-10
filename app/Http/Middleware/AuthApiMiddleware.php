<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class AuthApiMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                "status" => 400,
                "error" => "Bearer token is required."
            ], 400);
        }

        $token = substr($authHeader, 7); 

        if (!User::where('token', $token)->exists()) {
            return response()->json([
                "status" => 401,
                "error" => "Your account is logged in on another device."
            ], 200);
        }

        return $next($request);
    }
}
