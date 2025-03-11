<?php

namespace App\Http\Controllers;

use App\Models\SubCategory;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\User;
use App\Models\AddCategoryResponse; // Ensure you have this model
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
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

    // Add Category
    public function addCategory(Request $request)
    {
        $user = $this->validateAdmin($request);
        if (!($user instanceof User)) return $user; // Return error if not admin
    
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:categories,name', // Ensure unique category name
            'image_url' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Make image_url nullable
            'description' => 'nullable|string',
        ]);
    
        if ($validator->fails()) {
            return response()->json(["status" => 400, "error" => $validator->errors()->first()], 200);
        }
    
        // Check if an image is provided
        $imageUrl = null;
        if ($request->hasFile('image_url')) {
            $image = $request->file('image_url');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('categories'), $imageName);
            $imageUrl = url('categories/' . $imageName);
        }
    
        $category = Category::create([
            'name' => $request->name,
            'image_url' => $imageUrl, // Store nullable image URL
            'description' => $request->description,
        ]);
    
        return response()->json([
            "status" => 200, 
            "message" => "Category added successfully.", 
            "data" => $category
        ], 200);
    }
    
    // Update Category
    public function updateCategory(Request $request, $id)
    {
        $user = $this->validateAdmin($request);
        if (!($user instanceof User)) return $user; // Return error if not admin
    
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string',
            'image_url' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Optional image upload
            'description' => 'nullable|string',
        ]);
    
        if ($validator->fails()) {
            return response()->json(["status" => 400, "error" => collect($validator->errors()->all())->first(),], 200);
        }
    
        $category = Category::find($id);
        if (!$category) {
            return response()->json(["status" => 404, "error" => "Category not found."], 200);
        }
    
        // Update image if provided
        if ($request->hasFile('image_url')) {
            // Delete old image
            $oldImagePath = public_path(str_replace(url('/'), '', $category->image_url));
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
    
            // Store new image
            $image = $request->file('image_url');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('categories'), $imageName);
            $category->image_url = url('categories/' . $imageName);
        }
    
        // Update category details
        $category->update([
            'name' => $request->name ?? $category->name,
            'description' => $request->description ?? $category->description,
        ]);
    
        return response()->json([
            "status" => 200, 
            "message" => "Category updated successfully.", 
            "data" => $category
        ]);
    }

    // Delete Category
    public function deleteCategory(Request $request)
    {
        $user = $this->validateAdmin($request);
        if (!($user instanceof User)) return $user; // Return error if not admin

        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => 400, 
                "error" => collect($validator->errors()->all())->first(),
            ], 200);
        }

        $category = Category::find($request->category_id);
        if (!$category) return response()->json(["status" => 404, "error" => "Category not found."], 200);

        // Check if the category is linked to any subcategories
        $categoryInSubCat = SubCategory::where('category_id', $request->category_id)->exists();
        if ($categoryInSubCat) {
            return response()->json([
                "status" => 400, 
                "error" => "This category cannot be deleted because it is associated with subcategories."
            ], 200);
        }

        $imagePath = public_path(str_replace(url('/'), '', $category->image_url));
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }

        $category->delete();

        return response()->json(["status" => 200, "message" => "Category deleted successfully."]);
    }

    // Get All Categories
    public function getCategories()
    {
        $categories = Category::all();
        return response()->json([
            "status" => 200,
            'message' => "Categories fetched successfully",
            "categories" => $categories
        ], 200);
    }
}
