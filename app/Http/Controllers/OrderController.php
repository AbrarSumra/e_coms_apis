<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

class OrderController extends Controller
{
    
    public function orderCreate(Request $request)
    {
        $authHeader = $request->header('Authorization');
    
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                "status" => 400,
                "error" => "Bearer token is required."
            ], 400);
        }
    
        $token = substr($authHeader, 7);
        $user = User::where('token', $token)->first();
    
        if (!$user) {
            return response()->json([
                "status" => 401,
                "error" => "Your account is logged in on another device."
            ], 200);
        }
    
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'mobile' => 'required|string|min:10|max:15',
            'address' => 'required|string',
            'payment_method' => 'required|string',
            'cart_items' => 'required|array|min:1',
            'cart_items.*.product_id' => 'required|integer|exists:products,id',
            'cart_items.*.quantity' => 'required|integer|min:1',
            'cart_items.*.price' => 'required|numeric|min:0'
        ]);
    
        $totalPrice = 0;
        $orderItems = [];
    
        \DB::beginTransaction();
    
        try {
            foreach ($request->cart_items as $item) {
                $product = Product::find($item['product_id']);
    
                if ($product->inventory_quantity < $item['quantity']) {
                    return response()->json([
                        "status" => 400,
                        "error" => "Only " . $product->inventory_quantity . " items for product: " . $product->name
                    ], 200);
                }
    
                $product->inventory_quantity -= $item['quantity'];
                $product->save();
    
                $totalPrice += $item['quantity'] * $item['price'];
    
                $orderItems[] = [
                    "product_id" => $item['product_id'],
                    "product_name" => $product->name,
                    "product_img" => $product->image_url,
                    "quantity" => (int)$item['quantity'],
                    "price" => (int)$item['price'],
                    "total_price" => (int)($item['quantity'] * $item['price'])
                ];
            }
    
            $order = Order::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'address' => $request->address,
                'payment_method' => $request->payment_method,
                'total_price' => $totalPrice,
                'status' => 'pending',
                'order_items' => json_encode($orderItems) // Store order items as JSON
            ]);
    
            CartItem::where('user_id', $user->id)->delete();
    
            \DB::commit();
    
            return response()->json([
                "status" => 200,
                "message" => "Order placed successfully",
                "data" => [
                    "order_id" => $order->id,
                    "user_id" => $user->id,
                    "user_name" => $user->first_name . " " . $user->last_name,
                    "user_mobile" => $user->mobile,
                    "user_email" => $user->email,
                    "address" => $user->house_no . ", " . $user->street . ", " . $user->city . "-" . $user->zipcode . ", " . $user->country,
                    "total_price" => (int)$order->total_price,
                    "payment_method" => $order->payment_method,
                    "status" => $order->status,
                    "created_at" => $order->created_at,
                    "updated_at" => $order->updated_at,
                    "itemDetails" => $orderItems
                ]
            ], 200);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                "status" => 500,
                "error" => "Something went wrong. Please try again."
            ], 500);
        }
    }
    
    
    

    /// Get Users Order Function
    public function getOrders(Request $request)
    {
        $authHeader = $request->header('Authorization');
    
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                "status" => 400,
                "error" => "Bearer token is required."
            ], 400);
        }
    
        $token = substr($authHeader, 7);
        $user = User::where('token', $token)->first();
    
        if (!$user) {
            return response()->json([
                "status" => 401,
                "error" => "Your account is logged in on another device."
            ], 200);
        }
    
        $orders = Order::where('user_id', $user->id)->get();
    
        if ($orders->isEmpty()) {
            return response()->json(["status" => 200, "message" => "No orders found", "data" => []], 200);
        }
    
        $formattedOrders = $orders->map(function ($order) use ($user) {
            return [
                "order_id" => $order->id,
                "user_id" => $user->id,
                "user_name" => $user->first_name . " " . $user->last_name,
                "user_mobile" => $user->mobile,
                "user_email" => $user->email,
                "address" => $user->house_no . ", " . $user->street . ", " . $user->city . "-" . $user->zipcode . ", " . $user->country,
                "total_price" => (int)$order->total_price,
                "payment_method" => $order->payment_method,
                "status" => $order->status,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                "itemDetails" => json_decode($order->order_items, true)
            ];
        });
    
        return response()->json([
            "status" => 200,
            "message" => "Orders fetched successfully",
            "data" => $formattedOrders
        ], 200);
    }
    

    
}
