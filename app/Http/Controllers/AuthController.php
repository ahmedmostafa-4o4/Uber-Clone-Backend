<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Passenger;
use App\Models\User;
use App\Notifications\OTPNotification;
use App\OTPService;
use Exception;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Notification;
use Storage;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class AuthController extends Controller
{
    public function __construct(private OTPService $otpService)
    {
    }

    public function getToken(Request $request)
    {
        $user = $request->user(); // Get authenticated user

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Check if the user already has a token (if stored manually)
        $existingToken = $user->tokens()->latest()->first();

        if ($existingToken) {
            return response()->json([
                'success' => true,
                'token' => $existingToken->plainTextToken,
            ]);
        }

        // Generate a new token
        $token = $user->createToken('auth_token');

        return response()->json([
            'success' => true,
            'token' => $token->plainTextToken,
        ]);
    }

    public function getAuth(Request $request)
    {
        $user = $request->user();

        if ($user instanceof Passenger) {
            $rides = $user->rides()->with(
                'driver',
            )->get();
            return response()->json([
                'user' => $user,
                'user_type' => 'Passenger',
                'rides' => $rides,
                'feedbacks' => $user->feedbacks()->get(['id', 'passenger_id', 'passenger_rating', 'driver_comments'])
            ]);
        } elseif ($user instanceof Driver) {
            $rides = $user->rides()->with(
                'passenger',
            )->get();
            return response()->json([
                'user' => $user,
                'user_type' => 'Driver',
                'rides' => $rides,
                'feedbacks' => $user->feedbacks()->get(['id', 'driver_id', 'driver_rating', 'passenger_comments'])
            ]);
        }

        return $user;
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email|regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/',
            'password' => 'required',
            'user_type' => 'required|in:passenger,driver,admin',
        ], ['email.regex' => 'The email must be a valid Gmail address.']);


        if ($request->user_type === "passenger") {
            $user = Passenger::where('email', $request->email)->first();

            if ($user && Hash::check($request->password, $user->password)) {
                $user->tokens()->delete();

                $token = $user->createToken('API Token')->plainTextToken;

                return response()->json([
                    'user' => $user,
                    'token' => $token,
                    'message' => 'Login successful!',
                    'data' => ['email_verified_at' => $user->email_verified_at]
                ], 200);
            }
        } elseif ($request->user_type === "driver") {
            $user = Driver::where('email', $request->email)->first();

            if ($user && Hash::check($request->password, $user->password)) {

                $user->tokens()->delete();


                if ($user->is_verified === 'pending') {
                    return response()->json([
                        'user_type' => 'Driver',
                        'message' => 'Please wait for admin verfication. Your account is pending!',
                        'data' => ['is_verified' => $user->is_verified, 'user_type' => 'Driver'],
                    ], 200);
                } elseif ($user->is_verified == 0) {
                    return response()->json([
                        'message' => 'Your account has been declined!',
                        'data' => ['is_verified' => $user->is_verified, 'user_type' => 'Driver']
                    ], 200);
                }

                $user->status = 'active';
                $user->save();

                $token = $user->createToken('API Token')->plainTextToken;

                return response()->json([
                    'user' => $user,
                    'token' => $token,
                    'message' => 'Login Successfull!',
                    'is_verified' => $user->is_verified
                ], 200);
            }
        } elseif ($request->user_type === "admin") {
            $user = User::where('email', $request->email)->first();

            if ($user && Hash::check($request->password, $user->password)) {

                $user->tokens()->delete();

                $token = $user->createToken('API Token')->plainTextToken;

                return response()->json([
                    'user' => $user,
                    'token' => $token,
                    'message' => 'Login successful!',
                ], 200);
            }
        } else {
            return response()->json([
                'error' => 'Please choose role between admin, driver, passenger'
            ], 422);
        }


        return response()->json([
            'message' => 'Invalid credentials'
        ], 401);
    }

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



    public function update(Request $request)
    {
        $user = $request->user();

        if ($user instanceof Driver) {
            $user_type = 'driver';
        } elseif ($user instanceof Passenger) {
            $user_type = 'passenger';
        } else {
            $user_type = 'admin';
        }

        $baseValidation = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        $additionalFields = [];
        if ($user_type === 'passenger') {
            $additionalFields = $request->validate([
                'phone_number' => 'sometimes|string|max:15|regex:/^\+?[0-9]+$/',
                'address' => 'sometimes|nullable|string',
                'saved_payment_methods' => 'sometimes|nullable',
            ], ['email.regex' => 'The email must be a valid Gmail address.']);
        } elseif ($user_type === 'driver') {
            $additionalFields = $request->validate([
                'phone_number' => [
                    'sometimes',
                    'string',
                    'max:15',
                    'regex:/^\+?[0-9]+$/',
                    Rule::unique('drivers', 'phone_number')->ignore($user->id), // Ignore the current user's email

                ],
                'address' => 'sometimes|string',
                'status' => 'sometimes|string|in:active,inactive',
            ], ['email.regex' => 'The email must be a valid Gmail address.']);

        } elseif ($user_type === 'admin') {
            $additionalFields = $request->validate([
                'email' => [
                    'sometimes',
                    'email',
                    'regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/',
                    Rule::unique('users', 'email')->ignore($user->id),
                ],
            ], ['email.regex' => 'The email must be a valid Gmail address.']);
        }

        $fields = array_merge($baseValidation, $additionalFields);

        $user->fill($fields);
        $user->save();

        return response()->json(['message' => 'Your account has been updated successfully', 'updated_data' => $fields]);
    }


    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            if ($user instanceof Driver) {
                $user->update(['status' => 'inactive']);
            }

            $user->currentAccessToken()->delete();

            return response()->json(['message' => 'Successfully logged out.'], 200);
        }

        return response()->json(['message' => 'User not found.'], 404);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->currentAccessToken()->delete();

        if ($user instanceof Passenger && $user->customer_id) {
            if ($stripeSecret = config('services.stripe.secret')) {
                Stripe::setApiKey($stripeSecret);

                try {
                    $customer = Customer::retrieve($user->customer_id);
                    $customer->delete();
                } catch (ApiErrorException $e) {
                    return response()->json(['message' => "Failed to delete Stripe customer: " . $e->getMessage()], 500);
                }
            } else {
                return response()->json(['message' => 'Stripe API key not configured'], 500);
            }
        }

        try {
            if ($user instanceof Driver) {
                if (Storage::disk('public')->directoryExists('driver_licenses/' . $user->email)) {
                    if (!Storage::disk('public')->deleteDirectory('driver_licenses/' . $user->email)) {
                        throw new Exception('Error deleting account');
                    }
                }
            }
            $user->delete();
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }



        return response()->noContent(); // HTTP 204: No Content
    }

}
