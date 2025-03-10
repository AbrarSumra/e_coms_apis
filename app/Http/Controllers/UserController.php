<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Otp;
use App\Models\Product;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use App\Mail\SendOTP;
use Carbon\Carbon;
use App\Models\TokenCounter;
use Illuminate\Support\Facades\Storage;
use Validator;
use Log;
use DB;

class UserController extends Controller
{
    public function getAllUsers()
    {
        return response()->json([
            'status' => 200,
            'message' => 'User list fetched successfully',
            'data' => User::all(),
        ], 200);
    }

    /// Login Function
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'fcm_token' => 'nullable|string',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                "status" => 400,
                "error" => collect($validator->errors()->all())->first()
            ], 200);
        }
    
        $user = User::where('email', $request->email)->first();
    
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                "status" => 400,
                "error" => "Invalid credentials."
            ], 200);
        }
    
        if (!$user->email_verified_at) {
            return response()->json([
                "status" => 400,
                "error" => "Please verify your email before logging in."
            ], 200);
        }

        $counter = TokenCounter::firstOrCreate([], ['last_number' => 0]);
        $newNumber = $counter->last_number + 1;
        $counter->update(['last_number' => $newNumber]);
    
        // Generate a new token
        $token = $newNumber . '|' . bin2hex(random_bytes(32));

        // $encrypted = encrypt($token);
    
        // Store token in the users table
        $user->update([
            'token' => $token,
            'fcm_token' => $request->fcm_token ?? $user->fcm_token
        ]);

        // Exclude 'token' from user data
        $userData = $user->toArray();
        unset($userData['token']);
    
        return response()->json([
            "status" => 200,
            "message" => "Login successful.",
            "data" => [
                "token" => $token,
                "user" => [
                    "id" => $user->id,
                    "first_name" => $user->first_name,
                    "last_name" => $user->last_name,
                    "email" => $user->email,
                    "email_verified_at" => $user->email_verified_at,
                    "mobile" => $user->mobile,
                    "fcm_token" => $user->fcm_token,
                    "role" => $user->role,
                    "wishlist" => $user->wishlist,
                    "profile_image" => $user->profile_image 
                                    ? url('profile_images/' . $user->profile_image) 
                                    : null,
                    "date_of_birth" => $user->date_of_birth,
                    "address" => $user->address,
                    "city" => $user->city,
                    "street" => $user->street,
                    "house_no" => $user->house_no,
                    "zipcode" => $user->zipcode,
                    "country" => $user->country,
                    "created_at" => $user->created_at,
                    "updated_at" => $user->updated_at,
                ]
            ]
        ], 200);
    }
    
    /// Logout Function
    public function logout(Request $request)
    {
        $authHeader = $request->header('Authorization');
    
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                "status" => 400,
                "message" => "Bearer token is required."
            ], 400);
        }
    
        $token = substr($authHeader, 7); // Extract token
    
        // Find the user with the given token
        $user = User::where('token', $token)->first();
    
        if (!$user) {
            return response()->json([
                "status" => 401,
                "message" => "Unauthorized. Invalid token."
            ], 401);
        }
    
        // Invalidate the token by setting it to null
        $user->update(['token' => null]);
    
        return response()->json([
            "status" => 200,
            "message" => "Logout successful."
        ], 200);
    }
    
    /// Create New User Function
    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->whereNotNull('email_verified_at')
            ],
            'mobile' => [
                'required',
                'string',
                'min:10',
                'max:15',
                Rule::unique('users', 'mobile')->whereNotNull('email_verified_at') // Fixed rule for mobile
            ],
            'role'       => 'nullable|string|in:user,admin',
            'fcm_token'  => 'nullable|string',
            'password'   => 'required|string|min:6|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => 400,
                "error" => collect($validator->errors()->all())->first()
            ], 200);
        }

        // Check if the user exists by email
        $existingUser = User::where('email', $request->email)->first();

        if ($existingUser) {
            if ($existingUser->email_verified_at) {
                return response()->json([
                    "status" => 400,
                    "error" => "The email has already been taken."
                ], 200);
            } else {
                // Resend OTP if the user exists but is not verified
                return $this->sendOtp($request->email);
            }
        }

        // Check if the mobile number already exists
        $existingMobile = User::where('mobile', $request->mobile)->first();
        
        if ($existingMobile) {
            if ($existingMobile->email_verified_at) {
                return response()->json([
                    "status" => 400,
                    "error" => "The mobile number has already been taken."
                ], 200);
            } else {
                // Resend OTP if the mobile number exists but is not verified
                return $this->sendOtp($existingMobile->email);
            }
        }

        // Store user details but keep email unverified
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'mobile'     => $request->mobile,
            'role'       => $request->role ?? 'user',
            'fcm_token'  => $request->fcm_token,
            'password'   => Hash::make($request->password),
            'email_verified_at' => null, // Email verification pending
        ]);

        // Send OTP
        return $this->sendOtp($request->email);
    }

    /// Verify otp function
    
    public function verifyOtp(Request $request)
    {
        // Validate input fields
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp'   => 'required|numeric|digits:4'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                "status" => 400,
                "error" => collect($validator->errors()->all())->first()
            ], 200);
        }
    
        // Find user by email
        $user = User::where('email', $request->email)->first();
    
        if (!$user) {
            return response()->json([
                'status' => 400,
                'error' => 'User not found. Please register first.',
            ], 200);
        }
    
        // Check if OTP exists
        if (!$user->otp || !$user->otp_expires_at) {
            return response()->json([
                'status' => 400,
                'error' => 'OTP not found. Please request a new OTP.',
            ], 200);
        }
    
        // Check if OTP is expired
        if (Carbon::parse($user->otp_expires_at)->isPast()) {
            return response()->json([
                'status' => 400,
                'error' => 'OTP expired. Please request a new OTP.',
            ], 200);
        }
    
        // Debugging logs
        \Log::info("Stored OTP: " . $user->otp);
        \Log::info("Received OTP: " . $request->otp);
        \Log::info("OTP Expiry: " . $user->otp_expires_at);
    
        // Check if OTP matches
        if ((string) $user->otp !== (string) $request->otp) {
            return response()->json([
                'status' => 400,
                'error' => 'Invalid OTP.',
            ], 200);
        }
    
        DB::beginTransaction();
        try {
            // Check if email is already verified
            if ($user->email_verified_at) {
                return response()->json([
                    'status' => 400,
                    'error' => 'Email already verified.',
                ], 200);
            }
    
            // Verify email and clear OTP
            $user->update([
                'email_verified_at' => now(),
                'otp' => null, // Clear OTP after successful verification
                'otp_expires_at' => null
            ]);
    
            // Generate a unique token
            $counter = TokenCounter::firstOrCreate([], ['last_number' => 0]);
            $newNumber = $counter->last_number + 1;
            $counter->update(['last_number' => $newNumber]);
    
            $token = $newNumber . '|' . bin2hex(random_bytes(32));
    
            // Store token in user table
            $user->update([
                'token' => $token,
                'fcm_token' => $request->fcm_token ?? $user->fcm_token
            ]);
    
            DB::commit();
    
            // Exclude 'token' from user data for security
            $userData = $user->toArray();
            unset($userData['token']);
    
            return response()->json([
                'status' => 200,
                'message' => 'OTP verified successfully!',
                "data" => [
                    "token" => $token,
                    "user" => $userData
                ]
            ], 200);
    
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("OTP Verification Error: " . $e->getMessage());
    
            return response()->json([
                'status' => 500,
                'error' => 'Something went wrong. Please try again.',
            ], 500);
        }
    }
    
    
    /// Send otp to email function
    private function sendOtp($email)
    {
        $user = User::where('email', $email)->first();
    
        if (!$user) {
            return response()->json([
                'status' => 400,
                'error' => 'User not found.'
            ], 200);
        }
    
        $otp = rand(1000, 9999);
        $expiresAt = Carbon::now()->addMinutes(10);
    
        // Update OTP in users table
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => $expiresAt
        ]);
    
        // Send OTP via email
        Mail::to($email)->send(new SendOTP($otp));
    
        return response()->json([
            'status' => 200,
            'message' => 'OTP resent to email. Please verify to complete registration.'
        ], 200);
    }
    
    /// Get Profile
    public function getProfile(Request $request)
    {
        $authHeader = $request->header('Authorization'); // Get Authorization header
    
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                "status" => 400,
                "error" => "Bearer token is required."
            ], 200); // Change 400 -> 200
        }
    
        // Extract token from "Bearer <token>"
        $token = substr($authHeader, 7); 
    
        // Find user by token
        $user = User::where('token', $token)->first();
    
        if (!$user) {
            // Token is invalid or expired
            return response()->json([
                "status" => 401,
                "error" => "Invalid or expired token."
            ], 200); // Change 401 -> 200
        }
    
        // Check if the user has a different token (means logged in on another device)
        if ($user->token !== $token) {
            return response()->json([
                "status" => 401,
                "error" => "Your account is logged in on another device."
            ], 200);
        }

        $userData = $user->toArray();
        unset($userData['token']);
    
        return response()->json([
            "status" => 200,
            "message" => "Profile fetched successfully.",
            "data" => [
                "id" => $user->id,
                "first_name" => $user->first_name,
                "last_name" => $user->last_name,
                "email" => $user->email,
                "email_verified_at" => $user->email_verified_at,
                "mobile" => $user->mobile,
                "fcm_token" => $user->fcm_token,
                "role" => $user->role,
                "wishlist" => $user->wishlist,
                "profile_image" => $user->profile_image 
                                ? url('profile_images/' . $user->profile_image) 
                                : null,
                "date_of_birth" => $user->date_of_birth,
                "address" => $user->address,
                "city" => $user->city,
                "street" => $user->street,
                "house_no" => $user->house_no,
                "zipcode" => $user->zipcode,
                "country" => $user->country,
                "created_at" => $user->created_at,
                "updated_at" => $user->updated_at,
            ]
        ], 200);
    }

    public function updateProfile(Request $request)
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
    
        // Validation rules
        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:15|unique:users,mobile,' . $user->id,
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'city' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:255',
            'house_no' => 'nullable|string|max:255',
            'zipcode' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'address' => 'nullable|string',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                "status" => 422,
                "error" => $validator->errors()->first()
            ], 422);
        }
    
        // Handle image upload if provided
        if ($request->hasFile('profile_image')) {
            // Delete the old image if exists
            if ($user->profile_image && file_exists(public_path('profile_images/' . $user->profile_image))) {
                unlink(public_path('profile_images/' . $user->profile_image));
            }
    
            // Save image in public/profile_images
            $image = $request->file('profile_image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('profile_images'), $imageName);
    
            // Store only filename in DB
            $user->profile_image = $imageName;
        }
    
        // Update user details
        $user->update([
            'first_name' => $request->first_name ?? $user->first_name,
            'last_name' => $request->last_name ?? $user->last_name,
            'mobile' => $request->mobile ?? $user->mobile,
            'email' => $request->email ?? $user->email,
            'city' => $request->city ?? $user->city,
            'street' => $request->street ?? $user->street,
            'house_no' => $request->house_no ?? $user->house_no,
            'zipcode' => $request->zipcode ?? $user->zipcode,
            'country' => $request->country ?? $user->country,
            'date_of_birth' => $request->date_of_birth ?? $user->date_of_birth,
            'address' => $request->address ?? $user->address,
        ]);
    
        return response()->json([
            "status" => 200,
            "message" => "Profile updated successfully.",
            "data" => [
                "id" => $user->id,
                "first_name" => $user->first_name,
                "last_name" => $user->last_name,
                "email" => $user->email,
                "email_verified_at" => $user->email_verified_at,
                "mobile" => $user->mobile,
                "fcm_token" => $user->fcm_token,
                "role" => $user->role,
                "wishlist" => $user->wishlist,
                "profile_image" => $user->profile_image 
                                ? url('profile_images/' . $user->profile_image) 
                                : null,
                "date_of_birth" => $user->date_of_birth,
                "address" => $user->address,
                "city" => $user->city,
                "street" => $user->street,
                "house_no" => $user->house_no,
                "zipcode" => $user->zipcode,
                "country" => $user->country,
                "created_at" => $user->created_at,
                "updated_at" => $user->updated_at,
            ]
        ], 200);
    }

}