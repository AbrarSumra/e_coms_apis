<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProductsController extends Controller
{
    // Validate Admin Token
    public function validateAdmin(Request $request)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(["status" => 400, "error" => "Bearer token is required."], 200);
        }

        $token = substr($authHeader, 7);
        $user = User::where('token', $token)->first();

        if (!$user || $user->role !== 'admin') {
            return response()->json(["status" => 403, "error" => "Access denied."], 200);
        }

        return $user; // Return user if admin
    }

    // Add Product
    public function addProduct(Request $request)
    {
        $user = $this->validateAdmin($request);
        if (!($user instanceof User)) return $user;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'image_url' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'gallery_urls.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_available' => 'integer',
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'required|exists:sub_categories,id',
            'misc_id' => 'nullable|exists:misc_categories,id',
            'inventory_quantity' => 'required|integer',
            'low_stock_threshold' => 'required|integer',
            'sku' => 'required|string|unique:products,sku,',
            'is_in_stock' => 'integer',
            'manage_stock' => 'integer',
            'wishlist_liked' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(["status" => 400, "error" => $validator->errors()->first()], 200);
        }

        // Upload main image
        $image = $request->file('image_url');
        $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
        $image->move(public_path('products/image_url'), $imageName);
        $imageUrl = url('products/image_url/' . $imageName);

        // Upload gallery images
        $galleryUrls = [];
        if ($request->hasFile('gallery_urls')) {
            foreach ($request->file('gallery_urls') as $galleryImage) {
                $galleryName = time() . '_' . uniqid() . '.' . $galleryImage->getClientOriginalExtension();
                $galleryImage->move(public_path('products/gallery'), $galleryName);
                $galleryUrls[] = url('products/gallery/' . $galleryName);
            }
        }

        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'image_url' => $imageUrl,
            'gallery_urls' => $galleryUrls,
            'is_available' => $request->is_available ?? 1,
            'category_id' => $request->category_id,
            'sub_category_id' => $request->sub_category_id,
            'misc_id' => $request->misc_id,
            'inventory_quantity' => $request->inventory_quantity ?? 0,
            'low_stock_threshold' => $request->low_stock_threshold ?? 5,
            'sku' => $request->sku,
            'is_in_stock' => $request->is_in_stock ?? 1,
            'manage_stock' => $request->manage_stock ?? 1,
            'wishlist_liked' => $request->wishlist_liked ?? false,
        ]);

        return response()->json([
            "status" => 200,
            "message" => "Product added successfully.",
            "product" => $this->formatProductResponse($product)
        ], 200);
    }

    // Update Product
    public function updateProduct(Request $request, $id)
    {
        $user = $this->validateAdmin($request);
        if (!($user instanceof User)) return $user;
    
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'image_url' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'gallery_urls.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'prev_gallery_urls' => 'nullable|array',
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'required|exists:sub_categories,id',
            'misc_id' => 'nullable|exists:misc_categories,id',
            'inventory_quantity' => 'required|integer',
            'low_stock_threshold' => 'required|integer',
            'sku' => 'nullable|string|unique:products,sku,' . $id,
            'is_in_stock' => 'integer',
            'manage_stock' => 'integer',
            'wishlist_liked' => 'boolean',
        ]);
    
        if ($validator->fails()) {
            return response()->json(["status" => 400, "error" => $validator->errors()->first()], 200);
        }
    
        $product = Product::find($id);
        if (!$product) {
            return response()->json(["status" => 404, "error" => "Product not found."], 200);
        }
    
        // ✅ Only update if a new image is provided
        if ($request->hasFile('image_url')) {
            // Delete old image if exists
            if (!empty($product->image_url)) {
                $oldImagePath = public_path(str_replace(url('/'), '', $product->image_url));
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
    
            // Upload and update new image
            $image = $request->file('image_url');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('products/image_url'), $imageName);
            $product->image_url = url('products/image_url/' . $imageName);
        }
    
        // ✅ Handle gallery images update
        $prevGalleryUrls = $request->prev_gallery_urls ?? [];
        $currentGalleryImages = $product->gallery_urls ?? [];
    
        // Delete images that are removed by the user
        foreach (array_diff($currentGalleryImages, $prevGalleryUrls) as $imagePath) {
            $imageFilePath = public_path(str_replace(url('/'), '', $imagePath));
            if (file_exists($imageFilePath)) {
                unlink($imageFilePath);
            }
        }
    
        // Keep only the images that are still in prev_gallery_urls
        $galleryImagesToKeep = array_values($prevGalleryUrls);
    
        // Add new uploaded gallery images
        if ($request->hasFile('gallery_urls')) {
            foreach ($request->file('gallery_urls') as $galleryImage) {
                $galleryName = time() . '_' . uniqid() . '.' . $galleryImage->getClientOriginalExtension();
                $galleryImage->move(public_path('products/gallery'), $galleryName);
                $galleryImagesToKeep[] = url('products/gallery/' . $galleryName);
            }
        }
    
        $product->gallery_urls = $galleryImagesToKeep;
    
        // ✅ Save updated data excluding image fields (which are already updated)
        $product->update($request->except(['image_url', 'gallery_urls', 'prev_gallery_urls']));
    
        return response()->json([
            "status" => 200,
            "message" => "Product updated successfully.",
            "product" => $this->formatProductResponse($product)
        ], 200);
    }
    
    // Delete Product
    public function deleteProduct(Request $request)
    {
        $user = $this->validateAdmin($request);

        if (!($user instanceof User)) return $user;

        $product = Product::find($request->id);
        if (!$product) {
            return response()->json(["status" => 404, "error" => "Product not found."], 200);
        }

        $imagePath = public_path(str_replace(url('/'), '', $product->image_url));
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }

        if ($product->gallery_urls) {
            foreach ($product->gallery_urls as $galleryImage) {
                $galleryPath = public_path(str_replace(url('/'), '', $galleryImage));
                if (file_exists($galleryPath)) {
                    unlink($galleryPath);
                }
            }
        }
        
        // Remove product ID from all users' wishlists
        User::all()->each(function ($user) use ($product) {
            $wishlist = $user->wishlist ?? [];
            if (($key = array_search($product->id, $wishlist)) !== false) {
                unset($wishlist[$key]); // Remove the product ID
                $user->update(['wishlist' => array_values($wishlist)]); // Reindex array & update DB
            }
        });

        $product->delete();

        return response()->json(["status" => 200, "message" => "Product deleted successfully."]);
    }

    // Get All Products
    public function getAllProducts(Request $request)
    {
        // Get token from request header
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
            ], 200);
        }

        // Get user's wishlist
        $wishlist = $user->wishlist ?? [];

        // Get filter parameters from request
        $categoryId = $request->query('category_id');
        $subCategoryId = $request->query('sub_category_id');
        $miscId = $request->query('misc_id');

        // Fetch products based on filters
        $productsQuery = Product::query();

        if ($categoryId) {
            $productsQuery->where('category_id', $categoryId);
        }
        if ($subCategoryId) {
            $productsQuery->where('sub_category_id', $subCategoryId);
        }
        if ($miscId) {
            $productsQuery->where('misc_id', $miscId);
        }

        // Get filtered products
        $products = $productsQuery->get()->map(function ($product) use ($wishlist) {
            return array_merge($product->toArray(), [
                "wishlist_liked" => in_array($product->id, $wishlist)
            ]);
        });

        return response()->json([
            "status" => 200,
            "message" => "Products fetched successfully",
            "data" => $products
        ], 200);
    }

    /// Get Public All Products
    public function getPublicAllProducts()
    {
        $products = Product::all()->map(function ($product) {
            return $this->formatPublicProductResponse($product);
        });

        return response()->json([
            "status" => 200,
            "message" => "Products fetched successfully",
            "data" => $products
        ], 200);
    }

    // Get All Public Products By ID
    public function getProductByID(Request $request, $id)
    {
        // Get token from request header
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
            ], 200);
        }
    
        // Find product
        $product = Product::find($id);
        if (!$product) {
            return response()->json(["status" => 404, "error" => "Product not found."], 200);
        }
    
        // Check if product is in the user's wishlist
        $wishlist = $user->wishlist ?? [];
        $wishlistLiked = in_array($id, $wishlist);
    
        return response()->json([
            "status" => 200,
            "message" => "Product fetched successfully",
            "data" => array_merge($product->toArray(), [
                "wishlist_liked" => $wishlistLiked
            ])
        ], 200);
    }
    // Get All Public Products By ID
    public function getPublicProductByID(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(["status" => 404, "error" => "Product not found."], 200);
        }

        return response()->json([
            "status" => 200,
            "message" => "Products fetched successfully",
            "data" => $this->formatPublicProductResponse($product),
        ], 200);
    }

    // Helper function to format product response
    private function formatProductResponse($product)
    {
        return [
            "id" => $product->id,
            "name" => $product->name,
            "description" => $product->description,
            "price" => $product->price,
            "image_url" => $product->image_url,
            "gallery_urls" => array_merge([$product->image_url], $product->gallery_urls ?? []),
            "is_available" => (int)$product->is_available,
            "category_id" => (int)$product->category_id,
            "sub_category_id" => (int)$product->sub_category_id,
            "misc_id" => (int)($product->misc_id ?? null),
            "reviews_count" => $product->reviews_count,
            "brand" => $product->brand,
            "rating" => $product->rating,
            "inventory_quantity" => $product->inventory_quantity,
            "low_stock_threshold" => $product->low_stock_threshold,
            "sku" => $product->sku,
            "is_in_stock" => (int)$product->is_in_stock,
            "manage_stock" => (int)$product->manage_stock,
            "warehouse_location" => $product->warehouse_location,
            "wishlist_liked" => (bool)$product->wishlist_liked,
            "created_at" => $product->created_at,
            "updated_at" => $product->updated_at,
        ];
    }

    // Helper function to format Public product response
    private function formatPublicProductResponse($product)
    {
        return [
            "id" => $product->id,
            "name" => $product->name,
            "description" => $product->description,
            "price" => $product->price,
            "image_url" => $product->image_url,
            "gallery_urls" => array_merge([$product->image_url], $product->gallery_urls ?? []),
            "is_available" => (int)$product->is_available,
            "category_id" => (int)$product->category_id,
            "sub_category_id" => (int)$product->sub_category_id,
            "misc_id" => (int)($product->misc_id ?? null),
            "reviews_count" => $product->reviews_count,
            "brand" => $product->brand,
            "rating" => $product->rating,
            "inventory_quantity" => $product->inventory_quantity,
            "low_stock_threshold" => $product->low_stock_threshold,
            "sku" => $product->sku,
            "is_in_stock" => (int)$product->is_in_stock,
            "manage_stock" => (int)$product->manage_stock,
            "warehouse_location" => $product->warehouse_location,
            "created_at" => $product->created_at,
            "updated_at" => $product->updated_at,
        ];
    }

    /// Get Search Products Function
    public function searchProducts(Request $request)
    {
        // Get search query and optional filters
        $query = $request->input('search');
        $categoryId = $request->input('categoryId');
        $subCategoryId = $request->input('subCategoryId');
        $miscId = $request->input('miscId');
    
        // Search Categories
        $categories = Category::when($query, function ($queryBuilder) use ($query) {
                return $queryBuilder->where('name', 'LIKE', "%{$query}%");
            })
            ->get();
    
        // Search Products
        $products = Product::when($query, function ($queryBuilder) use ($query) {
                return $queryBuilder->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('description', 'LIKE', "%{$query}%");
            })
            ->when($categoryId, function ($queryBuilder) use ($categoryId) {
                return $queryBuilder->where('category_id', $categoryId);
            })
            ->when($subCategoryId, function ($queryBuilder) use ($subCategoryId) {
                return $queryBuilder->where('sub_category_id', $subCategoryId);
            })
            ->when($miscId, function ($queryBuilder) use ($miscId) {
                return $queryBuilder->where('misc_id', $miscId);
            })
            ->get();
    
        return response()->json([
            "status" => 200,
            "message" => "Filtered categories and products fetched successfully",
            "data" => [
                "categories" => $categories,
                "products" => $products
            ]
        ], 200);
    }
    
}
