<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->notifications;
    }

    public function show(Request $request, string $notification)
    {
        // Retrieve the notification
        $notification = DatabaseNotification::find($notification);

        // Check if the notification belongs to the authenticated user
        if ($notification && $notification->notifiable_id === $request->user()->id) {
            $notification->update(["read_at" => now()]);
            return response()->json($notification);
        }

        return response()->json(['error' => 'Notification not found'], 404);
    }

    public function destroy(Request $request, string $notification)
    {
        $notification = DatabaseNotification::find($notification);

        if ($notification && $notification->notifiable_id === $request->user()->id) {
            $notification->delete();
            return response()->json(['message' => "Notification deleted successfully"]);
        }

        return response()->json(['error' => 'Notification not found or unauthorized'], 404);
    }
}
