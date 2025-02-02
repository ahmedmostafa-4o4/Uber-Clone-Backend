<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDriverRequest;
use App\Models\Driver;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    /**
     * @OA\Get(
     *     path="/api/drivers",
     *     summary="Get All Drivers",
     *     description="Fetches all drivers with their associated rides",
     *     @OA\Response(
     *         response=200,
     *         description="A list of drivers"
     *      
     *     ),
     *     @OA\Response(response=404, description="No drivers found")
     * )
     */
    public function index()
    {
        $drivers = Driver::with('rides')->get();
        if ($drivers->isEmpty()) {
            return response()->json([
                'message' => 'No drivers found'
            ], 404);
        }
        return response()->json($drivers);
    }

    /**
     * Display the specified resource.
     */

    /**
     * @OA\Get(
     *     path="/api/drivers/{driverID}",
     *     summary="Get a specific driver",
     *     description="Fetches a driver by ID along with their associated rides",
     *     @OA\Parameter(
     *         name="driverID",
     *         in="path",
     *         description="ID of the driver",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Details of the specific driver"
     *     ),
     *     @OA\Response(response=404, description="driver not found")
     * )
     */

    public function show($driver)
    {
        // Retrieve the driver and load the related rides
        $driver = Driver::with('rides')->find($driver);

        // If the driver doesn't exist, return a 404 error response
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver not found.'
            ], 404);
        }

        // Return the driver and its rides in the response
        return response()->json([
            'success' => true,
            'driver' => $driver
        ], 200);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDriverRequest $request, Driver $driver)
    {
        $fields = $request->validated();
        $driver->update($fields);
        return response()->json($driver, 200);
    }

    /**
     * Remove the specified resource from storage.
     */

    /**
     * @OA\Delete(
     *     path="/api/drivers",
     *     summary="Delete multiple drivers",
     *     description="Deletes multiple drivers by their IDs",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"driver_ids"},
     *             @OA\Property(property="driver_ids", type="array", @OA\Items(type="integer", example=1))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Drivers successfully deleted",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="2 passengers deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(response=404, description="No drivers found to delete.", @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No drivers found to delete.")
     *         ))
     * )
     */
    public function destroy(Request $request)
    {
        // Validate that the 'passenger_ids' field exists and is an array
        $validated = $request->validate([
            'driver_ids' => 'required|array',  // Ensuring 'passenger_ids' is an array
            'driver_ids.*' => 'exists:drivers,id',  // Ensuring each ID exists in the passengers table
        ]);

        $drivers = Driver::whereIn('id', $validated['driver_ids'])->get();

        // Use the validated IDs to delete the passengers

        foreach ($drivers as $driver) {
            // Delete all tokens associated with this passenger
            $driver->tokens->each(function ($token) {
                $token->delete();
            });
        }

        $deletedCount = Driver::whereIn('id', $validated['driver_ids'])->delete();


        if ($deletedCount) {
            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} driver(s) deleted successfully.",
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No drivers found to delete.',
            ], 404);
        }
    }
}
