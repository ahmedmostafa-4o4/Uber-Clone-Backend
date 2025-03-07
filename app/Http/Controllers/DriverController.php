<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDriverRequest;
use App\Models\Driver;
use DB;
use Exception;
use Illuminate\Http\Request;
use Log;
use Storage;

class DriverController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index()
    {
        $drivers = Driver::with(['rides', 'feedbacks'])->get();
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

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver not found.'
            ], 404);
        }

        $updateData = $fields;
        $imageFields = ['license_image', 'driving_license_image', 'id_card_image'];

        foreach ($imageFields as $field) {
            if (isset($fields[$field])) {
                // Decode existing images (if stored as JSON)
                $existingImages = json_decode($driver->$field, true) ?? [];

                foreach (['front', 'back'] as $side) {
                    if (isset($fields[$field][$side])) {
                        Log::info("Processing {$field} - {$side}");

                        // Delete old image safely
                        if (isset($existingImages[$side])) {
                            Storage::disk('public')->delete($existingImages[$side]);
                        }

                        // Upload new image
                        $existingImages[$side] = Storage::disk('public')->putFile(
                            "driver_licenses/{$driver->email}/{$field}/{$side}",
                            $fields[$field][$side]
                        );
                    }
                }

                // Update the field without losing the other side
                $updateData[$field] = json_encode($existingImages);
            }
        }

        $driver->update($updateData);

        return response()->json($driver, 200);
    }

    /**
     * Remove the specified resource from storage.
     */


    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'driver_ids' => 'required|array|min:1',
            'driver_ids.*' => 'exists:drivers,id',
        ]);

        $drivers = Driver::whereIn('id', $validated['driver_ids'])->get();

        DB::beginTransaction();

        try {
            foreach ($drivers as $driver) {

                $driver->deleteLicense();

                if (isset($driver->tokens)) {
                    $driver->tokens->each(fn($token) => $token->delete());
                }
            }

            $deletedCount = Driver::whereIn('id', $validated['driver_ids'])->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} driver(s) deleted successfully.",
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error Deleting One or More Drivers. Please Try Again',
            ], 500);
        }
    }
}
