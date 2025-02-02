<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Notifications\DriverVerficationNotification;
use Illuminate\Http\Request;
use Notification;

class UserController extends Controller
{
    public function verifyDriver($driverID)
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

        if ($driver->is_verified == 0) {
            return response()->json([
                'message' => 'Driver has already been declined.'
            ], 400);
        }

        // Verify the driver and save the changes
        $driver->verify();
        $driver->save();
        Notification::send($driver, new DriverVerficationNotification(['message' => 'You account has been verified!', 'is_verified' => 1]));

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

        if ($driver->is_verified == 0) {
            return response()->json([
                'message' => 'Driver has already been declined.'
            ], 400);
        }

        // Verify the driver and save the changes
        $driver->decline();
        $driver->save();
        Notification::send($driver, new DriverVerficationNotification(['message' => 'You account has been declined!', 'is_verified' => 0]));

        return response()->json([
            'message' => 'Driver declined successfully.',
            'is_verified' => 0,
            'driver' => $driver
        ], 200);
    }

}
