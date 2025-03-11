<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SubCategory;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class SubCategoryController extends Controller
{
    // Validate Admin Token
    private function validateAdmin(Request $request)
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

        return $user;
    }

    // Add SubCategory
    public function addSubCategory(Request $request)
    {
        $user = $this->validateAdmin($request);
        if (!($user instanceof User)) return $user;

        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|unique:sub_categories,name',
            'image_url' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(["status" => 400, "error" => $validator->errors()->first()], 200);
        }

        // Store Image
        $imageUrl = null;
        if ($request->hasFile('image_url')) {
            $image = $request->file('image_url');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('sub_categories'), $imageName);
            $imageUrl = url('sub_categories/' . $imageName);
        }

        $subCategory = SubCategory::create([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'image_url' => $imageUrl,
        ]);

        return response()->json([
            "status" => 200,
            "message" => "SubCategory added successfully.",
            "data" => $subCategory
        ], 200);
    }

    // Get All SubCategories
    public function getSubCategories()
    {
        $subCategories = SubCategory::with('category')->get();
        return response()->json([
            "status" => 200,
            "message" => "SubCategories fetched successfully",
            "data" => $subCategories
        ], 200);
    }

    // Update SubCategory
    public function updateSubCategory(Request $request, $id)
    {
        $user = $this->validateAdmin($request);
        if (!($user instanceof User)) return $user;

        $validator = Validator::make($request->all(), [
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string',
            'image_url' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(["status" => 400, "error" => $validator->errors()->first()], 200);
        }

        $subCategory = SubCategory::find($id);
        if (!$subCategory) {
            return response()->json(["status" => 404, "error" => "SubCategory not found."], 200);
        }

        // Delete old image if new image is uploaded
        if ($request->hasFile('image_url')) {
            $oldImagePath = public_path(str_replace(url('/'), '', $subCategory->image_url));
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }

            $image = $request->file('image_url');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('sub_categories'), $imageName);
            $subCategory->image_url = url('sub_categories/' . $imageName);
        }

        $subCategory->update([
            'category_id' => $request->category_id ?? $subCategory->category_id,
            'name' => $request->name ?? $subCategory->name,
        ]);

        return response()->json([
            "status" => 200,
            "message" => "SubCategory updated successfully.",
            "data" => $subCategory
        ], 200);
    }

    // Delete SubCategory
    public function deleteSubCategory(Request $request)
    {
        $user = $this->validateAdmin($request);
        if (!($user instanceof User)) return $user;

        $validator = Validator::make($request->all(), [
            'sub_category_id' => 'required|exists:sub_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json(["status" => 400, "error" => $validator->errors()->first()], 200);
        }

        $subCategory = SubCategory::find($request->sub_category_id);
        if (!$subCategory) return response()->json(["status" => 404, "error" => "SubCategory not found."], 200);

        // Delete image
        $imagePath = public_path(str_replace(url('/'), '', $subCategory->image_url));
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }

        $subCategory->delete();

        return response()->json(["status" => 200, "message" => "SubCategory deleted successfully."], 200);
    }
}

