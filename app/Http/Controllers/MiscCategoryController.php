<?php

namespace App\Http\Controllers;

use App\Models\MiscCategory;
use App\Http\Controllers\CategoryController;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class MiscCategoryController extends Controller
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

    /// Add Misc Categories
    public function addMiscCategories(Request $request) {
    
        $adminUser = $this->validateAdmin($request);
        if (!($adminUser instanceof User)) return $adminUser;
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:misc_categories,name', // Ensure unique category name
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(["status" => 400, "error" => $validator->errors()->first()], 200);
        }

        $miscCategory = MiscCategory::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json([
            "status" => 200, 
            "message" => "Misc-Category added successfully.", 
            "data" => $miscCategory
        ], 200);
    }

    public function updateMiscCategory(Request $request, $id){
        $adminUser = $this->validateAdmin($request);
        if (!($adminUser instanceof User)) return $adminUser;

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string',
            'description' => 'nullable|string',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                "status" => 400, 
                "error" => collect($validator->errors()->all())->first(),
            ], 200);
        }

        $miscCategory = MiscCategory::find($id);
        if (!$miscCategory) {
            return response()->json([
                "status" => 404, 
                "error" => "Misc-Category not found."
            ], 200);
        }

        // Update category details
        $miscCategory->update([
            'name' => $request->name ?? $miscCategory->name,
            'description' => $request->description ?? $miscCategory->description,
        ]);
    
        return response()->json([
            "status" => 200, 
            "message" => "Misc-Category updated successfully.", 
            "data" => $miscCategory
        ]);
    }


    public function deleteMiscCategory(Request $request) {
        $adminUser = $this->validateAdmin($request);
        if (!($adminUser instanceof User)) return $adminUser;

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:misc_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => 400, 
                "error" => collect($validator->errors()->all())->first(),
            ], 200);
        }

        $miscCat = MiscCategory::find($request->id);
        if(!$miscCat) {
            return response()->json([
                "status" => 404, 
                "error" => "Misc-Category not found."
            ], 200);
        }


        $miscCat->delete();

        return response()->json([
            "status" => 200,
             "message" => "Misc-Category deleted successfully."
             ], 200);
    }

    /// Get Misc Categories
    public function getMiscCategories()
    {
        $misccategories = MiscCategory::all();
        return response()->json([
            "status" => 200,
            'message' => "Misc-Categories fetched successfully",
            "data" => $misccategories
        ], 200);
    }
}
