<?php

namespace App;
use App\Models\Otp;

class OTPService {
    public function createOtp($identifier) {
        $otp = random_int(100000, 999999);
        Otp::create([
            'identifier' => $identifier,
            'otp_code' => $otp,
            'expires_at' => now()->addMinutes(5),
            'is_used' => false,
        ]);
        return $otp;
    }

    public function verifyOtp($identifier, $otp) {
        return Otp::where('identifier', $identifier)
            ->where('otp_code', $otp)
            ->where('expires_at', '>', now())
            ->where('is_used', false)
            ->first();
    }
}

