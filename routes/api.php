<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\PassengerController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RideBidController;
use App\Http\Controllers\RideController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;
/**
 *
 */
/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         title="Uber Clone API",
 *         version="1.0.0",
 *         description="API documentation for the Uber Clone project",
 *         @OA\Contact(
 *             email="support@example.com"
 *         )
 *     )
 *  @OA\PathItem(
 *     path="/api"
 * )
 * )
 */

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/user', function (Request $request) {
        $user = $request->user();

        if ($user instanceof \App\Models\Passenger) {
            // Fetch rides for a passenger
            $rides = $user->rides()->with('driver')->get(); // Assuming a 'driver' relationship exists
            return response()->json([
                'user' => $user,
                'user_type' => 'Passenger',
                'rides' => $rides,
            ]);
        } elseif ($user instanceof \App\Models\Driver) {
            // Fetch rides for a driver
            $rides = $user->rides()->with('passenger')->get(); // Assuming a 'passenger' relationship exists
            return response()->json([
                'user' => $user,
                'user_type' => 'Driver',
                'rides' => $rides,
            ]);
        }

        return $user;
    });



    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/notifications', function (Request $request) {
        return $request->user()->notifications;
    });


    Route::get('/notifications/{notification}', function (Request $request, $notification) {
        // Retrieve the notification
        $notification = DatabaseNotification::find($notification);

        // Check if the notification belongs to the authenticated user
        if ($notification && $notification->notifiable_id === $request->user()->id) {
            $notification->update(["read_at" => now()]);
            return response()->json($notification);
        }

        return response()->json(['error' => 'Notification not found or unauthorized'], 404);
    });

    Route::middleware('guard:passenger')->group(function () {

        Route::post('/otp/verify', [RegisteredUserController::class, 'verifyOtp'])->middleware('throttle:3,1')->name('verifyOtp');

        Route::post('/otp/resend', [RegisteredUserController::class, 'resendOtp'])->middleware('throttle:2,1')->name('resendOtp');

    });

    Route::middleware('verify')->group(function () {

        Route::put('/change-password', [AuthController::class, 'changePassword'])->name('changePassword');

        Route::delete('/notifications/{notification}', function (Request $request, $notification) {
            // Retrieve the notification
            $notification = DatabaseNotification::find($notification);

            // Check if the notification belongs to the authenticated user
            if ($notification && $notification->notifiable_id === $request->user()->id) {
                $notification->delete();
                return response()->json(['message' => "Notification deleted successfully"]);
            }

            return response()->json(['error' => 'Notification not found or unauthorized'], 404);
        });

        Route::put('/user', [AuthController::class, 'update']);
        Route::delete('/user', [AuthController::class, 'destory']);


        Route::middleware('guard:passenger')->group(function () {
            Route::get('/rides/{ride}/bids', [RideBidController::class, 'getBids']);
            Route::post('/rides/{ride}/choose-bid', [RideBidController::class, 'chooseBid']);
        });

        Route::middleware('guard:driver')->group(function () {
            Route::post('/rides/{ride}/bids', [RideBidController::class, 'placeBid']);
            Route::get('/rides/my-rides', [RideController::class, 'driverRides']);
        });
        Route::apiResource('ride', RideController::class);

    });

    Route::middleware('guard:web')->group(function () {
        Route::put('/drivers/{driver}/verify', [UserController::class, 'verifyDriver']);
        Route::put('/drivers/{driver}/decline', [UserController::class, 'declineDriver']);

        Route::apiResource('feedbacks', FeedbackController::class);
        Route::apiResource('passengers', PassengerController::class);
        Route::apiResource('drivers', DriverController::class);
        Route::delete('/drivers', [DriverController::class, 'destroy']);
        Route::delete('/passengers', [PassengerController::class, 'destroy']);
    });


});



Route::post('/register', [RegisteredUserController::class, 'store'])->name('register');
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/request-reset', [PasswordResetController::class, 'requestReset'])->middleware('throttle:2,1');
Route::post('/verify-otp', [PasswordResetController::class, 'verifyOtp'])->middleware('throttle:3,1');
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:3,1');

Route::post('/stripe/payment-intent', [PaymentController::class, 'create']);
Route::post('/stripe', [PaymentController::class, 'store']);
Route::post('/webhook/stripe', [PaymentController::class, 'handleWebhook']);
Route::post('/payment/confirm', [PaymentController::class, 'confirmPayment']);
