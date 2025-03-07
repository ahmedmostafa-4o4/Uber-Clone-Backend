<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePassengerRequest;
use App\Models\Passenger;
use Illuminate\Http\Request;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;




class PassengerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $passengers = Passenger::with(['rides', 'feedbacks'])->get();

        if ($passengers->isEmpty()) {
            return response()->json([
                'message' => 'No passengers found'
            ], 404);
        }

        return response()->json($passengers);
    }

    /**
     * Display the specified resource.
     */

    public function show($passenger)
    {
        // Retrieve the driver and load the related rides
        $passenger = Passenger::with('rides')->find($passenger);

        // If the driver doesn't exist, return a 404 error response
        if (!$passenger) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger not found.'
            ], 404);
        }

        // Return the driver and its rides in the response
        return response()->json([
            'success' => true,
            'passenger' => $passenger
        ], 200);
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePassengerRequest $request, Passenger $passenger)
    {
        $fields = $request->validated();
        if (!$passenger) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger not found.'
            ], 404);
        }

        $passenger->update($fields);
        return response()->json($passenger, 200);
    }

    /**
     * Remove the specified resource from storage.
     */

    public function destroy(Request $request)
    {
        // Validate that the 'passenger_ids' field exists and is an array
        $validated = $request->validate([
            'passenger_ids' => 'required|array',  // Ensuring 'passenger_ids' is an array
            'passenger_ids.*' => 'exists:passengers,id',  // Ensuring each ID exists in the passengers table
        ]);

        Stripe::setApiKey(config('services.stripe.secret'));

        $passengers = Passenger::whereIn('id', $validated['passenger_ids'])->get();

        $passengers->each(function ($passenger) {
            $passenger->tokens->each(function ($token) {
                $token->delete();
            });
        });

        $customerIds = $passengers->pluck('customer_id')->filter();
        try {
            foreach ($customerIds as $customerId) {
                Customer::retrieve($customerId)->delete();
            }
        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => "Failed to delete customers. " . $e->getMessage(),
            ], 500);
        }
        $deletedCount = $passengers->each->delete()->count();




        if ($deletedCount > 0) {
            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} passenger(s) deleted successfully.",
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No passengers found to delete.',
            ], 404);
        }
    }

}
