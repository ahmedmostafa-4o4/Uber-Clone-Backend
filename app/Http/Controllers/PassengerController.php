<?php

namespace App\Http\Controllers;

use App\Models\Passenger;
use Illuminate\Http\Request;




class PassengerController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    /**
     * @OA\Get(
     *     path="/api/passengers",
     *     summary="Get All Passengers",
     *     description="Fetches all passengers with their associated rides",
     *     @OA\Response(
     *         response=200,
     *         description="A list of passengers"
     *      
     *     ),
     *     @OA\Response(response=404, description="No passengers found")
     * )
     */
    public function index()
    {
        $passengers = Passenger::with('rides')->get();

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

    /**
     * @OA\Get(
     *     path="/api/passengers/{passengerID}",
     *     summary="Get a specific passenger",
     *     description="Fetches a passenger by ID along with their associated rides",
     *     @OA\Parameter(
     *         name="passengerID",
     *         in="path",
     *         description="ID of the passenger",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Details of the specific passenger"
     *     ),
     *     @OA\Response(response=404, description="Passenger not found")
     * )
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
    public function update(Request $request, Passenger $passenger)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */

    /**
     * @OA\Delete(
     *     path="/api/passengers",
     *     summary="Delete multiple passengers",
     *     description="Deletes multiple passengers by their IDs",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"passenger_ids"},
     *             @OA\Property(property="passenger_ids", type="array", @OA\Items(type="integer", example=1))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Passengers successfully deleted",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="2 passengers deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(response=404, description="No passengers found to delete")
     * )
     */
    public function destroy(Request $request)
    {
        // Validate that the 'passenger_ids' field exists and is an array
        $validated = $request->validate([
            'passenger_ids' => 'required|array',  // Ensuring 'passenger_ids' is an array
            'passenger_ids.*' => 'exists:passengers,id',  // Ensuring each ID exists in the passengers table
        ]);

        $passengers = Passenger::whereIn('id', $validated['passenger_ids'])->get();

        // Use the validated IDs to delete the passengers

        foreach ($passengers as $passenger) {
            // Delete all tokens associated with this passenger
            $passenger->tokens->each(function ($token) {
                $token->delete();
            });
        }

        $deletedCount = Passenger::whereIn('id', $validated['passenger_ids'])->delete();


        if ($deletedCount) {
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
