<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\User;
use App\Notifications\PaymentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Notification;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Stripe\Stripe;
use Stripe\Exception\ApiErrorException;
use Stripe\Webhook;
use Exception;

class PaymentController extends Controller
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_SUCCEEDED = 'succeeded';
    private const STATUS_FAILED = 'failed';
    private const STATUS_CANCELED = 'canceled';
    private const STATUS_REFUNDED = 'refunded';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_ARRIVED = 'arrived';

    private function errorResponse($message, $status = 400)
    {
        return response()->json(['message' => $message], $status);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user instanceof Passenger) {
            return $this->passengerPayments($request);
        } elseif ($user instanceof User) {
            $payments = Payment::with('ride')->get();
            return response()->json(['payments' => $payments], 200);
        }

        return $this->errorResponse('Unauthorized', 401);
    }

    private function passengerPayments(Request $request)
    {
        $payments = $request->user()->rides()->with('payments')->get()->flatMap->payments;

        return response()->json(['payments' => $payments], 200);
    }


    public function showPayment(Payment $payment)
    {
        if (auth()->user() instanceof Driver)
            return $this->errorResponse('Unauthorized', 401);
        return response()->json(['payment' => $payment], 200);
    }



    public function create(Request $request)
    {
        $request->validate([
            'ride_id' => 'required|exists:rides,id',
            'amount' => 'required|numeric',
            'payment_type' => 'required|string|in:card,cash',
        ]);

        $ride = Ride::find($request->ride_id);
        if (!$ride)
            return $this->errorResponse('Ride not found.', 404);
        if ($ride->status === self::STATUS_COMPLETED)
            return $this->errorResponse('Ride is already paid.', 400);


        if ($request->payment_type === 'cash') {
            $payment = Payment::create([
                'ride_id' => $request->ride_id,
                'amount' => $request->amount,
                'status' => self::STATUS_PENDING,
                'payment_method' => 'cash',
            ]);

            return response()->json(['message' => 'Cash payment created successfully', 'payment' => $payment], 201);
        }

        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => $request->amount * 100,
                'currency' => 'egp',
                'payment_method_types' => ['card'],
                'metadata' => ['ride_id' => $request->ride_id],
            ]);
        } catch (ApiErrorException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

        return response()->json(['message' => 'Payment intent created', 'payment_intent' => $paymentIntent], 201);
    }


    public function handleWebhook(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        $endpointSecret = config('services.stripe.webhook.secret');
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (Exception $e) {
            return $this->errorResponse('Webhook Error: ' . $e->getMessage(), 400);
        }

        $paymentIntent = $event->data->object;
        $rideId = $paymentIntent->metadata->ride_id ?? null;

        if (!$rideId)
            return $this->errorResponse('Ride ID missing from metadata.', 404);

        $ride = Ride::findOrFail($rideId);

        try {
            $payment = Payment::updateOrCreate(
                ['ride_id' => $rideId],
                [
                    'payment_intent_id' => $paymentIntent->id,
                    'amount' => $paymentIntent->amount / 100,
                    'currency' => $paymentIntent->currency,
                    'payment_method' => $paymentIntent->payment_method_details->type ?? 'unknown',
                    'payment_method_details' => $paymentIntent->payment_method_details ?? null,
                    'payment_method_id' => $paymentIntent->payment_method ?? null,
                    'transaction_id' => $paymentIntent->id ?? null,
                    'status' => $paymentIntent->status,
                    'captured' => $paymentIntent->captured ?? false,
                    'failure_reason' => $paymentIntent->last_payment_error->message ?? null,
                    'metadata' => $paymentIntent->metadata ?? null,
                ]
            );

            if ($event->type === 'payment_intent.succeeded') {
                $this->updateRideStatus($ride, self::STATUS_COMPLETED);
                Notification::send($ride->passenger, new PaymentNotification([
                    'name' => $ride->passenger->name,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'ride_details' => [
                        'id' => $ride->id,
                        'created_at' => $ride->created_at,
                        'status' => $ride->status,
                        'fare' => $ride->fare,
                        'passenger_name' => $ride->passenger->name,
                        'driver_name' => $ride->driver->name ?? 'N/A',
                    ],
                    'payment_method' => $paymentIntent->payment_method,
                    'status' => $payment->status
                ]));
            }

            return response()->json(['message' => 'Webhook handled successfully', 'payment_intent' => $paymentIntent, 'payment' => $payment], 200);
        } catch (ApiErrorException $e) {
            Log::error('Webhook error: ' . $e->getMessage());
            return $this->errorResponse('Error processing webhook.', 500);
        }
    }

    public function confirmPayment(Request $request, Payment $payment)
    {
        $request->validate([
            'ride_id' => 'required|exists:rides,id',
            'amount' => 'required|numeric',
        ]);

        if ($request->amount != $payment->amount) {
            return $this->errorResponse('Payment amount mismatch.', 400);
        }

        $payment->update(['status' => self::STATUS_SUCCEEDED]);
        $this->updateRideStatus(Ride::find($request->ride_id), self::STATUS_COMPLETED);
        Notification::send($payment->ride->passenger, new PaymentNotification(
            [
                'name' => $payment->ride->passenger->name,
                'amount' => $payment->amount,
                'currency' => 'EGP',
                'ride_details' => [
                    'id' => $payment->ride->id,
                    'created_at' => $payment->ride->created_at,
                    'status' => $payment->ride->status,
                    'fare' => $payment->ride->fare,
                    'passenger_name' => $payment->ride->passenger->name,
                    'driver_name' => $payment->ride->driver->name ?? 'N/A',
                ],
                'payment_method' => "cash",
                'status' => $payment->status
            ]
        ));
        return response()->json(['message' => 'Payment confirmed successfully.']);
    }

    private function updateRideStatus(?Ride $ride, $status)
    {
        if (!$ride)
            throw new Exception('Ride not found.');
        $ride->update(['status' => $status]);
    }

    public function saveCustomer(Request $request)
    {
        $passenger = $request->user();

        Stripe::setApiKey(config('services.stripe.secret'));

        if ($passenger->customer_id) {
            return response()->json(['message' => 'This customer already saved'], 400);
        }
        try {
            $customer = Customer::create([
                'name' => $passenger->name,
                'email' => $passenger->email,
                'phone' => $passenger->phone_number,
            ]);

            $passenger->update([
                'customer_id' => $customer->id,
            ]);


        } catch (ApiErrorException $e) {
            return $this->errorResponse('Error creating customer: ' . $e->getMessage(), 500);
        }
        return response()->json(['message' => 'Customer saved successfully.', 'customer' => $customer], 201);
    }

    public function setupIntent(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $passenger = $request->user();

        try {
            $setupIntent = SetupIntent::create([
                'customer' => $passenger->customer_id,
            ]);
        } catch (ApiErrorException $e) {
            return Response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Setup intent created successfully.', 'client_secret' => $setupIntent->client_secret], 201);

    }

    public function attachPaymentMethod(Request $request)
    {
        $passenger = $request->user();
        $request->validate([
            'payment_method' => 'required|string',
        ]);
        Stripe::setApiKey(config('services.stripe.secret'));
        try {
            $paymentMethod = PaymentMethod::retrieve($request->payment_method);
            $paymentMethod->attach(['customer' => $passenger->customer_id]);
            $paymentMethod = $paymentMethod->id;
            $customer = Customer::retrieve($passenger->customer_id);
            $paymentMethods = $customer->allPaymentMethods($passenger->customer_id);
            $passenger->update([
                'saved_payment_methods' => json_encode([
                    'default' => $customer->invoice_settings['default_payment_method'],
                    'methods' => array_map(function ($method) {
                        return $method['id'];
                    }, $paymentMethods['data'])
                ])
            ]);
        } catch (ApiErrorException $e) {
            return $this->errorResponse('Error attaching payment method ' . $e->getMessage(), 500);
        }

        return response()->json(['message' => 'Payment method attached successfully.', 'payment_method' => $paymentMethod], 201);
    }

    public function setDefaultPaymentMethod(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);
        $passenger = $request->user();

        Stripe::setApiKey(config('services.stripe.secret'));
        $paymentMethodId = $request->payment_method_id;

        if (!$passenger->customer_id) {
            return response()->json(['error' => 'Passenger does not have a Stripe Customer ID'], 400);
        }

        try {
            Customer::update($passenger->customer_id, [
                'invoice_settings' => ['default_payment_method' => $paymentMethodId],
            ]);
        } catch (ApiErrorException $e) {
            return Response()->json(['message' => $e->getMessage()], 500);
        }

        $passenger->saved_payment_methods = json_encode([
            'default' => $paymentMethodId,
            'methods' => $passenger->saved_payment_methods['methods'] ?? []
        ]);
        $passenger->save();

        return response()->json(['success' => 'Default payment method updated']);
    }

    public function paymentMethods(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        $passenger = $request->user();
        try {
            $methods = Customer::allPaymentMethods($passenger->customer_id);
        } catch (ApiErrorException $e) {
            return response()->json(['error' => 'Error retrieving payment methods, ' . $e->getMessage()], 500);
        }
        return response()->json(['payment_methods' => $methods], 200);
    }

    public function destroyPaymentMethod(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string'
        ]);
        $passenger = $request->user();
        Stripe::setApiKey(config('services.stripe.secret'));
        try {
            Customer::retrieve($passenger->customer_id)->retrievePaymentMethod($request->payment_method_id)->detach();
            $customer = Customer::retrieve($passenger->customer_id);
            $paymentMethods = $customer->allPaymentMethods($passenger->customer_id);
        } catch (ApiErrorException $e) {
            return Response()->json(['message' => $e->getMessage()], 500);
        }
        $passenger->saved_payment_methods = json_encode([
            'default' => $customer->invoice_settings['default_payment_method'],
            'methods' => array_map(function ($method) {
                return $method['id'];
            }, $paymentMethods['data'])
        ]);
        $passenger->save();
        return response()->json(['success' => 'Payment method removed successfully.', 'customer' => $customer], 200);
    }


    public function show(Request $request, string $id)
    {
        $passenger = $request->user();
        Stripe::setApiKey(config('services.stripe.secret'));
        try {
            $paymentMethod = Customer::retrieve($passenger->customer_id)->retrievePaymentMethod($id);
        } catch (ApiErrorException $e) {
            return Response()->json(['message' => $e->getMessage()], 500);
        }
        return Response()->json($paymentMethod);
    }
    public function chargePassenger(Request $request)
    {
        $request->validate([
            'ride_id' => 'required|exists:rides,id',
            'amount' => 'required|numeric',
        ]);
        $passenger = $request->user();

        $ride = Ride::find($request->ride_id);
        if (!$ride)
            return $this->errorResponse('Ride not found.', 404);
        if ($ride->status === self::STATUS_COMPLETED)
            return $this->errorResponse('Ride is already paid.', 400);
        if ($ride->status !== self::STATUS_ARRIVED)
            return $this->errorResponse('Ride is not arrived yet.', 404);
        if ($ride->passenger_id !== $passenger->id)
            return $this->errorResponse('Unauthorized', 401);
        if ($ride->driver_id === null)
            return $this->errorResponse('Driver not found.', 404);
        if ($request->amount !== $ride->fare)
            return $this->errorResponse('Payment amount mismatch.', 400);

        if (!$passenger->customer_id) {
            return response()->json(['error' => 'Passenger does not have a Stripe customer ID'], 400);
        }

        $paymentMethods = $passenger->saved_payment_methods;
        if (!isset($paymentMethods['default'])) {
            return response()->json(['error' => 'No default payment method found'], 400);
        }

        $paymentMethodId = $paymentMethods['default'];

        try {
            Stripe::setApiKey(config('services.stripe.secret'));
            $paymentIntent = PaymentIntent::create([
                'amount' => $request->amount * 100,
                'currency' => 'egp',
                'payment_method_types' => ['card'],
                'metadata' => ['ride_id' => $request->ride_id],
                'payment_method' => $paymentMethodId,
                'customer' => $passenger->customer_id,
                'confirm' => true,
                'off_session' => true,
            ]);
        } catch (ApiErrorException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment successful!',
            'payment_intent' => $paymentIntent,
        ]);
    }
}
