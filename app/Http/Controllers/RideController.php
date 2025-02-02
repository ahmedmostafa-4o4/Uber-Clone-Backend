<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRideRequest;
use App\Http\Requests\UpdateRideRequest;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\RideBid;
use App\Notifications\BidAcceptedNotification;
use App\Notifications\NewRideNotification;
use App\Notifications\UpdateRideNotification;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Notification;
use Response;

class RideController extends Controller
{


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Rides = auth()->user()->rides;
        if ($Rides->isEmpty()) {
            return response()->json([
                'message' => 'No rides found'
            ], 404);
        }
        return response()->json($Rides);
    }

    public function driverRides(Request $request)
    {
        $driver = $request->user();
        if (!($driver instanceof Driver)) {
            return response()->json(["success" => false, "message" => "Unauthorized Access"], 401);
        }
        // Fetch all ride IDs from notifications for this driver
        $notifications = DatabaseNotification::where('type', 'App\Notifications\NewRideNotification')
            ->where('notifiable_type', 'App\Models\Driver')
            ->where('notifiable_id', $driver->id)
            ->get();

        // Extract ride IDs from notification data
        $rideIds = $notifications->map(function ($notification) {
            $data = $notification->data['ride_details']; // Decode JSON data
            return $data['id'] ?? null; // Get the ride ID
        })->filter(); // Remove null values

        // Fetch all rides based on extracted IDs
        $rides = Ride::whereIn('id', $rideIds)->get();

        if ($rides->isEmpty()) {
            return response()->json([
                'message' => 'No rides found'
            ], 404);
        }

        return response()->json($rides);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRideRequest $request)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Passenger)) {
            return response()->json(["success" => false, "message" => "Unauthorized Access"], 401);
        }
        $request->validated();
        $ride = Ride::create([
            'region' => $request->region,
            'pickup_location' => json_encode($request->pickup_location),
            'dropoff_location' => json_encode($request->dropoff_location),
            'passenger_id' => $request->passenger_id,
            'distance' => $request->distance,
            'status' => 'pending',
        ]);

        // إرسال إشعار لكل السائقين في نفس المنطقة
        $drivers = Driver::where('address', $ride->region)->where('status', 'active')->get();
        if ($drivers->isEmpty()) {
            return response()->json(['message' => 'There is no drivers available.'], 404);
        }
        foreach ($drivers as $driver) {
            Notification::send($driver, new NewRideNotification($ride));
        }

        return response()->json(['message' => 'Ride request sent to drivers.', 'ride' => $ride]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Ride $ride)
    {
        return response()->json([
            'success' => true,
            'data' => $ride,
        ]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRideRequest $request, Ride $ride)
    {
        try {
            $fields = $request->validated();

            if (
                ($ride->status !== "canceled" && $ride->status !== "completed") &&
                ($request->user()->id === $ride->passenger_id || $request->user()->id === $ride->driver_id)
            ) {

                if ($request->status === "going_to_passenger") {
                    $ride->start_time = now();
                    $ride->save();
                }

                if ($request->status === "arrived") {
                    $ride->end_time = now();
                    $ride->save();
                }

                $ride->update($fields);


                Notification::send([$ride->driver, $ride->passenger], new UpdateRideNotification($ride->toArray()));

                return response()->json([
                    'success' => true,
                    'data' => $ride,
                ]);
            }

            return response()->json([
                'message' => 'Unauthorized access or ride ended'
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
