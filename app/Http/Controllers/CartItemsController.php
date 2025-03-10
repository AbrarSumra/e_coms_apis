<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Product;
use App\Models\CartItem;

class CartItemsController extends Controller
{
    /// Get Cart Function
    public function getCart(Request $request)
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

        $cartItems = CartItem::where('user_id', $user->id)->get();

        if ($cartItems->isEmpty()) {
            return response()->json(["status" => 200, "message" => "Cart is empty", "data" => []], 200);
        }

        $formattedCart = $cartItems->map(function ($cartItem) {
            return [
                "item_id" => $cartItem->id,
                "product_id" => $cartItem->product_id,
                "quantity" => $cartItem->quantity,
                "product_name" => $cartItem->product_name,
                "product_price" => $cartItem->product_price,
                "total_price" => (int)$cartItem->total_price,
                "product_img" => $cartItem->product->image_url ?? null,
            ];
        });

        return response()->json([
            "status" => 200,
            "message" => "Cart items fetched successfully",
            "data" => $formattedCart
        ], 200);
    }

    /// Add to Cart Function
    public function addToCart(Request $request)
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
        $quantity = $request->quantity ?? 1; // Default to 1
        $product = Product::find($productId);
    
        if (!$product) {
            return response()->json(["status" => 404, "error" => "Product not found."], 404);
        }
    
        // Check if product is already in cart
        $cartItem = CartItem::where('user_id', $user->id)->where('product_id', $productId)->first();
    
        if ($cartItem) {
            // Update quantity and total price
            $cartItem->quantity += $quantity;
            $cartItem->total_price = $cartItem->quantity * $product->price;
            $cartItem->save();
        } else {
            // Add new item to cart
            $cartItem = CartItem::create([
                'user_id' => $user->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'product_name' => $product->name,
                'product_price' => $product->price,
                'total_price' => $quantity * $product->price
            ]);
        }
    
        return response()->json([
            "status" => 200,
            "message" => "Item added to cart successfully",
            "data" => [
                "item_id" => $cartItem->id,
                "product_id" => (int)$cartItem->product_id,
                "quantity" => (int)$cartItem->quantity,
                "product_name" => $cartItem->product_name,
                "product_price" => $cartItem->product_price,
                "total_price" => (int)$cartItem->total_price,
                "product_img" => $product->image_url
            ]
        ], 200);
    }

    /// Update Cart Item Function
    public function updateCartItem(Request $request){
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
   
       $item_id = $request->item_id;
       $cartItem = CartItem::where('user_id', $user->id)->where('id', $item_id)->first();

       if (!$cartItem) {
        return response()->json(["status" => 404, "error" => "Item not found in cart"], 404);
        }

        $cartItem->update([
            "quantity" => $request->quantity
        ]);

        return response()->json([
                "status" => 200,
                "message" => "Item updated successfully"
            ], 200);
    }
    
    /// Remove Cart Item Function
    public function removeFromCart(Request $request)
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
    
        $item_id = $request->item_id;
        $cartItem = CartItem::where('user_id', $user->id)->where('id', $item_id)->first();
    
        if (!$cartItem) {
            return response()->json(["status" => 404, "error" => "Item not found in cart"], 404);
        }
    
        $cartItem->delete();
    
        return response()->json([
            "status" => 200,
            "message" => "Item removed from cart successfully"
        ], 200);
    }
    
}
