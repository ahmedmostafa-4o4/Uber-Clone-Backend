<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use App\Models\RideBid;
use App\Notifications\BidAcceptedNotification;
use Illuminate\Http\Request;
use Notification;

class RideBidController extends Controller
{
    public function chooseBid(Request $request, $rideId)
    {
        $request->validate([
            'bid_id' => 'required|exists:ride_bids,id',
        ]);

        $ride = Ride::findOrFail($rideId);
        $bid = RideBid::findOrFail($request->bid_id);

        if ($bid->ride_id !== $ride->id) {
            return response()->json(['message' => 'Invalid bid for this ride.'], 400);
        }

        $ride->update(['status' => 'accepted', 'driver_id' => $bid->driver_id, "fare" => $bid->price, "updated_at" => now()]);

        Notification::send($bid->driver, new BidAcceptedNotification(['id' => $ride->id, 'passenger' => $ride->passenger->name, 'passenger_rating' => ['rate' => json_decode($ride->passenger->rating, true)['rate'], 'rate_count' => json_decode($ride->passenger->rating, true)['rate_count']], 'distance' => $ride->distance, 'status' => $ride->status, 'pickup_location' => json_decode($ride->pickup_location, true), 'dropoff_location' => json_decode($ride->dropoff_location, true)]));

        RideBid::where('ride_id', $rideId)->delete();

        return response()->json(['message' => 'Bid accepted successfully.', 'bid' => $bid]);
    }
    public function placeBid(Request $request, $rideId)
    {
        $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'price' => 'required|numeric|min:0',
        ]);

        $ride = Ride::findOrFail($rideId);

        if ($ride->status !== 'pending') {
            return response()->json(['message' => 'Cannot place a bid for this ride.'], 400);
        }

        RideBid::create([
            'ride_id' => $ride->id,
            'driver_id' => $request->driver_id,
            'price' => $request->price,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Bid placed successfully.']);
    }

    public function getBids($rideId)
    {
        $ride = Ride::findOrFail($rideId);

        if ($ride->status !== 'pending') {
            return response()->json(['message' => 'No bids available for this ride.'], 400);
        }

        $bids = RideBid::where('ride_id', $ride->id)->with([
            'driver',
            'driver.feedbacks' => function ($query) {
                $query->select('id', 'driver_id', 'driver_rating', 'passenger_comments');
            },
            'ride'
        ])->get();

        return response()->json($bids);
    }





}
