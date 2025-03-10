<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Product;

class WishlistController extends Controller
{

    public function getUserWishlist(Request $request)
    {
         // Get token from the request header
         $authHeader = $request->header('Authorization');
    
         if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
             return response()->json([
                 "status" => 400,
                 "error" => "Bearer token is required."
             ], 400);
         }
     
         $token = substr($authHeader, 7); // Extract token
     
         // Find user by token
         $user = User::where('token', $token)->first();
     
         if (!$user) {
             return response()->json([
                 "status" => 401,
                 "error" => "Your account is logged in on another device."
             ], 200); // Returning 200 but setting status 401 internally
         }

        $wishlist = $user->wishlist ?? [];
        $products = Product::whereIn('id', $wishlist)->get();

        return response()->json([
            "status" => 200,
            "message" => "Wishlist fetched successfully.",
            "wishlist" => $products
        ], 200);
    }

    
    public function addToWishlist(Request $request)
    {
        // Get token from the request header
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                "status" => 400,
                "error" => "Bearer token is required."
            ], 400);
        }

        $token = substr($authHeader, 7); // Extract token

        // Find user by token
        $user = User::where('token', $token)->first();

        if (!$user) {
            return response()->json([
                "status" => 401,
                "error" => "Your account is logged in on another device."
            ], 200); // Returning 200 but setting status 401 internally
        }

        $productId = $request->product_id;
        $product = Product::find($productId);

        if (!$product) {
            return response()->json(["status" => 404, "error" => "Product not found"], 404);
        }

        $wishlist = $user->wishlist ?? [];
        if (!in_array($productId, $wishlist)) {
            $wishlist[] = $productId;
        }

        $user->update(['wishlist' => $wishlist]);

        return response()->json([
            "status" => 200,
            "message" => "Product added to wishlist successfully.",
        ], 200);
    }

    public function removeFromWishlist(Request $request)
    {
        // Get token from the request header
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                "status" => 400,
                "error" => "Bearer token is required."
            ], 400);
        }

        $token = substr($authHeader, 7); // Extract token

        // Find user by token
        $user = User::where('token', $token)->first();
        $wishLikedProduct = Product::where('wishlist_liked', $request->wishlist_liked)->first();

        if (!$user) {
            return response()->json([
                "status" => 401,
                "error" => "Your account is logged in on another device."
            ], 200); // Returning 200 but setting status 401 internally
        }

        $productId = $request->product_id;
        $wishlist = $user->wishlist ?? [];

        if (($key = array_search($productId, $wishlist)) !== false) {
            unset($wishlist[$key]);
        }

        $user->update(['wishlist' => array_values($wishlist)]);

        return response()->json([
            "status" => 200,
            "message" => "Product removed from wishlist successfully.",
        ], 200);
    }
}
