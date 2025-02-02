<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Passenger;
use App\Models\User;
use App\Notifications\OTPNotification;
use App\OTPService;
use Hash;
use Illuminate\Http\Request;

class PasswordResetController extends Controller
{
    public function __construct(private OTPService $otpService) {}
    public function requestReset(Request $request)
    {
        $request->validate(['user_type' => 'required|in:passenger,driver,admin']);

        if($request->user_type === 'passenger') {
            $request->validate(['email' => 'required|email|exists:passengers,email']);
            $user = Passenger::where('email', $request->email)->first();
        }
        elseif ($request->user_type === 'driver') {
            $request->validate(['email' => 'required|email|exists:drivers,email']);
            $user = Driver::where('email', $request->email)->first();
        }
        else {
            $request->validate(['email' => 'required|email|exists:users,email']);
            $user = User::where('email', $request->email)->first();
        }

        $otp = $this->otpService->createOtp($user->email);
        
        $user->notify(new OTPNotification($otp));

        return response()->json(['message' => 'OTP sent to your email.', 'email' => $user->email, 'user_type' => $request->user_type]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:otps,identifier',
            'otp' => 'required|numeric',
        ]);

        $otpRecord=$this->otpService->verifyOtp($request->email, $request->otp);

        if (!$otpRecord) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 400);
        }

        return response()->json(['message' => 'OTP verified successfully.', 'email' => $request->email,'otp'=> $otpRecord->otp_code]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'otp' => 'required|numeric',
            'user_type' => 'required|in:passenger,driver,admin',
            'password' => 'required|min:8|confirmed',
        ]);

            if($request->user_type === 'passenger') {
            $request->validate(['email' => 'required|email|exists:passengers,email']);
            $user = Passenger::where('email', $request->email)->first();
        }
        elseif ($request->user_type === 'driver') {
            $request->validate(['email' => 'required|email|exists:drivers,email']);
            $user = Driver::where('email', $request->email)->first();
        }
        else {
            $request->validate(['email' => 'required|email|exists:users,email']);
            $user = User::where('email', $request->email)->first();
        }

        $otpRecord = $this->otpService->verifyOtp($request->email, $request->otp);

        if (!$otpRecord) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 400);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Delete OTP record
        $otpRecord->delete();

        return response()->json(['message' => 'Password reset successfully.']);
    }
}
