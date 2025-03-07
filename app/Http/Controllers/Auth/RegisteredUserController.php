<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OTP;
use App\Models\Passenger;
use App\Models\User;
use App\Notifications\NewDriverNotification;
use App\Notifications\OTPNotification;
use App\Notifications\PassengerVerficationNotification;
use App\OTPService;
use App\ResponseMessageService;
use DB;
use Exception;
use Illuminate\Http\Request;
use Log;
use Notification;
use Response;
use Storage;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function __construct(private OTPService $otpService, private ResponseMessageService $responseMessageService)
    {
    }

    private function createUser(array $data, $userType)
    {
        if ($userType === 'driver') {
            $data['id_card_image'] = json_encode($data['id_card_image']);
            $data['driving_license_image'] = json_encode($data['driving_license_image']);
            $data['license_image'] = json_encode($data['license_image']);
        }
        $userModel = match ($userType) {
            'passenger' => Passenger::class,
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
                'license_image.front' => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'license_image.back' => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'driving_license_image.front' => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'driving_license_image.back' => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'id_card_image.front' => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'id_card_image.back' => 'required|image|mimes:jpeg,png,jpg|max:2048',

            ], ['email.regex' => 'The email must be a valid Gmail address.']),
            'admin' => $request->validate([
                'email' => 'required|email|unique:users,email|regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/'
            ], ['email.regex' => 'The email must be a valid Gmail address.']),
        };

        if ($data['user_type'] === 'driver') {
            $storagePath = 'driver_licenses/' . $additionalFields['email'];

            $additionalFields['license_image']['front'] = $request->file('license_image.front')->store($storagePath . '/license_image/front', 'public');
            $additionalFields['license_image']['back'] = $request->file('license_image.back')->store($storagePath . '/license_image/back', 'public');

            $additionalFields['driving_license_image']['front'] = $request->file('driving_license_image.front')->store($storagePath . '/driving_license_image/front', 'public');
            $additionalFields['driving_license_image']['back'] = $request->file('driving_license_image.back')->store($storagePath . '/driving_license_image/back', 'public');

            $additionalFields['id_card_image']['front'] = $request->file('id_card_image.front')->store($storagePath . '/id_card_image/front', 'public');
            $additionalFields['id_card_image']['back'] = $request->file('id_card_image.back')->store($storagePath . '/id_card_image/back', 'public');

        }


        if ($data['user_type'] !== 'passenger') {
            $user = $this->createUser(
                array_merge($data, $additionalFields),
                $data['user_type']
            );
        }


        if ($data['user_type'] === 'passenger') {
            try {
                DB::beginTransaction();
                $otp = $this->otpService->createOtp($additionalFields['email']);
                Notification::route('mail', $additionalFields['email'])->notify(new OTPNotification($otp));
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                return $this->responseMessageService->error('Error sending OTP. Please try again. ' . $e->getMessage());

            }
            return response()->json([
                'message' => 'OTP sent, please verify your email.',
                'data' => ['OTP_expires_at' => now()->addMinutes(5), 'user_type' => $data['user_type']]
            ], 201);
        }

        if ($data['user_type'] === 'driver') {
            $admins = User::all();
            if ($admins->count()) {
                Notification::send($admins, new NewDriverNotification($user));
            }
            return response()->json([
                'user' => $user,
                'message' => 'Please wait for admin verfication...',
                'data' => ['is_verified' => $user->is_verified, 'user_type' => $data['user_type']]
            ], 201);
        }


        return response()->json([
            'user' => $user,
            'token' => $user->createToken('API Token')->plainTextToken,
            'message' => 'Registration successful!',
            'user_type' => $data['user_type']
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
            ], 422);
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

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'required|confirmed|min:8',
            'email' => [
                'required',
                'email',
                'unique:passengers,email',
                'regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/'
            ],
            'phone_number' => 'required|string|max:15|regex:/^\+?[0-9]+$/',
            'address' => 'nullable|string',
            'user_type' => 'required|in:passenger',
        ]);

        $otp = Otp::where('identifier', $data['email'])
            ->where('otp_code', $validated['otp'])
            ->where('expires_at', '>', now())
            ->where('is_used', false)
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        $user = Passenger::create(array_merge($data, ['email_verified_at' => now()]));

        Notification::send($user, new PassengerVerficationNotification(['message' => 'OTP verified successfully!', 'email_verified_at' => now()]));
        $otp->delete();
        return response()->json(['message' => 'OTP verified successfully!', 'user' => $user, 'token' => $user->createToken('API Token')->plainTextToken, 'email_verified_at' => now(), 'user_type' => $data['user_type']], \Illuminate\Http\Response::HTTP_CREATED);
    }

    public function resendOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $passenger = Passenger::where('email', $validated['email'])->first();
        // Check if the email is already verified
        if (isset($passenger)) {
            return response()->json([
                'message' => 'This email is already verified at ' . $passenger->email_verified_at,
            ], \Illuminate\Http\Response::HTTP_BAD_REQUEST);
        }

        // Retrieve the most recent OTP for this email
        $existingOtp = OTP::where('identifier', $validated['email'])
            ->where('is_used', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$existingOtp) {
            return response()->json([
                'message' => 'This Email is not registered',
            ], 400);
        }
        // Check if an active OTP exists
        if ($existingOtp && now()->lessThan($existingOtp->expires_at)) {
            $remainingSeconds = now()->diffInSeconds($existingOtp->expires_at);

            return response()->json([
                'message' => 'Please wait before requesting a new OTP.',
                'remaining_time' => $remainingSeconds,
            ], 429);
        }



        try {
            $otp = random_int(100000, 999999);

            Notification::route('mail', $validated['email'])
                ->notify(new OTPNotification($otp));

            OTP::create([
                'identifier' => $validated['email'],
                'otp_code' => $otp,
                'expires_at' => now()->addMinutes(5),
                'is_used' => false,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error sending OTP. ' . $e->getMessage(),
            ], 500);
        }


        return response()->json([
            'message' => 'OTP resent successfully! Please check your email.',
            'OTP_expires_at' => now()->addMinutes(5)
        ], \Illuminate\Http\Response::HTTP_CREATED);
    }
}
