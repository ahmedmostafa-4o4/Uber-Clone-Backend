<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        if ($ride->status !== self::STATUS_ARRIVED)
            return $this->errorResponse('Ride is not arrived yet.', 404);

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

    public function store(Request $request)
    {
        $fields = $request->validate([
            'ride_id' => 'required|exists:rides,id',
            'amount' => 'required|numeric',
            'payment_method' => 'nullable|string',
            'payment_method_id' => 'nullable|string',
            'transaction_id' => 'nullable|string',
            'currency' => 'nullable|string',
            'status' => 'required|string|in:pending,succeeded,failed,canceled,refunded',
            'metadata' => 'nullable|array',
            'failure_reason' => 'nullable|string',
            'captured' => 'boolean',
        ]);

        try {
            DB::beginTransaction();
            $payment = Payment::create($fields);

            if ($fields['status'] === self::STATUS_SUCCEEDED) {
                $this->updateRideStatus(Ride::find($fields['ride_id']), self::STATUS_COMPLETED);
            }

            DB::commit();
            return response()->json(['message' => 'Payment stored successfully.', 'payment' => $payment], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);
        }
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

        try {
            $payment = Payment::updateOrCreate(
                ['ride_id' => $rideId],
                [
                    'payment_intent_id' => $paymentIntent->id,
                    'amount' => $paymentIntent->amount / 100,
                    'currency' => $paymentIntent->currency,
                    'payment_method' => $paymentIntent->payment_method_types[0] ?? 'unknown',
                    'payment_method_details' => $paymentIntent->payment_method_details ?? 'unknown',
                    'payment_method_id' => $paymentIntent->payment_method ?? null,
                    'transaction_id' => $paymentIntent->charges->data[0]->id ?? null,
                    'status' => $paymentIntent->status,
                    'captured' => $paymentIntent->charges->data[0]->captured ?? false,
                    'failure_reason' => $paymentIntent->last_payment_error->message ?? null,
                    'metadata' => $paymentIntent->metadata ?? null,
                ]
            );

            if ($event->type === 'payment_intent.succeeded') {
                $this->updateRideStatus(Ride::find($rideId), self::STATUS_COMPLETED);
            }

            return response()->json(['message' => 'Webhook handled successfully', 'payment_intent' => $paymentIntent, 'payment' => $payment], 200);
        } catch (Exception $e) {
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

        return response()->json(['message' => 'Payment confirmed successfully.']);
    }

    private function updateRideStatus(?Ride $ride, $status)
    {
        if (!$ride)
            throw new Exception('Ride not found.');
        $ride->update(['status' => $status]);
    }
}
