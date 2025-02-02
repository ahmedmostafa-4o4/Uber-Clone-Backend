<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OTP;
use App\Models\User;
use App\Notifications\NewDriverNotification;
use App\Notifications\OTPNotification;
use App\Notifications\PassengerVerficationNotification;
use App\OTPService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Notification;
use Response;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function __construct(private OTPService $otpService)
    {
    }

    private function createUser(array $data, $userType)
    {
        if ($userType === 'driver') {
            $data['insurance_info'] = json_encode($data['insurance_info']);
            $data['registration_info'] = json_encode($data['registration_info']);
        }
        $userModel = match ($userType) {
            'passenger' => \App\Models\Passenger::class,
            'driver' => \App\Models\Driver::class,
            'admin' => User::class,
        };

        return $userModel::create($data);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'required|confirmed|min:8',
            'user_type' => 'required|in:passenger,driver,admin',
        ]);

        $additionalFields = match ($data['user_type']) {
            'passenger' => $request->validate([
                'email' => [
                    'required',
                    'email',
                    'unique:passengers,email',
                    'regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/'
                ],
                'phone_number' => 'required|string|max:15|regex:/^\+?[0-9]+$/',
                'address' => 'nullable|string',
                'saved_payment_methods' => 'nullable',
            ], ['email.regex' => 'The email must be a valid Gmail address.']),
            'driver' => $request->validate([
                'email' => 'required|email|unique:drivers,email|regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/',
                'phone_number' => 'required|string|regex:/^\+?[0-9]{10,15}$/|unique:drivers,phone_number',
                'address' => 'required|string',
                'license_number' => 'required|string|max:50|unique:drivers,license_number',
                'driving_experience' => 'required|numeric|min:0|max:50',
                'car_model' => 'required|string|max:255',
                'license_plate' => 'required|string|max:50|unique:drivers,license_plate',
                'car_color' => 'required|string|max:50',
                'manufacturing_year' => 'required|digits:4|integer|min:1900|max:' . date('Y'),
                'insurance_info' => 'required',
                'registration_info' => 'required',
            ], ['email.regex' => 'The email must be a valid Gmail address.']),
            'admin' => $request->validate([
                'email' => 'required|email|unique:users,email|regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/'
            ], ['email.regex' => 'The email must be a valid Gmail address.']),
        };

        $user = $this->createUser(
            array_merge($data, $additionalFields),
            $data['user_type']
        );

        if ($data['user_type'] === 'passenger') {
            $otp = $this->otpService->createOtp($user->email);
            Notification::route('mail', $user->email)->notify(new OTPNotification($otp));
            event(new Registered($user));
            Auth::guard('passenger')->login($user);
            Notification::send($user, new PassengerVerficationNotification(['message' => 'OTP sent, Please verify your account']));

            return response()->json([
                'user' => $user,
                'token' => $user->createToken('API Token')->plainTextToken,
                'message' => 'OTP sent, please verify your email.',
                'data' => ['email_verified_at' => $user->email_verified_at, 'OTP_expires_at' => now()->addMinutes(5)]
            ], 201);
        }

        if ($data['user_type'] === 'driver') {
            $admins = User::all();
            Notification::send($admins, new NewDriverNotification($user));
            event(new Registered($user));
            Auth::guard('driver')->login($user);
            return response()->json([
                'user' => $user,
                'token' => $user->createToken('API Token')->plainTextToken,
                'message' => 'Please wait for admin verfication...',
                'data' => ['is_verified' => $user->is_verified]
            ], 201);
        }


        event(new Registered($user));
        Auth::guard('web')->login($user);

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('API Token')->plainTextToken,
            'message' => 'Registration successful!',
        ], 201);
    }

    public function sendOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email', // Or 'phone' for SMS
        ]);

        if ($validated['email'] !== $request->user()->email) {
            return response()->json([
                'message' => 'The email provided does not match your account email.',
            ], 400);
        }

        // Check if the email is already verified
        if ($request->user()->email_verified_at) {
            return response()->json([
                'message' => 'This email is already verified at ' . $request->user()->email_verified_at,
            ]);
        }

        $otp = random_int(100000, 999999); // Generate a 6-digit OTP

        // Save OTP in session or database for verification
        session(['otp' => $otp, 'otp_expiration' => now()->addMinutes(5)]);

        // Send the OTP via notification
        Notification::route('mail', $validated['email'])
            ->notify(new OTPNotification($otp));

        return response()->json(['message' => 'OTP sent successfully!', 'otp' => session(key: 'otp')]);
    }


    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'otp' => 'required|integer',
        ]);

        $otp = Otp::where('identifier', $request->user()->email)
            ->where('otp_code', $validated['otp'])
            ->where('expires_at', '>', now())
            ->where('is_used', false)
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        // Mark OTP as used
        $otp->update(['is_used' => true]);

        // if ($storedOtp != $validated['otp']) {
        //     return response()->json(['message' => 'Incorrect OTP'], 400);
        // }

        // Mark user as verified (if applicable)
        $request->user()->email_verified_at = now();
        $request->user()->save();

        Notification::send($request->user(), new PassengerVerficationNotification(['message' => 'OTP verified successfully!', 'email_verified_at' => now()]));
        $otp->delete();
        return response()->json(['message' => 'OTP verified successfully!', 'email_verified_at' => now()], \Illuminate\Http\Response::HTTP_CREATED);
    }

    public function resendOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        // Check if the email belongs to the authenticated user (if applicable)
        if ($request->user() && $request->user()->email !== $validated['email']) {
            return response()->json([
                'error' => 'The provided email does not match your account.',
            ], \Illuminate\Http\Response::HTTP_NOT_ACCEPTABLE);
        }

        // Check if the email is already verified
        if ($request->user() && $request->user()->email_verified_at) {
            return response()->json([
                'message' => 'This email is already verified at ' . $request->user()->email_verified_at,
            ], \Illuminate\Http\Response::HTTP_BAD_REQUEST);
        }

        // Retrieve the most recent OTP for this email
        $existingOtp = OTP::where('identifier', $validated['email'])
            ->where('is_used', false)
            ->orderBy('created_at', 'desc')
            ->first();

        // Check if an active OTP exists
        if ($existingOtp && now()->lessThan($existingOtp->expires_at)) {
            $remainingSeconds = now()->diffInSeconds($existingOtp->expires_at);

            return response()->json([
                'message' => 'Please wait before requesting a new OTP.',
                'remaining_time' => $remainingSeconds,
            ], 429);
        }

        // Generate a new OTP
        $otp = random_int(100000, 999999);

        // Save the new OTP in the database
        OTP::create([
            'identifier' => $validated['email'],
            'otp_code' => $otp,
            'expires_at' => now()->addMinutes(3),
            'is_used' => false,
        ]);

        // Send the OTP securely without exposing it
        Notification::route('mail', $validated['email'])
            ->notify(new OTPNotification($otp));
        Notification::send($request->user(), new PassengerVerficationNotification(['message' => 'OTP resent, Please verify your account']));

        return response()->json([
            'message' => 'OTP resent successfully! Please check your email.',
        ], \Illuminate\Http\Response::HTTP_CREATED);
    }
}
