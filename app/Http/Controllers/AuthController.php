<?php

namespace App\Http\Controllers;

use App\Notifications\OTPNotification;
use App\OTPService;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Notification;

class AuthController extends Controller
{
    public function __construct(private OTPService $otpService)
    {
    }
    /**
     * @OA\Post(
     *     path="/api/login",
     *     tags={"Auth"},
     *     summary="Login a user",
     *     description="Handles user login for different user types.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password","user_type"},
     *             @OA\Property(property="email", type="string", example="user@gmail.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="user_type", type="string", enum={"passenger","driver","admin"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful.",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid input.")
     *         )
     *     )
     * )
     */
    public function login(Request $request)
    {
        // Validate the incoming request data
        $request->validate([
            'email' => 'required|email|regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/',
            'password' => 'required',
            'user_type' => 'required|in:passenger,driver,admin',
        ], ['email.regex' => 'The email must be a valid Gmail address.']);

        // Attempt to authenticate the user
        $credentials = $request->only('email', 'password');

        if ($request->user_type === "passenger") {
            if (Auth::guard('passenger')->attempt($credentials)) {
                // Generate a new token
                $request->session()->regenerate();
                $user = Auth::guard('passenger')->user();
                $token = $user->createToken('API Token')->plainTextToken;

                if (!$user->email_verified_at) {
                    $otp = $this->otpService->createOtp($user->email);
                    Notification::route('mail', $user->email)->notify(new OTPNotification($otp));

                    return response()->json([
                        'user' => $user,
                        'token' => $token,
                        'message' => 'OTP sent, please verify your email.',
                        'data' => ['email_verified_at' => $user->email_verified_at, 'OTP_expires_at' => now()->addMinutes(5)]
                    ], 201);
                }
                return response()->json([
                    'user' => $user,
                    'token' => $token,
                    'message' => 'Login successful!',
                    'data' => ['email_verified_at' => $user->email_verified_at]
                ], 200); // 200 for successful login
            }
        } elseif ($request->user_type === "driver") {
            if (Auth::guard('driver')->attempt($credentials)) {

                // Delete all existing tokens for the user to prevent multiple active sessions
                $request->session()->regenerate();
                $user = Auth::guard('driver')->user();
                // Generate a new token
                $token = $user->createToken('API Token')->plainTextToken;

                if ($user->is_verified === 'pending') {
                    return response()->json([
                        'user' => $user,
                        'token' => $token,
                        'message' => 'Please wait for admin verfication.',
                        'is_verified' => $user->is_verified
                    ], 200); // 200 for successful login
                } elseif ($user->is_verified == 0) {
                    return response()->json([
                        'user' => $user,
                        'token' => $token,
                        'message' => 'Your account has been declined!',
                        'is_verified' => $user->is_verified
                    ], 200); // 200 for successful login
                }

                $user->status = 'active';
                $user->save();
                return response()->json([
                    'user' => $user,
                    'token' => $token,
                    'message' => 'Login Successfull!',
                    'is_verified' => $user->is_verified
                ], 200); // 200 for successful login
            }
        } elseif ($request->user_type === "admin") {
            if (Auth::guard('web')->attempt($credentials)) {

                // Delete all existing tokens for the user to prevent multiple active sessions
                $request->session()->regenerate();
                $user = Auth::guard('web')->user();

                // Generate a new token
                $token = $user->createToken('API Token')->plainTextToken;

                return response()->json([
                    'user' => $user,
                    'token' => $token,
                    'message' => 'Login successful!',
                ], 200); // 200 for successful login
            }
        } else {
            return response()->json([
                'error' => 'Please choose role between admin, driver, passenger'
            ], 422);
        }


        return response()->json([
            'message' => 'Invalid credentials'
        ], 401); // 401 for unauthorized access
    }
    /**
     * @OA\Post(
     *     path="/api/change-password",
     *     tags={"Auth"},
     *     summary="Change user password",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password","password"},
     *             @OA\Property(property="current_password", type="string", example="current_password123"),
     *             @OA\Property(property="password", type="string", example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="string", example="Your password has been changed.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error."
     *     )
     * )
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => 'required|confirmed|min:8'
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($request->password),
        ]);
        return response()->json(['success' => 'Your password has been changed']);
    }

    /**
     * @OA\Put(
     *     path="/api/user",
     *     tags={"Auth"},
     *     summary="Update user profile",
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="address", type="string", example="Cairo")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Your account has been updated successfully"),
     *             @OA\Property(property="updated_data", type="object")
     *         )
     *     )
     * )
     */

    public function update(Request $request)
    {
        $user = $request->user();

        // Determine user type based on the instance
        if ($user instanceof \App\Models\Driver) {
            $user_type = 'driver';
        } elseif ($user instanceof \App\Models\Passenger) {
            $user_type = 'passenger';
        } else {
            $user_type = 'admin';
        }

        // Base validation
        $baseValidation = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        // Additional validation based on user type
        $additionalFields = [];
        if ($user_type === 'passenger') {
            $additionalFields = $request->validate([
                // 'email' => [
                //     'sometimes',
                //     'email',
                //     'regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/',
                //     Rule::unique('passengers', 'email')->ignore($user->id), // Ignore the current user's email
                // ],
                'phone_number' => 'sometimes|string|max:15|regex:/^\+?[0-9]+$/',
                'address' => 'sometimes|nullable|string',
                'saved_payment_methods' => 'sometimes|nullable',
            ], ['email.regex' => 'The email must be a valid Gmail address.']);
        } elseif ($user_type === 'driver') {
            $additionalFields = $request->validate([
                // 'email' => [
                //     'sometimes',
                //     'email',
                //     'regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/',
                //     Rule::unique('drivers', 'email')->ignore($user->id), // Ignore the current user's email
                // ],
                'phone_number' => [
                    'sometimes',
                    'string',
                    'max:15',
                    'regex:/^\+?[0-9]+$/',
                    Rule::unique('drivers', 'phone_number')->ignore($user->id), // Ignore the current user's email

                ],
                'address' => 'sometimes|string',
            ], ['email.regex' => 'The email must be a valid Gmail address.']);
        } elseif ($user_type === 'admin') {
            $additionalFields = $request->validate([
                'email' => [
                    'sometimes',
                    'email',
                    'regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/',
                    Rule::unique('users', 'email')->ignore($user->id), // Ignore the current user's email
                ],
            ], ['email.regex' => 'The email must be a valid Gmail address.']);
        }

        // Combine all validated fields
        $fields = array_merge($baseValidation, $additionalFields);

        // Update user information
        $user->fill($fields);
        $user->save();

        return response()->json(['message' => 'Your account has been updated successfully', 'updated_data' => $fields]);
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     tags={"Auth"},
     *     summary="Logout user",
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully logged out.")
     *         )
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            // Handle user type-specific logout logic
            if ($user instanceof \App\Models\Driver) {
                $user->update(['status' => 'inactive']);
                auth('driver')->logout();
            } elseif ($user instanceof \App\Models\Passenger) {
                auth('passenger')->logout();
            } else {
                auth('web')->logout();
            }

            if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }
        }

        // Invalidate session and regenerate CSRF token
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Successfully logged out.'], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/user",
     *     tags={"Auth"},
     *     summary="Delete my account",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password"},
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error."
     *     )
     * )
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        if ($user instanceof \App\Models\Driver) {
            auth('driver')->logout();
        } elseif ($user instanceof \App\Models\Passenger) {
            auth('passenger')->logout();
        } else {
            auth('web')->logout();
        }
        if ($token = $user->currentAccessToken()) {
            $token->delete();
        }
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

}
