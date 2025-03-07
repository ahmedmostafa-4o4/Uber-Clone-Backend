<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DriverController;
use App\Models\Driver;
use App\Notifications\DriverVerficationNotification;
use Notification;

class UserController extends Controller
{
    public function verifyDriver($driverID)
    {
        $driver = Driver::find($driverID);

        if (!$driver) {
            return response()->json(['error' => 'Driver not found'], 404);
        }

        if ($driver->is_verified == 1) {
            return response()->json([
                'message' => 'Driver has already been verified.'
            ], 400);
        }

        $driver->is_verified = 1;
        $driver->save();

        Notification::send($driver, new DriverVerficationNotification(['message' => 'Good News Your Account Has Been Verified. Login Now!']));

        return response()->json([
            'message' => 'Driver verified successfully.',
            'is_verified' => 1,
            'driver' => $driver
        ], 200);
    }
    public function declineDriver($driverID)
    {
        $driver = Driver::find($driverID);

        if (!$driver) {
            return response()->json(['error' => 'Driver not found'], 404);
        }
        // Check if the driver is already verified
        if ($driver->is_verified == 1) {
            return response()->json([
                'message' => 'Driver has already been verified.'
            ], 400);
        }

        Notification::send($driver, new DriverVerficationNotification(['message' => 'Sorry You account has been declined.']));

        $driver->deleteLicense();

        $driver->delete();


        return response()->json([
            'message' => 'Driver declined.',
            'success' => true
        ], 200);
    }

}
