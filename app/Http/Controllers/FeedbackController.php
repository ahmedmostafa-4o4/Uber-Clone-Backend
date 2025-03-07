<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackRequest;
use App\Models\Feedback;
use App\Models\Passenger;
use App\Models\Ride;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $feedbacks = Feedback::with('ride')->get();

        if (!$feedbacks) {
            return response()->json(['message' => 'There is no feedbacks yet.'], 404);
        }
        return response()->json($feedbacks);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFeedbackRequest $request)
    {
        $validated = $request->validated();
        $ride = Ride::findOrFail($validated['ride_id']);
        if ($ride->status !== 'completed') {
            return response()->json([
                'success' => false,
                'data' => 'Ride should be completed first.',
            ], 400);
        }
        $feedback = Feedback::updateOrCreate(['ride_id' => $validated['ride_id']], $validated);

        $passenger = $ride->passenger;
        if ($passenger && isset($validated['passenger_rating'])) {
            $rating = json_decode($passenger->rating, true) ?? ['rate' => 0, 'rate_count' => 0];

            $rating['rate'] = ($rating['rate'] * $rating['rate_count'] + $validated['passenger_rating']) / ($rating['rate_count'] + 1);
            $rating['rate_count'] += 1;

            $passenger->rating = json_encode($rating);
            $passenger->save();
        }

        $driver = $ride->driver;
        if ($driver && isset($validated['driver_rating'])) {
            $rating = json_decode($driver->rating, true) ?? ['rate' => 0, 'rate_count' => 0];

            $rating['rate'] = ($rating['rate'] * $rating['rate_count'] + $validated['driver_rating']) / ($rating['rate_count'] + 1);
            $rating['rate_count'] += 1;

            $driver->rating = json_encode($rating);
            $driver->save();
        }
        return response()->json([
            'success' => true,
            'data' => $feedback,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Feedback $feedback)
    {
        if (!$feedback) {
            return response()->json(['message' => 'Please select valid feedback.'], 404);
        }
        return response()->json($feedback->with('ride'));
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $data = $request->validate([
            'feedback_ids' => 'required|array',
            'feedback_ids.*' => 'exists:feedbacks,id',
        ]);

        $deletedCount = Feedback::whereIn('id', $data['feedback_ids'])->delete();

        if ($deletedCount > 0) {
            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} feedback(s) deleted successfully.",
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No feedback found to delete.',
            ], 404);
        }
    }
}
