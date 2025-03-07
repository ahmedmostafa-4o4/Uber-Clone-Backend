<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PassengerController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RideBidController;
use App\Http\Controllers\RideController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/user', [AuthController::class, 'getAuth']);

    Route::get('/token', [AuthController::class, 'getToken']);

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/notifications', [NotificationController::class, 'index']);

    Route::get('/notifications/{notification}', [NotificationController::class, 'show']);

    Route::middleware('verify')->group(function () {


        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

        Route::put('/user', [AuthController::class, 'update']);

        Route::delete('/user', [AuthController::class, 'destroy']);

        Route::put('/change-password', [AuthController::class, 'changePassword'])->name('changePassword');


        Route::middleware('guard:passenger')->group(function () {

            Route::get('/rides/{ride}/bids', [RideBidController::class, 'getBids']);

            Route::post('/rides/{ride}/choose-bid', [RideBidController::class, 'chooseBid']);

            Route::get('/stripe/payment-methods/{id}', [PaymentController::class, 'show']);

            Route::get('/stripe/payment-methods', [PaymentController::class, 'paymentMethods']);

            Route::post('/stripe/set-default-payment-method', [PaymentController::class, 'setDefaultPaymentMethod']);

            Route::delete('/stripe/destroy-payment-method', [PaymentController::class, 'destroyPaymentMethod']);

            Route::post('/stripe/save-customer', [PaymentController::class, 'saveCustomer']);

            Route::get('/stripe/setup-intent', [PaymentController::class, 'setupIntent']);

            Route::post('/stripe/attach-payment-method', [PaymentController::class, 'attachPaymentMethod']);

            Route::post('/stripe/payment-intent', [PaymentController::class, 'create']);

            Route::post('/stripe/charge', [PaymentController::class, 'chargePassenger']);

        });

        Route::middleware('guard:driver')->group(function () {

            Route::post('/rides/{ride}/bids', [RideBidController::class, 'placeBid']);

            Route::get('/rides/my-rides', [RideController::class, 'driverRides']);

            Route::post('/stripe/{payment}/confirm', [PaymentController::class, 'confirmPayment']);

        });

        Route::apiResource('ride', RideController::class);

        Route::post('/feedback', [FeedbackController::class, 'store']);

        Route::get('/feedback/{feedback}', [FeedbackController::class, 'show']);

    });



    Route::middleware('guard:web')->group(function () {

        Route::put('/drivers/{driver}/verify', [UserController::class, 'verifyDriver']);

        Route::put('/drivers/{driver}/decline', [UserController::class, 'declineDriver']);

        Route::get('/admin/rides', [RideController::class, 'rides']);

        Route::get('/feedback', [FeedbackController::class, 'index']);

        Route::delete('/feedback', [FeedbackController::class, 'destroy']);

        Route::apiResource('passengers', PassengerController::class);

        Route::apiResource('drivers', DriverController::class);

        Route::delete('/drivers', [DriverController::class, 'destroy']);

        Route::delete('/passengers', [PassengerController::class, 'destroy']);

        Route::get('/feedback', [FeedbackController::class, 'index']);

    });

    Route::get('/payments', [PaymentController::class, 'index']);

    Route::get('/payments/{payment}', [PaymentController::class, 'showPayment']);

});


Route::post('/otp/verify', [RegisteredUserController::class, 'verifyOtp'])->middleware('throttle:3,1')->name('verifyOtp');

Route::post('/otp/resend', [RegisteredUserController::class, 'resendOtp'])->middleware('throttle:3,1')->name('resendOtp');

Route::post('/register', [RegisteredUserController::class, 'store'])->name('register');

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::post('/request-reset', [PasswordResetController::class, 'requestReset'])->middleware('throttle:2,1');

Route::post('/verify-otp', [PasswordResetController::class, 'verifyOtp'])->middleware('throttle:3,1');

Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:3,1');

Route::post('/webhook/stripe', [PaymentController::class, 'handleWebhook']);

