<?php

use App\Http\Controllers\CartItemsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\StudentsController;
use App\Http\Controllers\WishlistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AuthApiMiddleware;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SubCategoryController;
use App\Http\Controllers\MiscCategoryController;

/// bANhggylmYN24

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function() {
    return ['name' => 'Abrar Khira', 'Std' => 12];
});

Route::get('/users', [UserController::class, 'getAllUsers']);
Route::post('/create-user', [UserController::class, 'createUser']);
Route::post('/verify-otp', [UserController::class, 'verifyOtp']);

Route::post('/login', [UserController::class, 'login']);
Route::middleware('auth.api')->post('/logout', [UserController::class, 'logout']);

Route::middleware('auth.api')->get('/profile', [UserController::class, 'getProfile']);
Route::middleware('auth.api')->post('/profile', [UserController::class, 'updateProfile']);


Route::post('/categories', [CategoryController::class, 'addCategory']); // Add Category
Route::get('/categories', [CategoryController::class, 'getCategories']); // Get Categories


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Category Routes
Route::get('/categories', [CategoryController::class, 'getCategories']); // Get Categories
Route::middleware('auth.api')->group(function () {
    Route::post('/categories', [CategoryController::class, 'addCategory']); // Add Category
    Route::post('/update/category/{id}', [CategoryController::class, 'updateCategory']); // Update Category
    Route::post('/category/delete', [CategoryController::class, 'deleteCategory']); // Delete Category
});

// Sub-Category Routes
Route::get('/subcategories', [SubCategoryController::class, 'getSubCategories']);
Route::middleware('auth.api')->group(function () {
    Route::post('/subcategories', [SubCategoryController::class, 'addSubCategory']);
    Route::post('/update/subCategory/{id}', [SubCategoryController::class, 'updateSubCategory']);
    Route::post('/subcategories/delete', [SubCategoryController::class, 'deleteSubCategory']);
});

// Misc Category Routes
Route::get('/misc', [MiscCategoryController::class, 'getMiscCategories']); // Get Misc Category
Route::middleware('auth.api')->group(function () {
    Route::post('/misc', [MiscCategoryController::class, 'addMiscCategories']); // Add Misc Category
    Route::post('/update/misc/{id}', [MiscCategoryController::class, 'updateMiscCategory']); // Update Misc Category
    Route::post('/misc/delete', [MiscCategoryController::class, 'deleteMiscCategory']); // Delete Misc Category
});

/// Search Api
Route::get('/search', [ProductsController::class, 'searchProducts']);

// Product Routes
Route::get('/public/products', [ProductsController::class, 'getPublicAllProducts']); // Get Public Product
Route::get('/public/product/{id}', [ProductsController::class, 'getPublicProductByID']); // Get Public Product By ID
Route::middleware('auth.api')->group(function () {
    Route::get('/products', [ProductsController::class, 'getAllProducts']); // Get Public Product
    Route::get('/product/{id}', [ProductsController::class, 'getProductByID']); // Get Public Product By ID
    Route::post('/add-product', [ProductsController::class, 'addProduct']); // Add Product
    Route::post('/update-product/{id}', [ProductsController::class, 'updateProduct']); // Update Product
    Route::post('/product/delete', [ProductsController::class, 'deleteProduct']); // Delete Product
});

/// Wishlist Routes
Route::middleware('auth.api')->group(function () {
    Route::get('/wishlist', [WishlistController::class, 'getUserWishlist']); // Get Public Product
    Route::post('/wishlist/add', [WishlistController::class, 'addToWishlist']); // Get Public Product By ID
    Route::post('/wishlist/remove', [WishlistController::class, 'removeFromWishlist']); // Add Product
});

/// Cart Routes
Route::middleware('auth.api')->group(function () {
    Route::get('/cart', [CartItemsController::class, 'getCart']); // Get Public Product
    Route::post('/cart/add', [CartItemsController::class, 'addToCart']); // Get Public Product By ID
    Route::post('/cart/cart-item', [CartItemsController::class, 'removeFromCart']); // Add Product
    Route::post('/cart/update-cart-item', [CartItemsController::class, 'updateCartItem']); // Add Product
});

/// Order Routes
Route::middleware('auth.api')->group(function () {
    Route::get('/user/orders', [OrderController::class, 'getOrders']); // Get Public Product
    Route::post('/orders', [OrderController::class, 'orderCreate']); // Get Public Product By ID
});